<?php

namespace App\Services;

use App\Models\EmployeeRequest;
use App\Models\ErpRecord;
use App\Models\User;
use Illuminate\Support\Collection;

class EmployeeRequestIntegrationService
{
    public function syncAll(?User $actor = null): Collection
    {
        return EmployeeRequest::with(['profile.managerProfile.user', 'user'])
            ->orderBy('id')
            ->get()
            ->map(fn (EmployeeRequest $request) => $this->syncToHris($request, $actor))
            ->filter()
            ->values();
    }

    public function syncToHris(EmployeeRequest $employeeRequest, ?User $actor = null): ErpRecord
    {
        $employeeRequest->loadMissing(['profile.managerProfile.user', 'user']);
        $profile = $employeeRequest->profile;
        $payload = $employeeRequest->payload ?? [];
        $quantity = $this->quantityFor($employeeRequest);
        $category = $this->categoryFor($employeeRequest->type);
        $existingRecord = ErpRecord::where('module', 'hris')
            ->where('code', 'HRS-REQ-'.$employeeRequest->id)
            ->first();

        $record = ErpRecord::updateOrCreate(
            ['module' => 'hris', 'code' => 'HRS-REQ-'.$employeeRequest->id],
            [
                'title' => $this->titleForHris($employeeRequest),
                'category' => $category,
                'party' => $profile?->department,
                'quantity' => $quantity,
                'amount' => $this->amountFor($employeeRequest, $quantity),
                'progress' => $employeeRequest->status === 'approved' ? 100 : ($employeeRequest->status === 'rejected' ? 0 : 40),
                'status' => $this->statusFor($employeeRequest->status),
                'owner' => $profile?->managerProfile?->user?->name ?: ($profile?->manager_name ?: 'HR'),
                'due_date' => $employeeRequest->start_date,
                'notes' => $employeeRequest->description,
                'extra' => array_merge($this->preservedPaymentExtra($existingRecord), [
                    'employee_request_id' => $employeeRequest->id,
                    'employee_profile_id' => $employeeRequest->employee_profile_id,
                    'employee_no' => $profile?->employee_no,
                    'user_id' => $employeeRequest->user_id,
                    'employee_name' => $employeeRequest->user?->name,
                    'department' => $profile?->department,
                    'position' => $profile?->position,
                    'manager_profile_id' => $profile?->manager_profile_id,
                    'manager_name' => $profile?->managerProfile?->user?->name ?: $profile?->manager_name,
                    'request_type' => $employeeRequest->type,
                    'request_type_label' => $this->labelFor($employeeRequest->type),
                    'start_date' => $employeeRequest->start_date?->toDateString(),
                    'end_date' => $employeeRequest->end_date?->toDateString(),
                    'start_time' => $payload['start_time'] ?? $payload['clock_in_at'] ?? null,
                    'end_time' => $payload['end_time'] ?? $payload['clock_out_at'] ?? null,
                    'total_hours' => $payload['total_hours'] ?? ($employeeRequest->type === 'overtime' ? $quantity : null),
                    'total_days' => $employeeRequest->type === 'overtime' ? null : $employeeRequest->total_days,
                    'travel_destination' => $payload['travel_destination'] ?? null,
                    'travel_purpose' => $payload['travel_purpose'] ?? null,
                    'transport_mode' => $payload['transport_mode'] ?? null,
                    'estimated_amount' => $payload['estimated_amount'] ?? null,
                    'payment_mode' => match ($employeeRequest->type) {
                        'overtime' => 'payroll',
                        'training' => 'separate',
                        'business_trip' => 'separate',
                        default => null,
                    },
                    'payload' => $payload,
                    'source_master' => 'employee_profiles',
                    'source' => 'employee_request',
                ]),
                'created_by' => $actor?->id ?: $employeeRequest->user_id,
            ]
        );

        return app(HrisEmployeeIntegrationService::class)
            ->applyEmployeeContext($record->fresh(), $actor, $profile);
    }

    public function categoryFor(string $type): string
    {
        return match ($type) {
            'manual_attendance' => 'Manual Attendance',
            'leave' => 'Leave Request',
            'permit' => 'Permit',
            'sick' => 'Sick Leave',
            'overtime' => 'Overtime',
            'training' => 'Training',
            'business_trip' => 'Business Trip',
            'data_change' => 'Data Change',
            'information' => 'Information',
            default => str($type)->replace('_', ' ')->headline()->toString(),
        };
    }

