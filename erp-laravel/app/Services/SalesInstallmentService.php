<?php

namespace App\Services;

use App\Models\ErpRecord;
use App\Models\ErpRecordLink;
use App\Models\Payment;
use App\Models\SalesInstallment;
use App\Models\SalesInstallmentPlan;
use App\Models\User;
use App\Services\ProductIntegrationService;
use App\Support\ErpModules;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SalesInstallmentService
{
    private const TAX_RATE = 0.11;

    public function createPlan(ErpRecord $salesRecord, array $data, ?User $actor = null): SalesInstallmentPlan
    {
        $this->validateSalesRecord($salesRecord);
        $this->ensureCanCreatePlan($salesRecord);

        $netAmount = round((float) $salesRecord->amount, 2);
        $hasProductTax = array_key_exists('tax_amount', $salesRecord->extra ?? []);
        $taxAmount = $hasProductTax
            ? round((float) $salesRecord->extra['tax_amount'], 2)
            : round($netAmount * self::TAX_RATE, 2);
        $taxRate = $netAmount > 0 ? round($taxAmount / $netAmount, 4) : self::TAX_RATE;
        $totalAmount = round((float) ($data['total_amount'] ?: ($netAmount + $taxAmount)), 2);
        $downPayment = round((float) ($data['down_payment'] ?? 0), 2);
        $installmentCount = (int) $data['installment_count'];
        $intervalMonths = (int) $data['interval_months'];
        $firstDueDate = Carbon::parse($data['first_due_date'])->toDateString();

        if ($totalAmount <= 0 || $downPayment < 0 || $downPayment >= $totalAmount) {
            throw ValidationException::withMessages([
                'down_payment' => 'Uang muka harus lebih kecil dari total tagihan cicilan.',
            ]);
        }

        $amounts = $this->splitAmounts($totalAmount, $downPayment, $installmentCount);

        return DB::transaction(function () use ($salesRecord, $actor, $netAmount, $taxRate, $taxAmount, $totalAmount, $downPayment, $installmentCount, $intervalMonths, $firstDueDate, $amounts, $data) {
            $plan = SalesInstallmentPlan::create([
                'erp_record_id' => $salesRecord->id,
                'customer' => $salesRecord->title,
                'net_amount' => $netAmount,
                'tax_rate' => $taxRate,
                'tax_amount' => $taxAmount,
                'total_amount' => $totalAmount,
                'down_payment' => $downPayment,
                'installment_count' => $installmentCount,
                'interval_months' => $intervalMonths,
                'first_due_date' => $firstDueDate,
                'status' => 'active',
                'notes' => $data['notes'] ?? null,
                'metadata' => [
                    'source_sales_code' => $salesRecord->code,
                    'created_from' => 'sales_installment_management',
                    'product_lines' => $salesRecord->extra['product_lines'] ?? [],
                    'created_at' => now()->toDateTimeString(),
                ],
                'created_by' => $actor?->id,
            ]);

            $this->markSalesAsInstallment($salesRecord, $plan, [
                'paid_count' => 0,
                'paid_amount' => 0,
                'outstanding_amount' => $totalAmount,
                'next_due_date' => $firstDueDate,
                'last_payment_order_id' => null,
            ]);

            $this->deactivateStandardSalesInvoice($salesRecord->fresh(), $plan, $actor);

            foreach ($amounts as $index => $amount) {
                $installmentNo = $index + 1;
                $dueDate = Carbon::parse($firstDueDate)
                    ->addMonthsNoOverflow(($installmentNo - 1) * $intervalMonths)
                    ->toDateString();

                $installment = SalesInstallment::create([
                    'sales_installment_plan_id' => $plan->id,
                    'installment_no' => $installmentNo,
                    'amount' => $amount,
                    'due_date' => $dueDate,
                    'status' => 'open',
                    'invoice_no' => $this->invoiceNo($salesRecord, $installmentNo),
                    'virtual_account' => $this->virtualAccount($salesRecord, $installmentNo),
                    'notes' => $installmentNo === 1 && $downPayment > 0 ? 'Uang muka cicilan penjualan.' : 'Cicilan penjualan.',
                ]);

                $financeRecord = $this->createFinanceInvoice($salesRecord, $plan, $installment, $actor);

                $installment->forceFill([
                    'finance_record_id' => $financeRecord->id,
                    'metadata' => [
                        'finance_record_id' => $financeRecord->id,
                        'finance_record_code' => $financeRecord->code,
                    ],
                ])->save();

                app(ErpModuleIntegrationService::class)->syncRecord($financeRecord->fresh(), $actor);
            }

            $this->refreshPlanStatus($plan->fresh(['installments', 'salesRecord']), $actor);

            return $plan->fresh(['salesRecord', 'installments.financeRecord']);
        });
    }

    public function markInstallmentPaidFromFinance(ErpRecord $financeRecord, Payment $payment, ?User $actor = null): void
    {
        $installmentId = $financeRecord->extra['sales_installment_id'] ?? null;

        if (! $installmentId) {
            return;
        }

        DB::transaction(function () use ($installmentId, $payment, $actor) {
            $installment = SalesInstallment::whereKey($installmentId)->lockForUpdate()->first();

            if (! $installment) {
                return;
            }

            $metadata = array_merge($installment->metadata ?? [], [
                'paid_payment_id' => $payment->id,
                'paid_order_id' => $payment->order_id,
                'paid_at' => ($payment->paid_at ?: now())->toDateTimeString(),
            ]);

            $installment->forceFill([
                'status' => 'paid',
                'paid_at' => $payment->paid_at ?: now(),
                'metadata' => $metadata,
            ])->save();

            $this->refreshPlanStatus($installment->plan()->with(['installments', 'salesRecord'])->first(), $actor, $payment);
        });
    }

    public function refreshPlanStatus(?SalesInstallmentPlan $plan, ?User $actor = null, ?Payment $payment = null): void
    {
        if (! $plan) {
            return;
        }

        $plan->loadMissing(['installments', 'salesRecord']);
        $installments = $plan->installments;
        $totalCount = $installments->count();
        $paidCount = $installments->where('status', 'paid')->count();
        $paidAmount = round((float) $installments->where('status', 'paid')->sum('amount'), 2);
        $outstandingAmount = max(round((float) $plan->total_amount - $paidAmount, 2), 0);
        $nextDueDate = optional($installments
            ->where('status', '!=', 'paid')
            ->sortBy('due_date')
            ->first()?->due_date)->toDateString();
        $overdueCount = $installments
            ->where('status', '!=', 'paid')
            ->filter(fn (SalesInstallment $installment) => $installment->due_date && $installment->due_date->lt(today()))
            ->count();
        $status = $paidCount >= $totalCount && $totalCount > 0
            ? 'paid'
            : ($overdueCount > 0 ? 'overdue' : 'active');

        $plan->forceFill([
            'status' => $status,
            'metadata' => array_merge($plan->metadata ?? [], [
                'paid_count' => $paidCount,
                'total_count' => $totalCount,
                'paid_amount' => $paidAmount,
                'outstanding_amount' => $outstandingAmount,
                'next_due_date' => $nextDueDate,
                'last_payment_order_id' => $payment?->order_id,
                'last_synced_at' => now()->toDateTimeString(),
            ]),
        ])->save();

        if ($plan->salesRecord) {
            $this->markSalesAsInstallment($plan->salesRecord, $plan->fresh(), [
                'paid_count' => $paidCount,
                'paid_amount' => $paidAmount,
                'outstanding_amount' => $outstandingAmount,
                'next_due_date' => $nextDueDate,
                'last_payment_order_id' => $payment?->order_id,
            ], $status === 'paid');

            app(ProductIntegrationService::class)->syncRecord($plan->salesRecord->fresh(), null, $actor);
            app(ErpModuleIntegrationService::class)->syncRecord($plan->salesRecord->fresh(), $actor);
        }
    }

    private function validateSalesRecord(ErpRecord $salesRecord): void
    {
        if ($salesRecord->module !== 'sales') {
            throw ValidationException::withMessages([
                'sales_record_id' => 'Cicilan hanya bisa dibuat dari record modul Penjualan.',
            ]);
        }

        if ((float) $salesRecord->amount <= 0) {
            throw ValidationException::withMessages([
                'sales_record_id' => 'Nilai order penjualan harus lebih dari 0 sebelum dibuat cicilan.',
            ]);
        }
    }

    private function ensureCanCreatePlan(ErpRecord $salesRecord): void
    {
        if ($salesRecord->status === 'Paid') {
            throw ValidationException::withMessages([
                'sales_record_id' => 'Sales order yang sudah Paid tidak bisa dibuat ulang menjadi cicilan.',
            ]);
        }

        if (SalesInstallmentPlan::where('erp_record_id', $salesRecord->id)->exists()) {
            throw ValidationException::withMessages([
                'sales_record_id' => 'Sales order ini sudah mempunyai jadwal cicilan.',
            ]);
        }

        $paidStandardInvoice = $salesRecord->outgoingLinks()
            ->with('targetRecord.payments')
            ->where('relation_type', 'sales_ar_invoice')
            ->get()
            ->contains(fn (ErpRecordLink $link) => $link->targetRecord?->payments?->contains(fn ($payment) => $payment->status === 'paid'));

        if ($paidStandardInvoice) {
            throw ValidationException::withMessages([
                'sales_record_id' => 'Sales order ini sudah punya invoice penuh yang dibayar. Tidak bisa diubah menjadi cicilan.',
            ]);
        }
    }

    private function splitAmounts(float $totalAmount, float $downPayment, int $installmentCount): array
    {
        $amounts = [];
        $remainingTerms = $installmentCount;
        $remainingAmount = $totalAmount;

        if ($downPayment > 0) {
            $amounts[] = $downPayment;
            $remainingTerms--;
            $remainingAmount = round($totalAmount - $downPayment, 2);
        }

        $baseAmount = $remainingTerms > 0 ? floor(($remainingAmount / $remainingTerms) * 100) / 100 : 0;

        for ($term = 1; $term <= $remainingTerms; $term++) {
            $amounts[] = $term === $remainingTerms
                ? round($totalAmount - array_sum($amounts), 2)
                : round($baseAmount, 2);
        }

        return $amounts;
    }

    private function markSalesAsInstallment(ErpRecord $salesRecord, SalesInstallmentPlan $plan, array $summary, bool $paid = false): void
    {
        $extra = array_merge($salesRecord->extra ?? [], [
            'installment_enabled' => true,
            'installment_plan_id' => $plan->id,
            'installment_status' => $paid ? 'paid' : $plan->status,
            'installment_total_count' => $plan->installment_count,
            'installment_paid_count' => $summary['paid_count'],
            'installment_total_amount' => (float) $plan->total_amount,
            'installment_paid_amount' => $summary['paid_amount'],
            'installment_outstanding_amount' => $summary['outstanding_amount'],
            'installment_next_due_date' => $summary['next_due_date'],
            'installment_last_payment_order_id' => $summary['last_payment_order_id'],
            'payment_mode' => 'installment',
            'payment_confirmation_status' => $paid ? 'confirmed' : ($summary['paid_count'] > 0 ? 'partial_payment' : 'waiting_payment'),
            'finance_verification_status' => $paid ? 'verified' : 'installment_scheduled',
            'installment_synced_at' => now()->toDateTimeString(),
        ]);

        $salesRecord->forceFill([
            'status' => $paid ? 'Paid' : (in_array($salesRecord->status, ['Paid'], true) ? $salesRecord->status : 'Invoiced'),
            'extra' => $extra,
        ])->save();
    }

    private function deactivateStandardSalesInvoice(ErpRecord $salesRecord, SalesInstallmentPlan $plan, ?User $actor): void
    {
        $links = $salesRecord->outgoingLinks()
            ->with('targetRecord.payments')
            ->where('relation_type', 'sales_ar_invoice')
            ->get();

        foreach ($links as $link) {
            $link->update([
                'status' => 'inactive',
                'metadata' => array_merge($link->metadata ?? [], [
                    'inactive_reason' => 'Digantikan jadwal cicilan penjualan.',
                    'sales_installment_plan_id' => $plan->id,
                    'inactive_at' => now()->toDateTimeString(),
                ]),
            ]);

            $financeRecord = $link->targetRecord;

            if (! $financeRecord || $financeRecord->payments->contains(fn ($payment) => $payment->status === 'paid')) {
                continue;
            }

            $financeRecord->forceFill([
                'amount' => 0,
                'status' => 'Posted',
                'notes' => trim(($financeRecord->notes ?: '').' Invoice penuh dinonaktifkan karena sales memakai jadwal cicilan '.$plan->id.'.'),
                'extra' => array_merge($financeRecord->extra ?? [], [
                    'superseded_by_installment_plan_id' => $plan->id,
                    'payment_required' => false,
                    'payment_confirmation_status' => 'superseded_by_installment',
                    'finance_verification_status' => 'superseded',
                    'superseded_at' => now()->toDateTimeString(),
                ]),
            ])->save();

            app(ErpModuleIntegrationService::class)->syncRecord($financeRecord->fresh(), $actor);
        }
    }

    private function createFinanceInvoice(ErpRecord $salesRecord, SalesInstallmentPlan $plan, SalesInstallment $installment, ?User $actor): ErpRecord
    {
        $financeRecord = ErpRecord::create([
            'module' => 'finance',
            'code' => $this->generateCode('finance'),
            'category' => 'AR Invoice',
            'title' => '1102 Piutang Cicilan',
            'party' => $salesRecord->title,
            'amount' => $installment->amount,
            'status' => 'Open',
            'due_date' => $installment->due_date,
            'notes' => 'AR cicilan '.$installment->installment_no.'/'.$plan->installment_count.' '.$installment->invoice_no.' VA '.$installment->virtual_account.' otomatis dari sales order '.$salesRecord->code.'.',
            'extra' => [
                'integration_source_record_id' => $salesRecord->id,
                'integration_source_code' => $salesRecord->code,
                'integration_source_module' => 'sales',
                'integration_relation_type' => 'sales_installment_ar_invoice',
                'sales_record_id' => $salesRecord->id,
                'sales_record_code' => $salesRecord->code,
                'sales_installment_plan_id' => $plan->id,
                'sales_installment_id' => $installment->id,
                'installment_no' => $installment->installment_no,
                'installment_count' => $plan->installment_count,
                'invoice_no' => $installment->invoice_no,
                'virtual_account' => $installment->virtual_account,
                'net_amount' => (float) $plan->net_amount,
                'tax_rate' => (float) $plan->tax_rate,
                'tax_amount' => (float) $plan->tax_amount,
                'gross_amount' => (float) $plan->total_amount,
                'payment_mode' => 'installment',
                'payment_confirmation_status' => 'waiting_payment',
                'finance_verification_status' => 'waiting_verification',
            ],
            'created_by' => $actor?->id ?: $salesRecord->created_by,
        ]);

        ErpRecordLink::updateOrCreate(
            [
                'source_record_id' => $salesRecord->id,
                'target_record_id' => $financeRecord->id,
                'relation_type' => 'sales_installment_ar_invoice',
            ],
            [
                'status' => 'active',
                'metadata' => [
                    'source_module' => 'sales',
                    'source_code' => $salesRecord->code,
                    'target_module' => 'finance',
                    'target_code' => $financeRecord->code,
                    'sales_installment_plan_id' => $plan->id,
                    'sales_installment_id' => $installment->id,
                    'installment_no' => $installment->installment_no,
                    'synced_at' => now()->toDateTimeString(),
                ],
                'created_by' => $actor?->id,
            ]
        );

        return $financeRecord;
    }

    private function invoiceNo(ErpRecord $salesRecord, int $installmentNo): string
    {
        return 'INV-'.$salesRecord->code.'-'.str_pad((string) $installmentNo, 2, '0', STR_PAD_LEFT);
    }

    private function virtualAccount(ErpRecord $salesRecord, int $installmentNo): string
    {
        return 'VA'.str_pad((string) $salesRecord->id, 8, '0', STR_PAD_LEFT).str_pad((string) $installmentNo, 3, '0', STR_PAD_LEFT);
    }

    private function generateCode(string $module): string
    {
        $config = ErpModules::get($module);
        $prefix = $config['prefix'] ?? str($module)->upper()->substr(0, 3)->toString();
        $period = now()->format('ym');
        $sequence = ErpRecord::where('module', $module)->count() + 1;

        do {
            $code = $prefix.'-'.$period.'-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            $sequence++;
        } while (ErpRecord::where('code', $code)->exists());

        return $code;
    }
}
