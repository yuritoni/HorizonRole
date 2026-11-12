<?php

namespace App\Services;

use App\Models\EmployeeProfile;
use App\Models\ErpRecord;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class EmployeeIntegrationService
{
    public function syncAllHrRecords(?User $actor = null): Collection
    {
        return ErpRecord::where('module', 'hr')
            ->orderBy('code')
            ->get()
            ->map(fn (ErpRecord $record) => $this->syncHrRecord($record, $actor));
    }

    public function syncAllEmployeeProfiles(?User $actor = null): Collection
    {
        return EmployeeProfile::with(['user', 'hrRecord'])
            ->whereHas('user', fn ($query) => $query->where('role', 'employee'))
            ->orderBy('employee_no')
            ->get()
            ->map(function (EmployeeProfile $profile) {
                if ($profile->erp_record_id && $profile->hrRecord?->module === 'hr') {
                    $profile->forceFill(['erp_record_id' => null])->save();
                }

                return $profile->fresh(['user']);
            });
    }

    public function syncHrRecord(ErpRecord $record, ?User $actor = null): EmployeeProfile
    {
        if ($record->module !== 'hr') {
            throw new \InvalidArgumentException('Hanya record modul SDM yang bisa disinkronkan sebagai employee.');
        }

        $extra = $record->extra ?? [];
        $profile = ! empty($extra['employee_profile_id'])
            ? EmployeeProfile::find($extra['employee_profile_id'])
            : null;

        $profile = $profile
            ?: (! empty($extra['employee_no']) ? EmployeeProfile::where('employee_no', $extra['employee_no'])->first() : null)
            ?: EmployeeProfile::where('erp_record_id', $record->id)->first()
            ?: EmployeeProfile::where('employee_no', $record->code)->first();

        $user = $profile?->user ?: $this->employeeUserForRecord($record);
        $accountStatus = $this->accountStatusFromHrStatus($record->status);

        $user->forceFill([
            'name' => $record->title,
            'role' => $user->isAdmin() ? $user->role : 'employee',
            'account_status' => $accountStatus,
            'email_verified_at' => $user->email_verified_at ?: now(),
            'activated_at' => $accountStatus === 'active' ? ($user->activated_at ?: now()) : null,
        ])->save();

        if (! $profile) {
            $profile = new EmployeeProfile([
                'user_id' => $user->id,
                'employee_no' => $record->code,
            ]);
        }

        $settings = array_merge($profile->settings ?? [], [
            'source' => 'sdm',
            'hr_record_id' => $record->id,
            'hr_record_code' => $record->code,
            'attendance_percent' => $record->progress,
            'salary_reference' => (float) $record->amount,
            'default_password' => $profile->exists ? ($profile->settings['default_password'] ?? null) : 'password123',
            'synced_at' => now()->toDateTimeString(),
        ]);

        $profile->fill([
            'erp_record_id' => $record->id,
            'user_id' => $user->id,
            'employee_no' => $extra['employee_no'] ?? $record->code,
            'manager_profile_id' => $extra['manager_profile_id'] ?? $profile->manager_profile_id,
            'department' => $record->party,
            'position' => $record->category ?: 'Employee',
            'job_level' => $extra['job_level'] ?? $profile->job_level,
            'grade' => $extra['grade'] ?? $profile->grade,
            'employment_status' => $this->profileStatusFromHrStatus($record->status),
            'join_date' => $record->due_date,
            'manager_name' => $extra['manager_name'] ?? $record->owner,
            'work_location' => $extra['work_location'] ?? $profile->work_location,
            'branch' => $extra['branch'] ?? $profile->branch,
            'bank_account_name' => $profile->bank_account_name ?: $record->title,
            'base_salary' => (float) ($extra['base_salary'] ?? $record->amount ?? $profile->base_salary ?? 0),
            'fixed_allowance' => (float) ($extra['fixed_allowance'] ?? $profile->fixed_allowance ?? 0),
            'tax_status' => $extra['tax_status'] ?? $profile->tax_status,
            'marketing_target' => (float) ($extra['marketing_target'] ?? $profile->marketing_target ?? 0),
            'commission_rate' => (float) ($extra['commission_rate'] ?? $profile->commission_rate ?? 0),
            'production_kpi_rate' => (float) ($extra['production_kpi_rate'] ?? $profile->production_kpi_rate ?? 0),
            'job_data' => array_merge($profile->job_data ?? [], [
                'hr_status' => $record->status,
                'attendance_percent' => $record->progress,
            ]),
            'settings' => array_filter($settings, fn ($value) => $value !== null),
        ])->save();

        $record->delete();

        return $profile->fresh(['user']);
    }

    public function syncProfileToHrRecord(EmployeeProfile $profile, ?User $actor = null): ?ErpRecord
    {
        if ($profile->erp_record_id && $profile->hrRecord?->module === 'hr') {
            $profile->forceFill(['erp_record_id' => null])->save();
        }

        return null;
    }

    private function employeeUserForRecord(ErpRecord $record): User
    {
        $email = $this->emailForRecord($record);

        return User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $record->title,
                'role' => 'employee',
                'account_status' => $this->accountStatusFromHrStatus($record->status),
                'email_verified_at' => now(),
                'activated_at' => $this->accountStatusFromHrStatus($record->status) === 'active' ? now() : null,
                'password' => Hash::make('password123'),
            ]
        );
    }

    private function emailForRecord(ErpRecord $record): string
    {
        return Str::of($record->code)
            ->lower()
            ->replace(' ', '-')
            ->append('@employee.horizon.test')
            ->toString();
    }

    private function accountStatusFromHrStatus(?string $status): string
    {
        return match ($status) {
            'Inactive' => 'inactive',
            'Recruiting' => 'pending',
            default => 'active',
        };
    }

    private function profileStatusFromHrStatus(?string $status): string
    {
        return match ($status) {
            'On Leave' => 'on_leave',
            'Recruiting' => 'recruiting',
            'Probation' => 'probation',
            'Inactive' => 'inactive',
            default => 'active',
        };
    }

    private function hrStatusFromProfileStatus(?string $status): string
    {
        return match ($status) {
            'on_leave' => 'On Leave',
            'recruiting' => 'Recruiting',
            'probation' => 'Probation',
            'inactive' => 'Inactive',
            default => 'Active',
        };
    }
}