    public function prunePayrollModeOvertimePayables(): int
    {
        return ErpRecord::where('module', 'hris')
            ->where('category', 'Overtime')
            ->get()
            ->filter(fn (ErpRecord $record) => ($record->extra['payment_mode'] ?? 'payroll') === 'payroll'
                || ($record->extra['source'] ?? null) === 'employee_request')
            ->each(fn (ErpRecord $record) => $this->removeUnpaidFinanceLinks($record))
            ->count();
    }

    private function removeUnpaidFinanceLinks(ErpRecord $record): void
    {
        $record->outgoingLinks()
            ->with(['targetRecord.payments', 'targetRecord.outgoingLinks.targetRecord'])
            ->where('relation_type', 'hris_payroll_payable')
            ->get()
            ->each(function ($link) use ($record) {
                $financeRecord = $link->targetRecord;

                if (! $financeRecord) {
                    $link->delete();

                    return;
                }

                if ($financeRecord->payments()->where('status', 'paid')->exists()) {
                    $financeRecord->forceFill([
                        'notes' => trim(($financeRecord->notes ?: '').' Lembur '.$record->code.' sekarang ditandai masuk payroll; histori payment finance dipertahankan.'),
                        'extra' => array_merge($financeRecord->extra ?? [], [
                            'overtime_payroll_replaced_by_record_id' => $record->id,
                            'overtime_payroll_replaced_by_code' => $record->code,
                            'overtime_payroll_replaced_at' => now()->toDateTimeString(),
                        ]),
                    ])->save();

                    return;
                }

                $link->delete();

                foreach ($financeRecord->outgoingLinks as $childLink) {
                    $childRecord = $childLink->targetRecord;

                    if ($childRecord
                        && (int) ($childRecord->extra['integration_source_record_id'] ?? 0) === (int) $financeRecord->id) {
                        $childRecord->delete();
                    }

                    $childLink->delete();
                }

                $financeRecord->delete();
            });
    }

    private function statusFor(string $status): string
    {
        return match ($status) {
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            default => 'Pending Approval',
        };
    }

    private function titleForHris(EmployeeRequest $employeeRequest): string
    {
        $employeeName = trim((string) ($employeeRequest->user?->name ?? ''));
        $title = trim((string) $employeeRequest->title);

        if ($employeeName === '' || str_contains(strtolower($title), strtolower($employeeName))) {
            return $title;
        }

        return $employeeName.' - '.$title;
    }

    private function quantityFor(EmployeeRequest $employeeRequest): float
    {
        $payload = $employeeRequest->payload ?? [];

        if (in_array($employeeRequest->type, ['overtime', 'manual_attendance'], true)) {
            return (float) ($payload['total_hours'] ?? $employeeRequest->total_days ?? 0);
        }

        return (float) ($employeeRequest->total_days ?? 1);
    }

    private function amountFor(EmployeeRequest $employeeRequest, float $quantity): float
    {
        if ($employeeRequest->type === 'business_trip') {
            return (float) ($employeeRequest->payload['estimated_amount'] ?? 0);
        }

        if ($employeeRequest->type !== 'overtime') {
            return 0;
        }

        $baseSalary = (float) ($employeeRequest->profile?->base_salary ?? 0);

        return round(($baseSalary / 173) * 1.5 * $quantity, 2);
    }

    private function preservedPaymentExtra(?ErpRecord $record): array
    {
        return collect($record?->extra ?? [])
            ->only([
                'payment_required',
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
                'finance_payment_record_id',
                'finance_payment_order_id',
                'finance_payment_status',
                'finance_paid_at',
            ])
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
    }

    private function labelFor(string $type): string
    {
        return match ($type) {
            'leave' => 'Cuti',
            'permit' => 'Izin',
            'sick' => 'Sakit',
            'overtime' => 'Lembur',
            'manual_attendance' => 'Presensi Manual',
            'training' => 'Pelatihan',
            'business_trip' => 'Perjalanan Dinas',
            'data_change' => 'Perubahan Data',
            'information' => 'Informasi',
            default => str($type)->replace('_', ' ')->headline()->toString(),
        };
    }
}
