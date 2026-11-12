<?php

namespace App\Services;

use App\Models\EmployeeReimbursement;
use App\Models\ErpRecord;
use App\Models\ErpRecordLink;
use App\Models\User;
use App\Support\ErpModules;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class ErpModuleIntegrationService
{
    public function syncAll(?User $actor = null): Collection
    {
        if (! $this->canSync()) {
            return collect();
        }

        return ErpRecord::orderBy('id')
            ->get()
            ->flatMap(fn (ErpRecord $record) => $this->syncRecord($record, $actor));
    }

    public function syncRecord(ErpRecord $record, ?User $actor = null, int $depth = 0): Collection
    {
        if (! $record->exists || ! $this->canSync() || $depth > 3) {
            return collect();
        }

        return collect($this->targetsFor($record))
            ->map(function (array $target) use ($record, $actor, $depth) {
                $targetRecord = $this->ensureLinkedRecord($record, $target, $actor);

                if ($targetRecord && $depth < 3) {
                    $this->syncRecord($targetRecord, $actor, $depth + 1);
                }

                return $targetRecord;
            })
            ->filter()
            ->values();
    }

    public static function relationLabel(string $type): string
    {
        return [
            'crm_sales_order' => 'CRM -> Sales',
            'marketing_sales_order' => 'Marketing -> Sales',
            'sales_ar_invoice' => 'Sales -> AR',
            'sales_installment_ar_invoice' => 'Sales Cicilan -> AR',
            'sales_revenue_journal' => 'Sales -> Journal',
            'sales_shipping_charge_ar' => 'Pengiriman -> AR Ongkir',
            'sales_courier_cost_ap' => 'Pengiriman -> AP Kurir',
            'procurement_ap_bill' => 'Procurement -> AP',
            'procurement_inventory_receipt' => 'Procurement -> Stock',
            'procurement_asset_receipt' => 'Procurement -> Asset',
            'finance_ledger_journal' => 'Finance -> Ledger',
            'advanced_accounting_journal' => 'ERP -> Advanced Ledger',
            'payment_cash_journal' => 'Payment -> Cash Journal',
            'manufacturing_finished_goods' => 'Produksi -> Stock',
            'project_milestone_ar' => 'Project -> AR',
            'hris_payroll_payable' => 'HRIS -> Finance Payable',
            'asset_maintenance_procurement' => 'Asset -> Procurement',
            'asset_maintenance_payable' => 'Asset -> AP',
        ][$type] ?? str($type)->replace('_', ' ')->headline()->toString();
    }

    private function canSync(): bool
    {
        return Schema::hasTable('erp_record_links') && Schema::hasTable('erp_records');
    }

    private function targetsFor(ErpRecord $record): array
    {
        return match ($record->module) {
            'crm' => $this->crmTargets($record),
            'marketing' => $this->marketingTargets($record),
            'sales' => $this->salesTargets($record),
            'procurement' => $this->procurementTargets($record),
            'finance' => $this->financeTargets($record),
            'manufacturing' => $this->manufacturingTargets($record),
            'projects' => $this->projectTargets($record),
            'hris' => $this->hrisTargets($record),
            'assets' => $this->assetTargets($record),
            default => [],
        };
    }

    private function crmTargets(ErpRecord $record): array
    {
        if ($record->status !== 'Won') {
            return [];
        }

        return [[
            'module' => 'sales',
            'relation_type' => 'crm_sales_order',
            'payload' => [
                'title' => $record->party ?: $record->title,
                'party' => 'B2B',
                'category' => $record->title,
                'amount' => $record->amount,
                'status' => 'Confirmed',
                'owner' => $record->owner,
                'due_date' => $record->due_date,
                'notes' => 'Sales order otomatis dari opportunity '.$record->code.'.',
            ],
        ]];
    }

    private function marketingTargets(ErpRecord $record): array
    {
        if (! in_array($record->status, ['Qualified', 'Converted', 'Closed'], true)) {
            return [];
        }

        return [[
            'module' => 'sales',
            'relation_type' => 'marketing_sales_order',
            'payload' => [
                'title' => $record->title,
                'party' => in_array($record->category, ['Telemarketing', 'Event'], true) ? $record->category : 'B2B',
                'category' => $record->party,
                'amount' => $record->amount,
                'status' => $record->status === 'Closed' ? 'Paid' : 'Confirmed',
                'owner' => $record->owner,
                'due_date' => $record->due_date,
                'notes' => 'Sales order otomatis dari marketing '.$record->code.'.',
                'extra' => [
                    'marketing_record_id' => $record->id,
                    'marketing_source_code' => $record->code,
                    'marketing_activity' => $record->category,
                    'marketing_branch' => $record->party,
                ],
            ],
        ]];
    }

    private function salesTargets(ErpRecord $record): array
    {
        $targets = [];

        $usesInstallmentPlan = ! empty($record->extra['installment_plan_id'])
            || filter_var($record->extra['installment_enabled'] ?? false, FILTER_VALIDATE_BOOL);

        if (! $usesInstallmentPlan && in_array($record->status, ['Confirmed', 'Invoiced', 'Paid'], true)) {
            $hasProductTax = array_key_exists('tax_amount', $record->extra ?? []);
            $taxRate = $hasProductTax && (float) $record->amount > 0
                ? round((float) $record->extra['tax_amount'] / (float) $record->amount, 4)
                : 0.11;
            $taxAmount = $hasProductTax
                ? round((float) $record->extra['tax_amount'], 2)
                : round(((float) $record->amount) * $taxRate, 2);
            $grossAmount = round(((float) $record->amount) + $taxAmount, 2);

            $targets[] = [
                'module' => 'finance',
                'relation_type' => 'sales_ar_invoice',
                'payload' => [
                    'category' => 'AR Invoice',
                    'title' => '1102 Piutang Usaha',
                    'party' => $record->title,
                    'amount' => $grossAmount,
                    'status' => $record->status === 'Paid' ? 'Paid' : 'Open',
                    'due_date' => $record->due_date,
                    'notes' => 'AR invoice otomatis dari sales order '.$record->code.' termasuk PPN 11% dan VA pembayaran.',
                    'extra' => [
                        'sales_record_id' => $record->id,
                        'invoice_no' => 'INV-'.$record->code,
                        'virtual_account' => 'VA'.str_pad((string) $record->id, 10, '0', STR_PAD_LEFT),
                        'tax_rate' => $taxRate,
                        'tax_amount' => $taxAmount,
                        'net_amount' => (float) $record->amount,
                        'gross_amount' => $grossAmount,
                        'product_lines' => $record->extra['product_lines'] ?? [],
                        'product_source' => $record->extra['product_source'] ?? null,
                        'payment_confirmation_status' => $record->status === 'Paid' ? 'confirmed' : 'waiting_payment',
                        'finance_verification_status' => $record->status === 'Paid' ? 'verified' : 'waiting_verification',
                    ],
                ],
            ];
        }

        if (in_array($record->status, ['Invoiced', 'Paid'], true)) {
            $targets[] = [
                'module' => 'accounting',
                'relation_type' => 'sales_revenue_journal',
                'payload' => [
                    'category' => 'Journal Entry',
                    'title' => 'Jurnal Penjualan '.$record->code,
                    'party' => $record->title,
                    'amount' => $record->amount,
                    'status' => $record->status === 'Paid' ? 'Posted' : 'Reviewed',
                    'owner' => $record->owner,
                    'due_date' => $record->due_date ?: now()->toDateString(),
                    'notes' => 'Jurnal penjualan otomatis dari '.$record->code.'.',
                    'extra' => [
                        'product_lines' => $record->extra['product_lines'] ?? [],
                        'product_source' => $record->extra['product_source'] ?? null,
                    ],
                ],
            ];
        }

        return $targets;
    }

    private function procurementTargets(ErpRecord $record): array
    {
        $targets = [];

        if (in_array($record->status, ['Ordered', 'Received', 'Vendor Bill', 'Closed'], true)) {
            $targets[] = [
                'module' => 'finance',
                'relation_type' => 'procurement_ap_bill',
                'payload' => [
                    'category' => 'AP Bill',
                    'title' => '2101 Utang Vendor',
                    'party' => $record->title,
                    'amount' => $record->amount,
                    'status' => $record->status === 'Closed' ? 'Paid' : ($record->status === 'Ordered' ? 'Open' : 'Approved'),
                    'due_date' => $record->due_date,
                    'notes' => 'AP bill otomatis dari procurement '.$record->code.'.',
                ],
            ];
        }

        if (in_array($record->status, ['Received', 'Vendor Bill', 'Closed'], true)) {
            if ($record->category === 'Asset') {
                $targets[] = [
                    'module' => 'assets',
                    'relation_type' => 'procurement_asset_receipt',
                    'payload' => [
                        'title' => $record->title,
                        'party' => 'Jakarta',
                        'owner' => $record->owner,
                        'amount' => $record->amount,
                        'category' => 'Good',
                        'status' => 'In Use',
                        'due_date' => $record->due_date,
                        'notes' => 'Aset otomatis dari penerimaan procurement '.$record->code.'.',
                    ],
                ];
            } elseif (in_array($record->category, ['Raw Material', 'Spare Part', 'IT', 'Office'], true)) {
                $targets[] = [
                    'module' => 'inventory',
                    'relation_type' => 'procurement_inventory_receipt',
                    'payload' => [
                        'title' => $record->title,
                        'party' => 'Jakarta',
                        'category' => $this->inventoryCategory($record->category),
                        'quantity' => $record->quantity ?: 1,
                        'min_quantity' => $record->min_quantity ?: 1,
                        'amount' => $record->amount,
                        'status' => 'Available',
                        'notes' => 'Stok otomatis dari penerimaan procurement '.$record->code.'.',
                    ],
                ];
            }
        }

        return $targets;
    }

    private function financeTargets(ErpRecord $record): array
    {
        if (! in_array($record->category, ['AR Invoice', 'AP Bill', 'Journal', 'Budget'], true)
            || ! in_array($record->status, ['Open', 'Approved', 'Posted', 'Paid', 'Overdue'], true)) {
            return [];
        }

        return [[
            'module' => 'accounting',
            'relation_type' => 'finance_ledger_journal',
            'payload' => [
                'category' => $record->category === 'Budget' ? 'Accrual' : 'Journal Entry',
                'title' => $this->financeJournalTitle($record),
                'party' => $record->party ?: $record->title,
                'amount' => $record->amount,
                'status' => $this->accountingStatusForFinance($record),
                'due_date' => $record->due_date ?: now()->toDateString(),
                'notes' => 'Ledger otomatis dari finance '.$record->code.' status '.$record->status.'.',
                'extra' => array_merge($this->employeeContextExtra($record), [
                    'finance_record_id' => $record->id,
                    'finance_record_code' => $record->code,
                    'finance_category' => $record->category,
                    'finance_status' => $record->status,
                    'payment_required' => in_array($record->category, ['AR Invoice', 'AP Bill'], true),
                ]),
            ],
        ]];
    }

    private function manufacturingTargets(ErpRecord $record): array
    {
        if ($record->status !== 'Completed') {
            return [];
        }

        return [[
            'module' => 'inventory',
            'relation_type' => 'manufacturing_finished_goods',
            'payload' => [
                'title' => $record->title,
                'party' => $record->party ?: 'Jakarta',
                'category' => 'Finished Goods',
                'quantity' => $record->quantity ?: 1,
                'min_quantity' => 1,
                'amount' => $record->amount,
                'status' => 'Available',
                'notes' => 'Finished goods otomatis dari work order '.$record->code.'.',
                'extra' => [
                    'product_id' => $record->extra['product_id'] ?? null,
                    'product_sku' => $record->extra['product_sku'] ?? null,
                    'product_uom' => $record->extra['product_uom'] ?? null,
                    'bom_materials' => $record->extra['bom_materials'] ?? [],
                    'material_cost' => $record->extra['material_cost'] ?? 0,
                ],
            ],
        ]];
    }

    private function projectTargets(ErpRecord $record): array
    {
        if (strtolower(trim((string) $record->party)) === 'internal'
            || ! in_array($record->status, ['On Track', 'At Risk', 'Delayed', 'Completed'], true)) {
            return [];
        }

        $progress = $record->status === 'Completed' ? 100 : max((int) $record->progress, 1);
        $amount = round(((float) $record->amount * $progress) / 100, 2);

        return [[
            'module' => 'finance',
            'relation_type' => 'project_milestone_ar',
            'payload' => [
                'category' => 'AR Invoice',
                'title' => '1102 Piutang Usaha',
                'party' => $record->party,
                'amount' => $amount,
                'status' => 'Open',
                'due_date' => $record->due_date,
                'notes' => 'AR milestone '.$progress.'% otomatis dari project '.$record->code.'.',
            ],
        ]];
    }

    private function hrisTargets(ErpRecord $record): array
    {
        if (! in_array($record->category, ['Payroll Event', 'Overtime', 'Reimbursement', 'Training', 'Business Trip'], true)
            || ! in_array($record->status, ['Approved', 'Processed'], true)) {
            return [];
        }

        if (($record->extra['source_master'] ?? null) === 'employee_profiles_summary'
            || empty($record->extra['employee_profile_id'])) {
            return [];
        }

        if ($record->category === 'Reimbursement' && $this->reimbursementUsesPayroll($record)) {
            return [];
        }

        if ($record->category === 'Overtime' && ($record->extra['payment_mode'] ?? 'payroll') === 'payroll') {
            return [];
        }

        if ($record->category === 'Training' && ! $this->trainingPaymentReady($record)) {
            return [];
        }

        if ($record->category === 'Business Trip' && ! $this->businessTripPaymentReady($record)) {
            return [];
        }

        $employeeName = $record->extra['employee_name'] ?? $record->title;
        $trainingProvider = trim((string) ($record->extra['training_provider'] ?? ''));
        $businessTripPayee = trim((string) ($record->extra['business_trip_payee_name'] ?? ''));
        $payee = match (true) {
            $record->category === 'Training' && $trainingProvider !== '' => $trainingProvider,
            $record->category === 'Business Trip' && $businessTripPayee !== '' => $businessTripPayee,
            default => $employeeName,
        };
        $payableTitle = match ($record->category) {
            'Reimbursement' => '2101 Utang Reimburse',
            'Overtime' => '2101 Utang Lembur',
            'Training' => '2101 Utang Pelatihan',
            'Business Trip' => '2101 Utang Perjalanan Dinas',
            default => '2101 Utang Payroll',
        };
        $dueDate = match ($record->category) {
            'Training' => $record->extra['training_payment_due_date'] ?? $record->due_date,
            'Business Trip' => $record->extra['business_trip_payment_due_date'] ?? $record->due_date,
            default => $record->due_date,
        };

        return [[
            'module' => 'finance',
            'relation_type' => 'hris_payroll_payable',
            'payload' => [
                'category' => 'AP Bill',
                'title' => $payableTitle,
                'party' => $payee,
                'amount' => $record->amount,
                'status' => $record->status === 'Processed' ? 'Paid' : 'Approved',
                'due_date' => $dueDate,
                'notes' => match ($record->category) {
                    'Training' => 'AP pelatihan otomatis dari HRIS '.$record->code.' untuk '.$employeeName.' ke '.$payee.'.',
                    'Business Trip' => 'AP perjalanan dinas otomatis dari HRIS '.$record->code.' untuk '.$employeeName.' ke '.$payee.'.',
                    default => 'Payable '.$record->category.' otomatis dari HRIS '.$record->code.' untuk '.$employeeName.'.',
                },
                'extra' => array_merge($this->employeeContextExtra($record), $this->trainingPaymentExtra($record), $this->businessTripPaymentExtra($record), [
                    'hris_category' => $record->category,
                    'hris_record_id' => $record->id,
                    'hris_record_code' => $record->code,
                ]),
            ],
        ]];
    }

    private function assetTargets(ErpRecord $record): array
    {
        if ($record->status !== 'Maintenance') {
            return [];
        }

        return [
            [
                'module' => 'procurement',
                'relation_type' => 'asset_maintenance_procurement',
                'payload' => [
                    'title' => 'Maintenance '.$record->title,
                    'party' => 'Purchase Request',
                    'category' => 'Service',
                    'amount' => $record->amount,
                    'status' => 'Purchase Request',
                    'owner' => $record->owner,
                    'due_date' => $record->due_date,
                    'notes' => 'Purchase request maintenance otomatis dari aset '.$record->code.'.',
                ],
            ],
            [
                'module' => 'finance',
                'relation_type' => 'asset_maintenance_payable',
                'payload' => [
                    'category' => 'AP Bill',
                    'title' => '2101 Utang Vendor',
                    'party' => 'Maintenance '.$record->title,
                    'amount' => $record->amount,
                    'status' => 'Open',
                    'due_date' => $record->due_date,
                    'notes' => 'AP maintenance otomatis dari aset '.$record->code.'.',
                ],
            ],
        ];
    }

    private function ensureLinkedRecord(ErpRecord $source, array $target, ?User $actor = null): ?ErpRecord
    {
        $module = $target['module'];
        $relationType = $target['relation_type'];
        $payload = $target['payload'];

        if (! ErpModules::get($module)) {
            return null;
        }

        $link = ErpRecordLink::with('targetRecord')
            ->where('source_record_id', $source->id)
            ->where('relation_type', $relationType)
            ->whereHas('targetRecord', fn ($query) => $query->where('module', $module))
            ->first();

        $record = $link?->targetRecord;
        $payload = $this->normalizedPayload($source, $module, $relationType, $payload, $actor, $record);

        if ($record) {
            $record->fill($payload);
            $record->save();
        } else {
            $record = ErpRecord::create($payload);
        }

        ErpRecordLink::updateOrCreate(
            [
                'source_record_id' => $source->id,
                'target_record_id' => $record->id,
                'relation_type' => $relationType,
            ],
            [
                'status' => 'active',
                'metadata' => [
                    'source_module' => $source->module,
                    'source_code' => $source->code,
                    'target_module' => $module,
                    'target_code' => $record->code,
                    'synced_at' => now()->toDateTimeString(),
                ],
                'created_by' => $link?->created_by ?: $actor?->id,
            ]
        );

        return $record->fresh();
    }

    private function normalizedPayload(ErpRecord $source, string $module, string $relationType, array $payload, ?User $actor, ?ErpRecord $existing): array
    {
        $extra = array_merge($existing?->extra ?? [], $payload['extra'] ?? [], [
            'integration_source_record_id' => $source->id,
            'integration_source_code' => $source->code,
            'integration_source_module' => $source->module,
            'integration_relation_type' => $relationType,
            'integration_synced_at' => now()->toDateTimeString(),
        ]);

        return array_merge([
            'module' => $module,
            'code' => $existing?->code ?: $this->generateCode($module),
            'title' => $payload['title'] ?? $source->title,
            'party' => $payload['party'] ?? null,
            'category' => $payload['category'] ?? null,
            'amount' => $payload['amount'] ?? 0,
            'quantity' => $payload['quantity'] ?? null,
            'min_quantity' => $payload['min_quantity'] ?? null,
            'progress' => $payload['progress'] ?? null,
            'status' => $payload['status'] ?? (ErpModules::get($module)['statuses'][0] ?? 'Draft'),
            'owner' => $payload['owner'] ?? $source->owner,
            'due_date' => $payload['due_date'] ?? $source->due_date,
            'notes' => $payload['notes'] ?? $existing?->notes ?? 'Terintegrasi otomatis dari '.$source->code.'.',
            'extra' => $extra,
            'created_by' => $existing?->created_by ?: $actor?->id ?: $source->created_by,
        ], array_intersect_key($payload, array_flip([
            'module',
            'code',
            'title',
            'party',
            'category',
            'amount',
            'quantity',
            'min_quantity',
            'progress',
            'status',
            'owner',
            'due_date',
            'notes',
            'created_by',
        ])));
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

    private function inventoryCategory(?string $category): string
    {
        return match ($category) {
            'Raw Material' => 'Raw Material',
            'Spare Part', 'IT', 'Office' => 'MRO',
            default => 'Finished Goods',
        };
    }

    private function accountingStatusForFinance(ErpRecord $record): string
    {
        return match ($record->status) {
            'Paid', 'Posted' => 'Posted',
            'Approved' => 'Reviewed',
            default => 'Draft',
        };
    }

    private function financeJournalTitle(ErpRecord $record): string
    {
        return match ($record->category) {
            'AR Invoice' => 'Jurnal Piutang '.$record->code,
            'AP Bill' => 'Jurnal Utang '.$record->code,
            'Budget' => 'Kontrol Budget '.$record->code,
            default => 'Jurnal '.$record->category.' '.$record->code,
        };
    }

    private function employeeContextExtra(ErpRecord $record): array
    {
        return collect($record->extra ?? [])
            ->only([
                'employee_profile_id',
                'employee_no',
                'user_id',
                'employee_name',
                'department',
                'position',
                'job_level',
                'grade',
                'branch',
                'work_location',
                'manager_profile_id',
                'manager_name',
                'employee_payroll_id',
                'employee_request_id',
                'employee_reimbursement_id',
                'employee_attendance_id',
                'training_payment_required',
                'training_provider',
                'training_invoice_no',
                'training_payment_method',
                'training_bank_name',
                'training_bank_account_number',
                'training_bank_account_name',
                'training_payment_due_date',
                'training_payment_reference',
                'training_payment_amount',
                'training_payment_status',
                'training_paid_at',
                'training_payment_order_id',
                'business_trip_payment_required',
                'business_trip_payee_type',
                'business_trip_payee_name',
                'business_trip_invoice_no',
                'business_trip_payment_method',
                'business_trip_bank_name',
                'business_trip_bank_account_number',
                'business_trip_bank_account_name',
                'business_trip_payment_due_date',
                'business_trip_destination',
                'business_trip_purpose',
                'business_trip_transport_mode',
                'business_trip_payment_reference',
                'business_trip_payment_amount',
                'business_trip_payment_status',
                'business_trip_paid_at',
                'business_trip_payment_order_id',
                'source_master',
            ])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    private function trainingPaymentReady(ErpRecord $record): bool
    {
        if ((float) $record->amount <= 0) {
            return false;
        }

        $extra = $record->extra ?? [];
        $paymentRequired = array_key_exists('training_payment_required', $extra)
            ? filter_var($extra['training_payment_required'], FILTER_VALIDATE_BOOL)
            : true;

        return $paymentRequired
            && trim((string) ($extra['training_provider'] ?? '')) !== ''
            && trim((string) ($extra['training_payment_method'] ?? '')) !== '';
    }

    private function trainingPaymentExtra(ErpRecord $record): array
    {
        if ($record->category !== 'Training') {
            return [];
        }

        return collect($record->extra ?? [])
            ->only([
                'training_payment_required',
                'training_provider',
                'training_invoice_no',
                'training_payment_method',
                'training_bank_name',
                'training_bank_account_number',
                'training_bank_account_name',
                'training_payment_due_date',
                'training_payment_reference',
                'training_payment_notes',
                'training_payment_amount',
                'training_payment_status',
                'training_paid_at',
                'training_payment_order_id',
            ])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    private function businessTripPaymentReady(ErpRecord $record): bool
    {
        if ((float) $record->amount <= 0) {
            return false;
        }

        $extra = $record->extra ?? [];
        $paymentRequired = array_key_exists('business_trip_payment_required', $extra)
            ? filter_var($extra['business_trip_payment_required'], FILTER_VALIDATE_BOOL)
            : true;

        return $paymentRequired
            && trim((string) ($extra['business_trip_payee_name'] ?? $extra['employee_name'] ?? '')) !== ''
            && trim((string) ($extra['business_trip_payment_method'] ?? '')) !== '';
    }

    private function businessTripPaymentExtra(ErpRecord $record): array
    {
        if ($record->category !== 'Business Trip') {
            return [];
        }

        return collect($record->extra ?? [])
            ->only([
                'business_trip_payment_required',
                'business_trip_payee_type',
                'business_trip_payee_name',
                'business_trip_invoice_no',
                'business_trip_payment_method',
                'business_trip_bank_name',
                'business_trip_bank_account_number',
                'business_trip_bank_account_name',
                'business_trip_payment_due_date',
                'business_trip_destination',
                'business_trip_purpose',
                'business_trip_transport_mode',
                'business_trip_payment_reference',
                'business_trip_payment_notes',
                'business_trip_payment_amount',
                'business_trip_payment_status',
                'business_trip_paid_at',
                'business_trip_payment_order_id',
            ])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    private function reimbursementUsesPayroll(ErpRecord $record): bool
    {
        if (($record->extra['payment_mode'] ?? null) === 'payroll' || ! empty($record->extra['employee_payroll_id'])) {
            return true;
        }

        if (empty($record->extra['employee_reimbursement_id']) || ! Schema::hasTable('employee_reimbursements')) {
            return false;
        }

        $reimbursement = EmployeeReimbursement::find($record->extra['employee_reimbursement_id']);

        return $reimbursement && ($reimbursement->payment_method ?: 'payroll') === 'payroll';
    }
}
