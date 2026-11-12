<?php

namespace App\Services;

use App\Models\EmployeeAttendance;
use App\Models\EmployeePayroll;
use App\Models\EmployeeProfile;
use App\Models\EmployeeReimbursement;
use App\Models\EmployeeRequest;
use App\Models\ErpRecord;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class HrisEmployeeIntegrationService
{
    public function syncExistingRecords(?User $actor = null): Collection
    {
        if (! Schema::hasTable('erp_records') || ! Schema::hasTable('employee_profiles')) {
            return collect();
        }

        return ErpRecord::where('module', 'hris')
            ->orderBy('id')
            ->get()
            ->map(fn (ErpRecord $record) => $this->applyEmployeeContext($record, $actor))
            ->filter()
            ->values();
    }

    public function applyEmployeeContext(ErpRecord $record, ?User $actor = null, ?EmployeeProfile $profile = null): ErpRecord
    {
        if ($record->module !== 'hris') {
            return $record;
        }

        $profile = $profile ?: $this->profileForRecord($record);

        if (! $profile) {
            $summaryRecord = $this->applyPayrollSummaryContext($record);

            if ($summaryRecord) {
                return $summaryRecord;
            }

            $record->forceFill([
                'extra' => array_merge($record->extra ?? [], [
                    'source_master' => in_array($record->category, ['Recruitment', 'Interview', 'Test', 'Onboarding'], true)
                        ? 'recruitment_candidates'
                        : 'employee_profiles_required',
                    'hris_employee_synced_at' => now()->toDateTimeString(),
                ]),
            ])->save();

            return $record->fresh();
        }

        $profile->loadMissing(['user', 'managerProfile.user']);
        $payload = $this->profilePayload($profile);
        $record->forceFill([
            'title' => $this->titleForRecord($record, $profile),
            'party' => $record->party ?: $profile->department,
            'owner' => $record->owner ?: ($profile->managerProfile?->user?->name ?: $profile->manager_name ?: 'HR'),
            'extra' => array_merge($record->extra ?? [], $payload, [
                'source_master' => 'employee_profiles',
                'hris_employee_synced_at' => now()->toDateTimeString(),
            ]),
        ])->save();

        $this->syncOperationalTables($record->fresh(), $profile, $actor);

        return $record->fresh();
    }

    public function profileForRecord(ErpRecord $record): ?EmployeeProfile
    {
        if (! Schema::hasTable('employee_profiles')) {
            return null;
        }

        $extra = $record->extra ?? [];
        $query = EmployeeProfile::with(['user', 'managerProfile.user']);

        if (! empty($extra['employee_profile_id'])) {
            $profile = (clone $query)->find($extra['employee_profile_id']);

            if ($profile) {
                return $profile;
            }
        }

        if (! empty($extra['user_id'])) {
            $profile = (clone $query)->where('user_id', $extra['user_id'])->first();

            if ($profile) {
                return $profile;
            }
        }

        if (! empty($extra['employee_no'])) {
            $profile = (clone $query)->where('employee_no', $extra['employee_no'])->first();

            if ($profile) {
                return $profile;
            }
        }

        $haystack = Str::lower(trim(implode(' ', array_filter([
            $record->title,
            $record->notes,
            $record->code,
            $extra['employee_name'] ?? null,
            $extra['employee_no'] ?? null,
        ]))));

        if ($haystack === '') {
            return null;
        }

        return $query->get()->first(function (EmployeeProfile $profile) use ($haystack) {
            $needles = collect([
                $profile->employee_no,
                $profile->user?->name,
                $profile->bank_account_name,
            ])
                ->filter(fn ($value) => filled($value) && Str::length(trim((string) $value)) >= 3)
                ->map(fn ($value) => Str::lower(trim((string) $value)));

            return $needles->contains(fn ($needle) => Str::contains($haystack, $needle));
        });
    }

    public function profilePayload(EmployeeProfile $profile): array
    {
        $profile->loadMissing(['user', 'managerProfile.user']);

        return [
            'employee_profile_id' => $profile->id,
            'employee_no' => $profile->employee_no,
            'user_id' => $profile->user_id,
            'employee_name' => $profile->user?->name ?: $profile->bank_account_name ?: $profile->employee_no,
            'department' => $profile->department,
            'position' => $profile->position,
            'job_level' => $profile->job_level,
            'grade' => $profile->grade,
            'branch' => $profile->branch,
            'work_location' => $profile->work_location,
            'manager_profile_id' => $profile->manager_profile_id,
            'manager_name' => $profile->managerProfile?->user?->name ?: $profile->manager_name,
            'employment_status' => $profile->employment_status,
        ];
    }

    private function titleForRecord(ErpRecord $record, EmployeeProfile $profile): string
    {
        $employeeName = $profile->user?->name ?: $profile->bank_account_name ?: $profile->employee_no;

        if (Str::contains(Str::lower((string) $record->title), Str::lower($employeeName))) {
            return $record->title;
        }

        if (in_array($record->category, ['Recruitment', 'Interview', 'Test', 'Onboarding'], true)) {
            return $record->title ?: $employeeName;
        }

        $suffix = trim(Str::after((string) $record->title, ' - '));
        $suffix = $suffix !== '' && $suffix !== $record->title
            ? $suffix
            : ($record->category ?: 'HRIS');

        return $employeeName.' - '.$suffix;
    }

    private function syncOperationalTables(ErpRecord $record, EmployeeProfile $profile, ?User $actor): void
    {
        if (! $profile->user_id) {
            return;
        }

        if (in_array($record->category, ['Attendance', 'Manual Attendance'], true)) {
            $this->syncAttendance($record, $profile, $actor);
        }

        if (in_array($record->category, ['Manual Attendance', 'Leave Request', 'Permit', 'Sick Leave', 'Overtime', 'Training', 'Business Trip', 'Data Change', 'Information'], true)) {
            $this->syncRequest($record, $profile, $actor);
        }

        if ($record->category === 'Reimbursement') {
            $this->syncReimbursement($record, $profile, $actor);
        }
    }

    private function applyPayrollSummaryContext(ErpRecord $record): ?ErpRecord
    {
        if ($record->category !== 'Payroll Event'
            || ! Str::contains(Str::lower((string) $record->title), 'payroll')
            || ! Schema::hasTable('employee_payrolls')) {
            return null;
        }

        $period = $record->due_date?->copy()->startOfMonth()->toDateString();
        $payrolls = EmployeePayroll::with(['profile.user', 'user'])
            ->when($period, fn ($query) => $query->whereDate('period_month', $period))
            ->latest('period_month')
            ->limit(80)
            ->get();

        if ($payrolls->isEmpty()) {
            return null;
        }

        $record->forceFill([
            'extra' => array_merge($record->extra ?? [], [
                'source_master' => 'employee_profiles_summary',
                'payroll_summary_profile_ids' => $payrolls->pluck('employee_profile_id')->filter()->values()->all(),
                'payroll_summary_employee_names' => $payrolls
                    ->map(fn (EmployeePayroll $payroll) => $payroll->user?->name ?: $payroll->profile?->employee_no)
                    ->filter()
                    ->values()
                    ->all(),
                'payroll_summary_period' => $period,
                'hris_employee_synced_at' => now()->toDateTimeString(),
            ]),
        ])->save();

        return $record->fresh();
    }

    private function syncAttendance(ErpRecord $record, EmployeeProfile $profile, ?User $actor): void
    {
        if (! Schema::hasTable('employee_attendances') || ! $record->due_date) {
            return;
        }

        $date = $record->due_date->toDateString();
        $attendance = EmployeeAttendance::updateOrCreate(
            ['user_id' => $profile->user_id, 'attendance_date' => $date],
            [
                'employee_profile_id' => $profile->id,
                'source' => $record->category === 'Manual Attendance' ? 'manual' : 'hris',
                'phone_number' => $profile->phone,
                'status' => $record->category === 'Manual Attendance' ? 'manual_present' : 'present',
                'approval_status' => in_array($record->status, ['Approved', 'Processed'], true) ? 'approved' : 'pending',
                'approved_by' => in_array($record->status, ['Approved', 'Processed'], true) ? $actor?->id : null,
                'approved_at' => in_array($record->status, ['Approved', 'Processed'], true) ? now() : null,
                'notes' => $record->notes,
                'metadata' => [
                    'erp_record_id' => $record->id,
                    'erp_record_code' => $record->code,
                    'source' => 'hris_record',
                ],
            ]
        );

        $this->mergeRecordExtra($record, ['employee_attendance_id' => $attendance->id]);
    }

    private function syncRequest(ErpRecord $record, EmployeeProfile $profile, ?User $actor): void
    {
        if (! Schema::hasTable('employee_requests')) {
            return;
        }

        $type = match ($record->category) {
            'Manual Attendance' => 'manual_attendance',
            'Overtime' => 'overtime',
            'Permit' => 'permit',
            'Sick Leave' => 'sick',
            'Training' => 'training',
            'Business Trip' => 'business_trip',
            'Data Change' => 'data_change',
            'Information' => 'information',
            default => 'leave',
        };
        $status = match ($record->status) {
            'Approved', 'Processed' => 'approved',
            'Rejected' => 'rejected',
            default => 'submitted',
        };
        $startDate = $record->due_date?->toDateString();
        $endDate = $record->extra['end_date'] ?? $startDate;
        $payload = $record->extra['payload'] ?? [];
        $payload = array_merge(is_array($payload) ? $payload : [], [
            'erp_record_id' => $record->id,
            'erp_record_code' => $record->code,
            'amount' => (float) $record->amount,
            'start_time' => $record->extra['start_time'] ?? null,
            'end_time' => $record->extra['end_time'] ?? null,
            'total_hours' => $record->extra['total_hours'] ?? ($type === 'overtime' ? (float) $record->quantity : null),
            'payment_mode' => $record->extra['payment_mode'] ?? null,
            'training_payment_required' => $record->extra['training_payment_required'] ?? null,
            'training_provider' => $record->extra['training_provider'] ?? null,
            'training_invoice_no' => $record->extra['training_invoice_no'] ?? null,
            'training_payment_method' => $record->extra['training_payment_method'] ?? null,
            'training_payment_due_date' => $record->extra['training_payment_due_date'] ?? null,
            'training_payment_status' => $record->extra['training_payment_status'] ?? null,
            'training_paid_at' => $record->extra['training_paid_at'] ?? null,
            'training_payment_order_id' => $record->extra['training_payment_order_id'] ?? null,
            'travel_destination' => $record->extra['travel_destination'] ?? $record->extra['business_trip_destination'] ?? null,
            'travel_purpose' => $record->extra['travel_purpose'] ?? $record->extra['business_trip_purpose'] ?? null,
            'transport_mode' => $record->extra['transport_mode'] ?? $record->extra['business_trip_transport_mode'] ?? null,
            'business_trip_payment_required' => $record->extra['business_trip_payment_required'] ?? null,
            'business_trip_payee_name' => $record->extra['business_trip_payee_name'] ?? null,
            'business_trip_invoice_no' => $record->extra['business_trip_invoice_no'] ?? null,
            'business_trip_payment_method' => $record->extra['business_trip_payment_method'] ?? null,
            'business_trip_payment_due_date' => $record->extra['business_trip_payment_due_date'] ?? null,
            'business_trip_payment_status' => $record->extra['business_trip_payment_status'] ?? null,
            'business_trip_paid_at' => $record->extra['business_trip_paid_at'] ?? null,
            'business_trip_payment_order_id' => $record->extra['business_trip_payment_order_id'] ?? null,
            'source' => 'hris_record',
        ]);

        $employeeRequest = ! empty($record->extra['employee_request_id'])
            ? EmployeeRequest::find($record->extra['employee_request_id'])
            : null;

        $employeeRequest = $employeeRequest ?: (EmployeeRequest::where('user_id', $profile->user_id)
            ->where('type', $type)
            ->where('title', $record->title)
            ->when($startDate, fn ($query) => $query->whereDate('start_date', $startDate))
            ->first() ?: new EmployeeRequest());

        $employeeRequest->fill([
            'employee_profile_id' => $profile->id,
            'user_id' => $profile->user_id,
            'type' => $type,
            'status' => $status,
            'title' => $record->title,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => $record->quantity ?: 1,
            'description' => $record->notes,
            'payload' => array_merge($employeeRequest->payload ?? [], array_filter($payload, fn ($value) => $value !== null && $value !== '')),
            'reviewed_by' => in_array($status, ['approved', 'rejected'], true) ? $actor?->id : null,
            'reviewed_at' => in_array($status, ['approved', 'rejected'], true) ? now() : null,
        ])->save();

        $this->mergeRecordExtra($record, ['employee_request_id' => $employeeRequest->id]);
    }

    private function syncReimbursement(ErpRecord $record, EmployeeProfile $profile, ?User $actor): void
    {
        if (! Schema::hasTable('employee_reimbursements')) {
            return;
        }

        $status = match ($record->status) {
            'Approved' => 'approved',
            'Processed' => 'paid',
            'Rejected' => 'rejected',
            default => 'submitted',
        };
        $expenseDate = $record->due_date?->toDateString();
        $paymentMethod = $record->extra['payment_mode']
            ?? (! empty($record->extra['employee_payroll_id']) ? 'payroll' : ($record->status === 'Processed' ? 'separate' : 'payroll'));

        $reimbursement = ! empty($record->extra['employee_reimbursement_id'])
            ? EmployeeReimbursement::find($record->extra['employee_reimbursement_id'])
            : null;

        $reimbursement = $reimbursement ?: (EmployeeReimbursement::where('user_id', $profile->user_id)
            ->where('title', $record->title)
            ->when($expenseDate, fn ($query) => $query->whereDate('expense_date', $expenseDate))
            ->first() ?: new EmployeeReimbursement());

        $payload = [
            'employee_profile_id' => $profile->id,
            'user_id' => $profile->user_id,
            'category' => 'HRIS',
            'title' => $record->title,
            'amount' => (float) $record->amount,
            'expense_date' => $expenseDate,
            'description' => $record->notes,
            'status' => $status,
            'reviewed_by' => in_array($status, ['approved', 'paid', 'rejected'], true) ? $actor?->id : null,
            'reviewed_at' => in_array($status, ['approved', 'paid', 'rejected'], true) ? now() : null,
            'review_note' => 'Tersinkron dari HRIS '.$record->code.'.',
        ];

        if (Schema::hasColumn('employee_reimbursements', 'payment_method')) {
            $payload['payment_method'] = $paymentMethod;
        }

        if (Schema::hasColumn('employee_reimbursements', 'employee_payroll_id')) {
            $payload['employee_payroll_id'] = $record->extra['employee_payroll_id'] ?? $reimbursement->employee_payroll_id;
        }

        if (Schema::hasColumn('employee_reimbursements', 'paid_at')) {
            $payload['paid_at'] = $status === 'paid' ? ($reimbursement->paid_at ?: now()) : $reimbursement->paid_at;
        }

        $reimbursement->fill($payload)->save();

        $this->mergeRecordExtra($record, [
            'employee_reimbursement_id' => $reimbursement->id,
            'payment_mode' => $paymentMethod,
            'employee_payroll_id' => $reimbursement->employee_payroll_id,
            'paid_at' => $reimbursement->paid_at?->toDateTimeString(),
        ]);
    }

    private function mergeRecordExtra(ErpRecord $record, array $payload): void
    {
        $record->forceFill([
            'extra' => array_merge($record->extra ?? [], $payload),
        ])->save();
    }
}
