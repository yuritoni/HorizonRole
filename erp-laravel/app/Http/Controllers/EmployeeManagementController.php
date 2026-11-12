<?php

namespace App\Http\Controllers;

use App\Models\EmployeeAnnouncement;
use App\Models\EmployeeAttendance;
use App\Models\EmployeePayroll;
use App\Models\EmployeeProfile;
use App\Models\EmployeeReimbursement;
use App\Models\EmployeeRequest as EmployeeRequestModel;
use App\Models\ErpRecord;
use App\Models\RecruitmentCandidate;
use App\Models\User;
use App\Services\EmployeeRequestIntegrationService;
use App\Services\ErpModuleIntegrationService;
use App\Services\ErpNotificationService;
use App\Services\EmployeePayrollService;
use App\Services\HrisEmployeeIntegrationService;
use App\Support\ErpViewData;
use App\Support\MasterDataRegistry;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class EmployeeManagementController extends Controller
{
    public function index()
    {
        $employees = User::with('employeeProfile.hrRecord', 'employeeProfile.managerProfile.user')
            ->where('role', 'employee')
            ->orderBy('name')
            ->get();
        $legacySdmRecords = ErpRecord::where('module', 'hr')
            ->orderBy('title')
            ->get();
        $hrisRecords = ErpRecord::where('module', 'hris')
            ->latest()
            ->get();
        $linkedHrisRecords = $hrisRecords->filter(fn (ErpRecord $record) => ! empty($record->extra['employee_profile_id']));
        $unlinkedHrisRecords = $hrisRecords->filter(fn (ErpRecord $record) => empty($record->extra['employee_profile_id'])
            && ($record->extra['source_master'] ?? null) !== 'employee_profiles_summary'
            && ! in_array($record->category, ['Recruitment', 'Interview', 'Test', 'Onboarding'], true));
        $payrolls = EmployeePayroll::with(['user', 'profile'])
            ->latest('period_month')
            ->limit(24)
            ->get();
        $payrollHrisRecords = ErpRecord::with('outgoingLinks.targetRecord')
            ->where('module', 'hris')
            ->where('category', 'Payroll Event')
            ->latest()
            ->get()
            ->filter(fn (ErpRecord $record) => ! empty($record->extra['employee_payroll_id']))
            ->keyBy(fn (ErpRecord $record) => (int) $record->extra['employee_payroll_id']);
        $employeeRequests = EmployeeRequestModel::with(['user', 'profile'])
            ->latest()
            ->limit(160)
            ->get();
        $employeeReimbursements = EmployeeReimbursement::with(['user', 'profile'])
            ->latest()
            ->limit(160)
            ->get();

        return view('settings.employees', array_merge(ErpViewData::layout(), [
            'employees' => $employees,
            'sdmRecordsCount' => EmployeeProfile::count(),
            'unsyncedSdmRecords' => $legacySdmRecords,
            'hrisRecords' => $hrisRecords,
            'linkedHrisRecordsCount' => $linkedHrisRecords->count(),
            'unlinkedHrisRecords' => $unlinkedHrisRecords->values(),
            'employeeRequests' => $employeeRequests,
            'pendingRequests' => $employeeRequests->where('status', 'submitted')->take(20)->values(),
            'employeeReimbursements' => $employeeReimbursements,
            'pendingReimbursements' => $employeeReimbursements->where('status', 'submitted')->take(20)->values(),
            'payrolls' => $payrolls,
            'payrollHrisRecords' => $payrollHrisRecords,
            'announcements' => EmployeeAnnouncement::with('creator')
                ->latest()
                ->limit(12)
                ->get(),
            'attendances' => EmployeeAttendance::with(['user', 'profile'])
                ->latest('attendance_date')
                ->limit(20)
                ->get(),
            'recruitmentCandidates' => RecruitmentCandidate::with(['assignedUser', 'hrisRecord'])
                ->latest()
                ->limit(20)
                ->get(),
            'hierarchyProfiles' => EmployeeProfile::with(['user', 'managerProfile.user', 'directReports.user'])
                ->orderBy('department')
                ->orderBy('job_level')
                ->get(),
            'departments' => MasterDataRegistry::optionsForGroup('master.departments', ['Finance', 'Accounting', 'Sales', 'Marketing', 'Operations', 'HR', 'IT']),
            'locations' => MasterDataRegistry::optionsForGroup('master.locations', ['Jakarta', 'Surabaya', 'Bandung', 'Medan']),
            'reimbursementCategories' => MasterDataRegistry::optionsForGroup('employee.reimbursement_categories', ['Transport', 'Medical', 'Meal', 'Office Supplies']),
        ]));
    }

    public function storeEmployee(Request $request, ErpNotificationService $notifications)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'employee_no' => ['nullable', 'string', 'max:80', 'unique:employee_profiles,employee_no'],
            'manager_profile_id' => ['nullable', 'exists:employee_profiles,id'],
            'department' => ['nullable', 'string', 'max:120'],
            'position' => ['nullable', 'string', 'max:160'],
            'job_level' => ['nullable', 'string', 'max:120'],
            'grade' => ['nullable', 'string', 'max:80'],
            'employment_status' => ['nullable', Rule::in(['active', 'on_leave', 'recruiting', 'probation', 'contract', 'inactive'])],
            'join_date' => ['nullable', 'date'],
            'manager_name' => ['nullable', 'string', 'max:160'],
            'work_location' => ['nullable', 'string', 'max:160'],
            'branch' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account_number' => ['nullable', 'string', 'max:120'],
            'bank_account_name' => ['nullable', 'string', 'max:160'],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'fixed_allowance' => ['nullable', 'numeric', 'min:0'],
            'tax_status' => ['nullable', 'string', 'max:40'],
            'marketing_target' => ['nullable', 'numeric', 'min:0'],
            'commission_rate' => ['nullable', 'numeric', 'min:0'],
            'production_kpi_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $managerName = ! empty($data['manager_profile_id'])
            ? EmployeeProfile::with('user')->find($data['manager_profile_id'])?->user?->name
            : null;

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => 'employee',
            'account_status' => 'active',
            'email_verified_at' => now(),
            'activated_at' => now(),
            'password' => Hash::make($data['password'] ?: 'password123'),
        ]);

        $profile = EmployeeProfile::create([
            'user_id' => $user->id,
            'manager_profile_id' => $data['manager_profile_id'] ?? null,
            'employee_no' => $data['employee_no'] ?: 'EMP-'.now()->format('ym').'-'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT),
            'department' => $data['department'] ?? null,
            'position' => $data['position'] ?? 'Employee',
            'job_level' => $data['job_level'] ?? null,
            'grade' => $data['grade'] ?? null,
            'employment_status' => $data['employment_status'] ?? 'active',
            'join_date' => $data['join_date'] ?? now()->toDateString(),
            'manager_name' => $data['manager_name'] ?? $managerName,
            'work_location' => $data['work_location'] ?? null,
            'branch' => $data['branch'] ?? null,
            'phone' => $data['phone'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'bank_account_number' => $data['bank_account_number'] ?? null,
            'bank_account_name' => $data['bank_account_name'] ?? $user->name,
            'base_salary' => $data['base_salary'] ?? 0,
            'fixed_allowance' => $data['fixed_allowance'] ?? 0,
            'tax_status' => $data['tax_status'] ?? null,
            'marketing_target' => $data['marketing_target'] ?? 0,
            'commission_rate' => $data['commission_rate'] ?? 0,
            'production_kpi_rate' => $data['production_kpi_rate'] ?? 0,
            'settings' => ['created_from' => 'employee_management'],
        ]);
        $notifications->notifyUsers([$user], [
            'type' => 'employee.account.created',
            'severity' => 'success',
            'subject_user_id' => $user->id,
            'module' => 'employee',
            'title' => 'Akun employee aktif',
            'body' => 'Akun Employee Self Service Anda sudah aktif. Silakan login dan lengkapi data pekerja.',
            'action_url' => route('employee.portal'),
            'data' => ['employee_profile_id' => $profile->id],
        ], $request->user());

        return back()->with('success', 'User employee '.$user->name.' berhasil dibuat sebagai data master SDM/Employee.');
    }

    public function editEmployee(EmployeeProfile $employeeProfile)
    {
        $employeeProfile->load(['user', 'managerProfile.user']);

        return view('settings.employee-edit', array_merge(ErpViewData::layout(), [
            'profile' => $employeeProfile,
            'employee' => $employeeProfile->user,
            'hierarchyProfiles' => EmployeeProfile::with('user')
                ->whereKeyNot($employeeProfile->id)
                ->orderBy('department')
                ->orderBy('job_level')
                ->get(),
            'departments' => MasterDataRegistry::optionsForGroup('master.departments', ['Finance', 'Accounting', 'Sales', 'Marketing', 'Operations', 'HR', 'IT']),
            'locations' => MasterDataRegistry::optionsForGroup('master.locations', ['Jakarta', 'Surabaya', 'Bandung', 'Medan']),
        ]));
    }

    public function attendanceEmployee(Request $request, EmployeeProfile $employeeProfile)
    {
        $data = $request->validate([
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2020,2100'],
        ]);
        $month = (int) ($data['month'] ?? now()->month);
        $year = (int) ($data['year'] ?? now()->year);
        $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();
        $employeeProfile->load(['user', 'managerProfile.user']);

        $attendanceRecords = EmployeeAttendance::with('approver')
            ->where(function ($query) use ($employeeProfile) {
                $query->where('employee_profile_id', $employeeProfile->id);

                if ($employeeProfile->user_id) {
                    $query->orWhere('user_id', $employeeProfile->user_id);
                }
            })
            ->whereBetween('attendance_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->orderBy('attendance_date')
            ->get()
            ->keyBy(fn (EmployeeAttendance $attendance) => $attendance->attendance_date->toDateString());

        $rows = collect();
        $cursor = $periodStart->copy();

        while ($cursor->lte($periodEnd)) {
            $attendance = $attendanceRecords->get($cursor->toDateString());
            $workHours = 0;

            if ($attendance?->clock_in_at && $attendance?->clock_out_at) {
                $workHours = round($attendance->clock_in_at->diffInMinutes($attendance->clock_out_at) / 60, 2);
            }

            $rows->push([
                'date' => $cursor->copy(),
                'attendance' => $attendance,
                'is_weekend' => $cursor->isWeekend(),
                'is_missing' => ! $attendance,
                'work_hours' => $workHours,
            ]);

            $cursor->addDay();
        }

        $summary = [
            'calendar_days' => $rows->count(),
            'working_days' => $rows->where('is_weekend', false)->count(),
            'present_days' => $attendanceRecords->filter(fn (EmployeeAttendance $attendance) => in_array($attendance->status, ['present', 'manual_present'], true))->count(),
            'manual_days' => $attendanceRecords->filter(fn (EmployeeAttendance $attendance) => $attendance->status === 'manual_present' || $attendance->source === 'manual')->count(),
            'pending_approval' => $attendanceRecords->where('approval_status', 'pending')->count(),
            'missing_working_days' => $rows->filter(fn (array $row) => ! $row['is_weekend'] && $row['is_missing'])->count(),
            'total_hours' => round($rows->sum('work_hours'), 2),
        ];
        $years = range(now()->year + 1, now()->year - 4);

        return view('settings.employee-attendance', array_merge(ErpViewData::layout(), [
            'profile' => $employeeProfile,
            'employee' => $employeeProfile->user,
            'rows' => $rows,
            'summary' => $summary,
            'month' => $month,
            'year' => $year,
            'years' => $years,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
        ]));
    }

    public function payrollEmployee(Request $request, EmployeeProfile $employeeProfile)
    {
        [$month, $year, $periodStart, $periodEnd, $years] = $this->employeePeriodFilter($request);
        $employeeProfile->load(['user', 'managerProfile.user']);

        $payrolls = EmployeePayroll::with(['user', 'profile'])
            ->where(fn ($query) => $this->constrainEmployeeProfile($query, $employeeProfile))
            ->whereBetween('period_month', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->orderByDesc('period_month')
            ->latest()
            ->get();

        $payrollCodes = $payrolls
            ->filter(fn (EmployeePayroll $payroll) => $payroll->profile && $payroll->period_month)
            ->map(fn (EmployeePayroll $payroll) => 'HRS-PAY-'.$payroll->profile->employee_no.'-'.$payroll->period_month->format('ym'))
            ->values();
        $payrollIds = $payrolls->pluck('id')->map(fn ($id) => (int) $id)->all();
        $payrollHrisRecords = $payrollCodes->isEmpty()
            ? collect()
            : ErpRecord::with(['outgoingLinks.targetRecord.payments', 'outgoingLinks.targetRecord.outgoingLinks.targetRecord'])
                ->where('module', 'hris')
                ->whereIn('code', $payrollCodes)
                ->get()
                ->filter(fn (ErpRecord $record) => in_array((int) ($record->extra['employee_payroll_id'] ?? 0), $payrollIds, true))
                ->keyBy(fn (ErpRecord $record) => (int) ($record->extra['employee_payroll_id'] ?? 0));

        $summary = [
            'count' => $payrolls->count(),
            'gross_salary' => $payrolls->sum(fn (EmployeePayroll $payroll) => (float) $payroll->gross_salary),
            'net_salary' => $payrolls->sum(fn (EmployeePayroll $payroll) => (float) $payroll->net_salary),
            'reimbursement' => $payrolls->sum(fn (EmployeePayroll $payroll) => (float) $payroll->reimbursement),
            'overtime_amount' => $payrolls->sum(fn (EmployeePayroll $payroll) => (float) $payroll->overtime_amount),
            'tax' => $payrolls->sum(fn (EmployeePayroll $payroll) => (float) $payroll->tax),
            'deduction' => $payrolls->sum(fn (EmployeePayroll $payroll) => (float) $payroll->deduction + (float) $payroll->attendance_deduction),
            'paid' => $payrolls->where('status', 'paid')->count(),
            'released' => $payrolls->where('status', 'released')->count(),
            'draft' => $payrolls->where('status', 'draft')->count(),
        ];

        return view('settings.employee-payrolls', array_merge(ErpViewData::layout(), [
            'profile' => $employeeProfile,
            'employee' => $employeeProfile->user,
            'payrolls' => $payrolls,
            'payrollHrisRecords' => $payrollHrisRecords,
            'summary' => $summary,
            'month' => $month,
            'year' => $year,
            'years' => $years,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
        ]));
    }

    public function requestsEmployee(Request $request, EmployeeProfile $employeeProfile)
    {
        [$month, $year, $periodStart, $periodEnd, $years] = $this->employeePeriodFilter($request);
        $employeeProfile->load(['user', 'managerProfile.user']);

        $employeeRequests = $this->employeeRequestQuery($employeeProfile, $periodStart, $periodEnd)
            ->orderByDesc('start_date')
            ->latest()
            ->get();
        $requestHrisRecords = $this->hrisRecordsForEmployeeRequests($employeeRequests->pluck('id')->all());

        $summary = [
            'count' => $employeeRequests->count(),
            'submitted' => $employeeRequests->where('status', 'submitted')->count(),
            'approved' => $employeeRequests->where('status', 'approved')->count(),
            'rejected' => $employeeRequests->where('status', 'rejected')->count(),
            'duration' => round($employeeRequests->sum(fn (EmployeeRequestModel $employeeRequest) => (float) $employeeRequest->total_days), 2),
            'hris_linked' => $requestHrisRecords->count(),
        ];
        $typeGroups = $employeeRequests
            ->groupBy('type')
            ->map(fn ($items, $type) => [
                'label' => $this->employeeRequestTypeLabel($type ?: 'unknown'),
                'count' => $items->count(),
                'pending' => $items->where('status', 'submitted')->count(),
            ])
            ->values();

        return view('settings.employee-requests', array_merge(ErpViewData::layout(), [
            'profile' => $employeeProfile,
            'employee' => $employeeProfile->user,
            'employeeRequests' => $employeeRequests,
            'requestHrisRecords' => $requestHrisRecords,
            'summary' => $summary,
            'typeGroups' => $typeGroups,
            'month' => $month,
            'year' => $year,
            'years' => $years,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
        ]));
    }

    public function leavesEmployee(Request $request, EmployeeProfile $employeeProfile)
    {
        [$month, $year, $periodStart, $periodEnd, $years] = $this->employeePeriodFilter($request);
        $employeeProfile->load(['user', 'managerProfile.user']);
        $leaveTypes = ['leave', 'permit', 'sick'];

        $employeeRequests = $this->employeeRequestQuery($employeeProfile, $periodStart, $periodEnd, $leaveTypes)
            ->orderByDesc('start_date')
            ->latest()
            ->get();
        $requestHrisRecords = $this->hrisRecordsForEmployeeRequests($employeeRequests->pluck('id')->all());

        $summary = [
            'count' => $employeeRequests->count(),
            'leave' => $employeeRequests->where('type', 'leave')->count(),
            'permit' => $employeeRequests->where('type', 'permit')->count(),
            'sick' => $employeeRequests->where('type', 'sick')->count(),
            'approved_days' => round($employeeRequests->where('status', 'approved')->sum(fn (EmployeeRequestModel $employeeRequest) => (float) $employeeRequest->total_days), 2),
            'pending' => $employeeRequests->where('status', 'submitted')->count(),
        ];

        return view('settings.employee-leaves', array_merge(ErpViewData::layout(), [
            'profile' => $employeeProfile,
            'employee' => $employeeProfile->user,
            'employeeRequests' => $employeeRequests,
            'requestHrisRecords' => $requestHrisRecords,
            'summary' => $summary,
            'month' => $month,
            'year' => $year,
            'years' => $years,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
        ]));
    }

    public function overtimesEmployee(Request $request, EmployeeProfile $employeeProfile)
    {
        [$month, $year, $periodStart, $periodEnd, $years] = $this->employeePeriodFilter($request);
        $employeeProfile->load(['user', 'managerProfile.user']);

        $employeeRequests = $this->employeeRequestQuery($employeeProfile, $periodStart, $periodEnd, ['overtime'])
            ->orderByDesc('start_date')
            ->latest()
            ->get();
        $requestHrisRecords = $this->hrisRecordsForEmployeeRequests($employeeRequests->pluck('id')->all());
        $hourlyRate = (float) $employeeProfile->base_salary > 0 ? ((float) $employeeProfile->base_salary / 173) * 1.5 : 0;

        $summary = [
            'count' => $employeeRequests->count(),
            'total_hours' => round($employeeRequests->sum(fn (EmployeeRequestModel $employeeRequest) => $this->overtimeHours($employeeRequest)), 2),
            'approved_hours' => round($employeeRequests->where('status', 'approved')->sum(fn (EmployeeRequestModel $employeeRequest) => $this->overtimeHours($employeeRequest)), 2),
            'pending' => $employeeRequests->where('status', 'submitted')->count(),
            'estimated_amount' => round($employeeRequests->where('status', 'approved')->sum(fn (EmployeeRequestModel $employeeRequest) => $this->overtimeHours($employeeRequest) * $hourlyRate), 2),
        ];

        return view('settings.employee-overtimes', array_merge(ErpViewData::layout(), [
            'profile' => $employeeProfile,
            'employee' => $employeeProfile->user,
            'employeeRequests' => $employeeRequests,
            'requestHrisRecords' => $requestHrisRecords,
            'summary' => $summary,
            'hourlyRate' => $hourlyRate,
            'month' => $month,
            'year' => $year,
            'years' => $years,
            'periodStart' => $periodStart,
            'periodEnd' => $periodEnd,
        ]));
    }

    public function updateEmployee(Request $request, EmployeeProfile $employeeProfile, ErpNotificationService $notifications)
    {
        $employeeProfile->load('user');
        $user = $employeeProfile->user;

        abort_if(! $user, 404);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', Rule::unique('users', 'email')->ignore($user->id)],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'account_status' => ['required', Rule::in(['pending', 'active', 'inactive'])],
            'employee_no' => ['required', 'string', 'max:80', Rule::unique('employee_profiles', 'employee_no')->ignore($employeeProfile->id)],
            'manager_profile_id' => ['nullable', 'exists:employee_profiles,id', Rule::notIn([$employeeProfile->id])],
            'department' => ['nullable', 'string', 'max:120'],
            'position' => ['nullable', 'string', 'max:160'],
            'job_level' => ['nullable', 'string', 'max:120'],
            'grade' => ['nullable', 'string', 'max:80'],
            'employment_status' => ['required', Rule::in(['active', 'on_leave', 'recruiting', 'probation', 'contract', 'inactive'])],
            'join_date' => ['nullable', 'date'],
            'manager_name' => ['nullable', 'string', 'max:160'],
            'work_location' => ['nullable', 'string', 'max:160'],
            'branch' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account_number' => ['nullable', 'string', 'max:120'],
            'bank_account_name' => ['nullable', 'string', 'max:160'],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'fixed_allowance' => ['nullable', 'numeric', 'min:0'],
            'tax_status' => ['nullable', 'string', 'max:40'],
            'marketing_target' => ['nullable', 'numeric', 'min:0'],
            'commission_rate' => ['nullable', 'numeric', 'min:0'],
            'production_kpi_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        if ($request->user()->id === $user->id && $data['account_status'] !== 'active') {
            return back()->withErrors(['account_status' => 'Anda tidak bisa menonaktifkan akun sendiri dari halaman Employee Management.'])->withInput();
        }

        $managerName = ! empty($data['manager_profile_id'])
            ? EmployeeProfile::with('user')->find($data['manager_profile_id'])?->user?->name
            : null;

        $userPayload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => 'employee',
            'account_status' => $data['account_status'],
            'email_verified_at' => $data['account_status'] === 'active'
                ? ($user->email_verified_at ?: now())
                : ($user->email !== $data['email'] ? null : $user->email_verified_at),
            'activated_at' => $data['account_status'] === 'active'
                ? ($user->activated_at ?: now())
                : null,
        ];

        if (! empty($data['password'])) {
            $userPayload['password'] = Hash::make($data['password']);
        }

        $user->forceFill($userPayload)->save();

        $settings = array_merge($employeeProfile->settings ?? [], [
            'source' => 'employee_management',
            'sdm_master_updated_at' => now()->toDateTimeString(),
            'sdm_master_updated_by' => $request->user()->id,
        ]);

        $employeeProfile->fill([
            'manager_profile_id' => $data['manager_profile_id'] ?? null,
            'employee_no' => $data['employee_no'],
            'department' => $data['department'] ?? null,
            'position' => $data['position'] ?? null,
            'job_level' => $data['job_level'] ?? null,
            'grade' => $data['grade'] ?? null,
            'employment_status' => $data['employment_status'],
            'join_date' => $data['join_date'] ?? null,
            'manager_name' => $data['manager_name'] ?? $managerName,
            'work_location' => $data['work_location'] ?? null,
            'branch' => $data['branch'] ?? null,
            'phone' => $data['phone'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'bank_account_number' => $data['bank_account_number'] ?? null,
            'bank_account_name' => $data['bank_account_name'] ?? $data['name'],
            'base_salary' => $data['base_salary'] ?? 0,
            'fixed_allowance' => $data['fixed_allowance'] ?? 0,
            'tax_status' => $data['tax_status'] ?? null,
            'marketing_target' => $data['marketing_target'] ?? 0,
            'commission_rate' => $data['commission_rate'] ?? 0,
            'production_kpi_rate' => $data['production_kpi_rate'] ?? 0,
            'settings' => $settings,
        ])->save();

        $this->syncEmployeeProfileIntegrations($employeeProfile->fresh(['user', 'managerProfile.user']), $request->user());

        $notifications->notifyUsers([$user], [
            'type' => 'employee.master.updated',
            'severity' => 'info',
            'subject_user_id' => $user->id,
            'module' => 'employee',
            'title' => 'Data pegawai diperbarui',
            'body' => 'Master data SDM/Employee Anda diperbarui oleh HR.',
            'action_url' => route('employee.portal'),
            'data' => ['employee_profile_id' => $employeeProfile->id],
        ], $request->user());

        return redirect()
            ->route('settings.employees')
            ->with('success', 'Data SDM/Employee '.$user->name.' berhasil diperbarui dan disinkronkan ke HRIS serta modul terkait.');
    }

    public function updateAttendanceLocation(Request $request, EmployeeProfile $employeeProfile)
    {
        $enabled = $request->boolean('attendance_location_enabled');
        $data = $request->validate([
            'attendance_location_enabled' => ['nullable', 'boolean'],
            'attendance_location_name' => ['nullable', 'string', 'max:160'],
            'attendance_latitude' => ['nullable', 'numeric', 'between:-90,90', Rule::requiredIf($enabled)],
            'attendance_longitude' => ['nullable', 'numeric', 'between:-180,180', Rule::requiredIf($enabled)],
            'attendance_radius_meters' => ['nullable', 'integer', 'min:10', 'max:50000', Rule::requiredIf($enabled)],
            'attendance_location_address' => ['nullable', 'string', 'max:1000'],
        ]);

        $settings = $employeeProfile->settings ?? [];
        $settings['attendance_location'] = [
            'enabled' => $enabled,
            'name' => $data['attendance_location_name'] ?? null,
            'latitude' => $enabled ? (float) $data['attendance_latitude'] : null,
            'longitude' => $enabled ? (float) $data['attendance_longitude'] : null,
            'radius_meters' => $enabled ? (int) ($data['attendance_radius_meters'] ?? 100) : null,
            'address' => $data['attendance_location_address'] ?? null,
            'updated_by' => $request->user()?->id,
            'updated_at' => now()->toDateTimeString(),
        ];

        $employeeProfile->forceFill(['settings' => $settings])->save();

        return back()->with('success', 'Setting lokasi presensi '.$employeeProfile->user?->name.' berhasil diperbarui.');
    }

    public function syncSdm(Request $request, \App\Services\EmployeeIntegrationService $integrator, ErpModuleIntegrationService $moduleIntegrator, HrisEmployeeIntegrationService $hrisIntegrator)
    {
        $profiles = $integrator->syncAllHrRecords($request->user());
        $records = $integrator->syncAllEmployeeProfiles($request->user());
        $hrisRecords = $hrisIntegrator->syncExistingRecords($request->user());
        $linkedRecords = $moduleIntegrator->syncAll($request->user());

        return back()->with('success', 'Penggabungan SDM selesai: '.$profiles->filter()->count().' record SDM legacy dipindahkan ke Employee Management, '.$records->filter()->count().' profil employee menjadi master tunggal, '.$hrisRecords->filter(fn ($record) => ! empty($record->extra['employee_profile_id']))->count().' record HRIS tersambung ke employee profile, dan '.$linkedRecords->filter()->count().' relasi lintas modul diperbarui.');
    }

    public function syncHris(Request $request, HrisEmployeeIntegrationService $hrisIntegrator, ErpModuleIntegrationService $moduleIntegrator, EmployeePayrollService $payrollService)
    {
        $legacyPayrollRecords = $payrollService->consolidateLegacyHrisPayrollRecords($request->user());
        $payrollRecords = $payrollService->syncAllPayrolls($request->user());
        $payrollReimbursements = $payrollService->prunePayrollModeReimbursementPayables();
        $requestIntegrator = app(EmployeeRequestIntegrationService::class);
        $requestRecords = $requestIntegrator->syncAll($request->user());
        $payrollOvertimes = $requestIntegrator->prunePayrollModeOvertimePayables();
        $hrisRecords = $hrisIntegrator->syncExistingRecords($request->user());
        $linkedRecords = $moduleIntegrator->syncAll($request->user());
        $linkedCount = $hrisRecords->filter(fn ($record) => ! empty($record->extra['employee_profile_id']))->count();
        $unlinkedCount = $hrisRecords->filter(fn ($record) => empty($record->extra['employee_profile_id'])
            && ($record->extra['source_master'] ?? null) !== 'employee_profiles_summary'
            && ! in_array($record->category, ['Recruitment', 'Interview', 'Test', 'Onboarding'], true))->count();

        return back()->with('success', 'Sync HRIS selesai: '.$legacyPayrollRecords->count().' payroll HRIS lama dikonsolidasikan, '.$payrollRecords->count().' payroll tersambung ke HRIS, '.$requestRecords->count().' pengajuan employee tersambung ke HRIS, '.$payrollReimbursements.' reimburse payroll dan '.$payrollOvertimes.' lembur payroll dibersihkan dari payable terpisah, '.$linkedCount.' record HRIS tersambung ke master SDM/Employee, '.$unlinkedCount.' record perlu dipilih pegawainya, dan '.$linkedRecords->filter()->count().' relasi finance/accounting/payment diperbarui.');
    }

    public function reviewRequest(Request $request, EmployeeRequestModel $employeeRequest, ErpNotificationService $notifications, ErpModuleIntegrationService $moduleIntegrator, EmployeeRequestIntegrationService $requestIntegrator, EmployeePayrollService $payrollService)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $employeeRequest->update([
            'status' => $data['status'],
            'review_note' => $data['review_note'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        if ($data['status'] === 'approved' && $employeeRequest->type === 'manual_attendance') {
            $this->approveManualAttendance($employeeRequest, $request);
        }

        $hrisRecord = $requestIntegrator->syncToHris($employeeRequest->fresh(['profile.managerProfile.user', 'user']), $request->user());
        $moduleIntegrator->syncRecord($hrisRecord, $request->user());

        if ($data['status'] === 'approved' && $employeeRequest->type === 'overtime') {
            $payrollService->syncAllPayrolls($request->user());
        }

        $notifications->notifyUsers([$employeeRequest->user], [
            'type' => 'employee.request.reviewed',
            'severity' => $data['status'] === 'approved' ? 'success' : 'danger',
            'subject_user_id' => $employeeRequest->user_id,
            'module' => 'employee',
            'title' => 'Status pengajuan diperbarui',
            'body' => $employeeRequest->title.' sekarang '.strtoupper($employeeRequest->status).'.',
            'action_url' => route('employee.portal'),
            'data' => ['employee_request_id' => $employeeRequest->id],
        ], $request->user());

        return back()->with('success', 'Pengajuan '.$employeeRequest->title.' sudah '.$employeeRequest->status.'.');
    }

    public function reviewReimbursement(Request $request, EmployeeReimbursement $reimbursement, ErpNotificationService $notifications, ErpModuleIntegrationService $moduleIntegrator, EmployeePayrollService $payrollService)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected', 'paid'])],
            'payment_method' => ['nullable', Rule::in(['payroll', 'separate'])],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $paymentMethod = $data['payment_method'] ?? $reimbursement->payment_method ?? 'payroll';

        $reimbursement->update([
            'status' => $data['status'],
            'payment_method' => $paymentMethod,
            'paid_at' => $data['status'] === 'paid' ? now() : $reimbursement->paid_at,
            'review_note' => $data['review_note'] ?? null,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        if (in_array($data['status'], ['approved', 'paid'], true)) {
            $hrisRecord = $this->syncReimbursementToHris($reimbursement->fresh(['profile', 'user']), $request->user());
            $moduleIntegrator->syncRecord($hrisRecord, $request->user());

            if ($paymentMethod === 'payroll') {
                $payrollService->syncAllPayrolls($request->user());
            }
        }

        $notifications->notifyUsers([$reimbursement->user], [
            'type' => 'employee.reimbursement.reviewed',
            'severity' => in_array($data['status'], ['approved', 'paid'], true) ? 'success' : 'danger',
            'subject_user_id' => $reimbursement->user_id,
            'module' => 'employee',
            'title' => 'Status reimburse diperbarui',
            'body' => $reimbursement->title.' sekarang '.strtoupper($reimbursement->status).'.',
            'action_url' => route('employee.portal'),
            'data' => ['employee_reimbursement_id' => $reimbursement->id],
        ], $request->user());

        return back()->with('success', 'Reimburse '.$reimbursement->title.' sudah '.$reimbursement->status.($paymentMethod === 'payroll' ? ' dan akan digabung ke payroll.' : ' dan dibuat payable terpisah di Finance.').'.');
    }

    public function storePayroll(Request $request, ErpNotificationService $notifications, EmployeePayrollService $payrollService)
    {
        $data = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'period_month' => ['required', 'date'],
            'basic_salary' => ['nullable', 'numeric', 'min:0'],
            'allowance' => ['nullable', 'numeric', 'min:0'],
            'deduction' => ['nullable', 'numeric', 'min:0'],
            'reimbursement' => ['nullable', 'numeric', 'min:0'],
            'overtime_amount' => ['nullable', 'numeric', 'min:0'],
            'bonus' => ['nullable', 'numeric', 'min:0'],
            'thr' => ['nullable', 'numeric', 'min:0'],
            'tax' => ['nullable', 'numeric', 'min:0'],
            'marketing_commission' => ['nullable', 'numeric', 'min:0'],
            'production_incentive' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['draft', 'released', 'paid'])],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = User::findOrFail($data['user_id']);
        $profile = EmployeeProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'employee_no' => 'EMP-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                'employment_status' => 'active',
                'position' => 'Employee',
                'bank_account_name' => $user->name,
            ]
        );

        $payroll = $payrollService->storePayroll($profile, $data['period_month'], $data, $data['status'], $data['notes'] ?? null, $request->user());

        if (in_array($payroll->status, ['released', 'paid'], true)) {
            $notifications->notifyUsers([$user], [
                'type' => 'employee.payroll.released',
                'severity' => 'success',
                'subject_user_id' => $user->id,
                'module' => 'employee',
                'title' => 'Slip gaji tersedia',
                'body' => 'Slip gaji periode '.$payroll->period_month->format('M Y').' sudah tersedia.',
                'action_url' => route('employee.payrolls.slip', $payroll),
                'data' => ['employee_payroll_id' => $payroll->id],
            ], $request->user());
        }

        return redirect()
            ->to(route('settings.employees').'#payroll-list')
            ->with('success', 'Payroll '.$user->name.' periode '.$payroll->period_month->format('M Y').' tersimpan dan tersinkron ke HRIS'.(in_array($payroll->status, ['released', 'paid'], true) ? ', finance, dan accounting.' : ' sebagai draft.'));
    }

    public function storeRecruitment(Request $request, ErpModuleIntegrationService $moduleIntegrator)
    {
        $data = $request->validate([
            'candidate_name' => ['required', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:180'],
            'phone' => ['nullable', 'string', 'max:40'],
            'department' => ['nullable', 'string', 'max:120'],
            'position' => ['nullable', 'string', 'max:160'],
            'stage' => ['required', Rule::in(['applied', 'screening', 'meeting', 'interview', 'test', 'offered', 'onboarding', 'hired', 'rejected'])],
            'meeting_at' => ['nullable', 'date'],
            'meeting_url' => ['nullable', 'string', 'max:255'],
            'interviewer' => ['nullable', 'string', 'max:160'],
            'test_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'onboarding_start_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1200'],
        ]);

        $candidate = RecruitmentCandidate::create($data + [
            'created_by' => $request->user()->id,
            'payload' => ['source' => 'employee_management'],
        ]);

        $hrisRecord = $this->syncCandidateToHris($candidate, $request->user());
        $candidate->update(['hris_record_id' => $hrisRecord->id]);
        $moduleIntegrator->syncRecord($hrisRecord, $request->user());

        return back()->with('success', 'Kandidat recruitment tersimpan dan tersinkron ke HRIS.');
    }

    public function updateRecruitment(Request $request, RecruitmentCandidate $candidate, ErpModuleIntegrationService $moduleIntegrator)
    {
        $data = $request->validate([
            'stage' => ['required', Rule::in(['applied', 'screening', 'meeting', 'interview', 'test', 'offered', 'onboarding', 'hired', 'rejected'])],
            'meeting_at' => ['nullable', 'date'],
            'meeting_url' => ['nullable', 'string', 'max:255'],
            'interviewer' => ['nullable', 'string', 'max:160'],
            'test_score' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'onboarding_start_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:1200'],
        ]);

        $candidate->update($data);
        $hrisRecord = $this->syncCandidateToHris($candidate->fresh(), $request->user());
        $candidate->update(['hris_record_id' => $hrisRecord->id]);
        $moduleIntegrator->syncRecord($hrisRecord, $request->user());

        return back()->with('success', 'Tahap recruitment diperbarui.');
    }

    public function storeAnnouncement(Request $request, ErpNotificationService $notifications)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'max:2000'],
            'category' => ['required', 'string', 'max:80'],
            'audience' => ['required', 'string', 'max:120'],
            'is_published' => ['nullable', 'boolean'],
            'publish_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:publish_at'],
        ]);

        $announcement = EmployeeAnnouncement::create([
            'kind' => 'announcement',
            'title' => $data['title'],
            'summary' => Str::limit($data['body'], 180),
            'body' => $data['body'],
            'category' => $data['category'],
            'audience' => $data['audience'],
            'source_department' => 'HR',
            'priority' => 'normal',
            'scope_module' => 'employee',
            'is_published' => $request->boolean('is_published', true),
            'is_pinned' => false,
            'requires_acknowledgement' => false,
            'publish_at' => $data['publish_at'] ?? now(),
            'expires_at' => $data['expires_at'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        if ($announcement->is_published) {
            $recipients = User::where('role', 'employee')
                ->where('account_status', 'active')
                ->get()
                ->filter(function (User $user) use ($announcement) {
                    $department = $user->employeeProfile?->department;

                    return in_array($announcement->audience, ['all', 'employee', $department], true);
                });

            $notifications->notifyUsers($recipients, [
                'type' => 'employee.announcement',
                'severity' => 'info',
                'module' => 'employee',
                'title' => $announcement->title,
                'body' => Str::limit($announcement->body, 240),
                'action_url' => route('announcements.show', $announcement),
                'data' => ['employee_announcement_id' => $announcement->id],
            ], $request->user());
        }

        return back()->with('success', 'Informasi employee berhasil dipublikasikan.');
    }

    private function syncEmployeeProfileIntegrations(EmployeeProfile $profile, ?User $actor): void
    {
        $profile->loadMissing(['user', 'managerProfile.user']);
        $hrisIntegrator = app(HrisEmployeeIntegrationService::class);

        ErpRecord::where('module', 'hris')
            ->get()
            ->filter(fn (ErpRecord $record) => (int) ($record->extra['employee_profile_id'] ?? 0) === (int) $profile->id)
            ->each(fn (ErpRecord $record) => $hrisIntegrator->applyEmployeeContext($record, $actor, $profile));

        app(ErpModuleIntegrationService::class)->syncAll($actor);
    }

    private function approveManualAttendance(EmployeeRequestModel $employeeRequest, Request $request): void
    {
        $payload = $employeeRequest->payload ?? [];
        $date = $payload['attendance_date'] ?? $employeeRequest->start_date?->toDateString();

        if (! $date) {
            return;
        }

        $clockIn = ! empty($payload['clock_in_at']) ? Carbon::parse($date.' '.$payload['clock_in_at']) : null;
        $clockOut = ! empty($payload['clock_out_at']) ? Carbon::parse($date.' '.$payload['clock_out_at']) : null;

        EmployeeAttendance::updateOrCreate(
            ['user_id' => $employeeRequest->user_id, 'attendance_date' => $date],
            [
                'employee_profile_id' => $employeeRequest->employee_profile_id,
                'source' => 'manual',
                'device_id' => $payload['device_id'] ?? null,
                'phone_number' => $payload['phone_number'] ?? $employeeRequest->profile?->phone,
                'clock_in_at' => $clockIn,
                'clock_in_address' => $payload['address'] ?? null,
                'clock_out_at' => $clockOut,
                'clock_out_address' => $payload['address'] ?? null,
                'status' => 'manual_present',
                'approval_status' => 'approved',
                'approved_by' => $request->user()->id,
                'approved_at' => now(),
                'notes' => $employeeRequest->description,
                'metadata' => [
                    'employee_request_id' => $employeeRequest->id,
                    'review_note' => $employeeRequest->review_note,
                ],
            ]
        );
    }

    private function syncRequestToHris(EmployeeRequestModel $employeeRequest, ?User $actor): ErpRecord
    {
        return app(EmployeeRequestIntegrationService::class)->syncToHris($employeeRequest, $actor);
    }

    private function syncReimbursementToHris(EmployeeReimbursement $reimbursement, ?User $actor): ErpRecord
    {
        $reimbursement->loadMissing(['profile.managerProfile.user', 'user']);
        $record = ErpRecord::updateOrCreate(
            ['module' => 'hris', 'code' => 'HRS-RMB-'.$reimbursement->id],
            [
                'title' => $reimbursement->user?->name.' - '.$reimbursement->title,
                'category' => 'Reimbursement',
                'party' => $reimbursement->profile?->department,
                'quantity' => 1,
                'amount' => $reimbursement->amount,
                'progress' => in_array($reimbursement->status, ['approved', 'paid'], true) ? 100 : 35,
                'status' => $reimbursement->status === 'paid'
                    ? 'Processed'
                    : ($reimbursement->status === 'approved' ? 'Approved' : ($reimbursement->status === 'rejected' ? 'Rejected' : 'Pending Approval')),
                'owner' => $reimbursement->profile?->managerProfile?->user?->name ?: ($reimbursement->profile?->manager_name ?: 'HR'),
                'due_date' => $reimbursement->expense_date,
                'notes' => $reimbursement->description,
                'extra' => [
                    'employee_reimbursement_id' => $reimbursement->id,
                    'employee_profile_id' => $reimbursement->employee_profile_id,
                    'employee_no' => $reimbursement->profile?->employee_no,
                    'user_id' => $reimbursement->user_id,
                    'employee_name' => $reimbursement->user?->name,
                    'department' => $reimbursement->profile?->department,
                    'position' => $reimbursement->profile?->position,
                    'manager_profile_id' => $reimbursement->profile?->manager_profile_id,
                    'manager_name' => $reimbursement->profile?->managerProfile?->user?->name ?: $reimbursement->profile?->manager_name,
                    'payment_mode' => $reimbursement->payment_method ?: 'payroll',
                    'employee_payroll_id' => $reimbursement->employee_payroll_id,
                    'paid_at' => $reimbursement->paid_at?->toDateTimeString(),
                    'source_master' => 'employee_profiles',
                    'source' => 'employee_reimbursement',
                ],
                'created_by' => $actor?->id ?: $reimbursement->user_id,
            ]
        );

        return app(HrisEmployeeIntegrationService::class)
            ->applyEmployeeContext($record->fresh(), $actor, $reimbursement->profile);
    }

    private function employeePeriodFilter(Request $request): array
    {
        $data = $request->validate([
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2020,2100'],
        ]);
        $month = (int) ($data['month'] ?? now()->month);
        $year = (int) ($data['year'] ?? now()->year);
        $periodStart = Carbon::create($year, $month, 1)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();
        $years = range(now()->year + 1, now()->year - 4);

        return [$month, $year, $periodStart, $periodEnd, $years];
    }

    private function constrainEmployeeProfile($query, EmployeeProfile $employeeProfile): void
    {
        $query->where('employee_profile_id', $employeeProfile->id);

        if ($employeeProfile->user_id) {
            $query->orWhere('user_id', $employeeProfile->user_id);
        }
    }

    private function employeeRequestQuery(EmployeeProfile $employeeProfile, Carbon $periodStart, Carbon $periodEnd, ?array $types = null)
    {
        $query = EmployeeRequestModel::with(['user', 'profile', 'reviewer'])
            ->where(fn ($query) => $this->constrainEmployeeProfile($query, $employeeProfile))
            ->where(function ($query) use ($periodStart, $periodEnd) {
                $query->whereBetween('start_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
                    ->orWhere(function ($innerQuery) use ($periodStart, $periodEnd) {
                        $innerQuery
                            ->whereNull('start_date')
                            ->whereBetween('created_at', [$periodStart, $periodEnd]);
                    });
            });

        if ($types) {
            $query->whereIn('type', $types);
        }

        return $query;
    }

    private function hrisRecordsForEmployeeRequests(array $requestIds)
    {
        $codes = collect($requestIds)
            ->map(fn ($id) => 'HRS-REQ-'.$id)
            ->values();

        if ($codes->isEmpty()) {
            return collect();
        }

        return ErpRecord::where('module', 'hris')
            ->whereIn('code', $codes)
            ->get()
            ->keyBy(fn (ErpRecord $record) => (int) ($record->extra['employee_request_id'] ?? str_replace('HRS-REQ-', '', $record->code)));
    }

    private function overtimeHours(EmployeeRequestModel $employeeRequest): float
    {
        $payload = $employeeRequest->payload ?? [];

        return (float) ($payload['total_hours'] ?? $employeeRequest->total_days ?? 0);
    }

    private function employeeRequestTypeLabel(string $type): string
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

    private function syncCandidateToHris(RecruitmentCandidate $candidate, ?User $actor): ErpRecord
    {
        $status = match ($candidate->stage) {
            'meeting' => 'Scheduled',
            'interview' => 'Interview',
            'test' => 'Test',
            'onboarding', 'hired' => 'Onboarding',
            'rejected' => 'Rejected',
            default => 'Draft',
        };

        return ErpRecord::updateOrCreate(
            ['module' => 'hris', 'code' => 'HRS-REC-'.$candidate->id],
            [
                'title' => $candidate->candidate_name.' - Recruitment',
                'category' => in_array($candidate->stage, ['interview', 'test', 'onboarding'], true)
                    ? str($candidate->stage)->headline()->toString()
                    : 'Recruitment',
                'party' => $candidate->department,
                'quantity' => $candidate->test_score,
                'amount' => 0,
                'progress' => match ($candidate->stage) {
                    'screening' => 20,
                    'meeting' => 35,
                    'interview' => 50,
                    'test' => 70,
                    'offered' => 82,
                    'onboarding' => 95,
                    'hired' => 100,
                    default => 10,
                },
                'status' => $status,
                'owner' => $candidate->interviewer ?: 'HR',
                'due_date' => $candidate->meeting_at ?: $candidate->onboarding_start_date,
                'notes' => $candidate->notes,
                'extra' => [
                    'recruitment_candidate_id' => $candidate->id,
                    'email' => $candidate->email,
                    'phone' => $candidate->phone,
                    'position' => $candidate->position,
                    'stage' => $candidate->stage,
                    'meeting_at' => $candidate->meeting_at?->toDateTimeString(),
                    'meeting_url' => $candidate->meeting_url,
                    'test_score' => $candidate->test_score,
                    'onboarding_start_date' => $candidate->onboarding_start_date?->toDateString(),
                    'source' => 'recruitment_onboarding',
                ],
                'created_by' => $actor?->id ?: $candidate->created_by,
            ]
        )->fresh();
    }
}
