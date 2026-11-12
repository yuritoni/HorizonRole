<?php

namespace App\Http\Controllers;

use App\Models\CalendarEvent;
use App\Models\EmployeeAnnouncement;
use App\Models\EmployeeAttendance;
use App\Models\EmployeePayroll;
use App\Models\EmployeeProfile;
use App\Models\EmployeeReimbursement;
use App\Models\EmployeeRequest;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class MobileEmployeePortalController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Email atau password tidak sesuai.'], 422);
        }

        if ($user->account_status !== 'active') {
            return response()->json(['message' => 'Akun belum aktif atau sedang dinonaktifkan.'], 403);
        }

        if (! $user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Email akun belum diverifikasi.'], 403);
        }

        if (! $user->hasPrivilege('employee.portal')) {
            return response()->json(['message' => 'Akun tidak memiliki akses Employee Portal.'], 403);
        }

        $tokenName = $data['device_name'] ?: 'flutter-employee';
        $token = $user->createToken($tokenName, ['employee.portal'])->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
            'portal' => $this->portalPayload($user),
        ]);
    }

    public function portal(Request $request)
    {
        return response()->json($this->portalPayload($request->user()));
    }

    public function updateProfile(Request $request)
    {
        $profile = $this->profileFor($request->user());
        $data = $request->validate([
            'phone' => ['nullable', 'string', 'max:40'],
            'birth_place' => ['nullable', 'string', 'max:120'],
            'birth_date' => ['nullable', 'date'],
            'gender' => ['nullable', Rule::in(['Pria', 'Wanita', 'Lainnya'])],
            'address' => ['nullable', 'string', 'max:1000'],
            'identity_no' => ['nullable', 'string', 'max:80'],
            'marital_status' => ['nullable', 'string', 'max:80'],
            'bank_name' => ['nullable', 'string', 'max:120'],
            'bank_account_number' => ['nullable', 'string', 'max:120'],
            'bank_account_name' => ['nullable', 'string', 'max:160'],
            'education_level' => ['nullable', 'string', 'max:120'],
            'education_institution' => ['nullable', 'string', 'max:180'],
            'education_major' => ['nullable', 'string', 'max:160'],
            'graduation_year' => ['nullable', 'string', 'max:10'],
            'emergency_name' => ['nullable', 'string', 'max:160'],
            'emergency_relation' => ['nullable', 'string', 'max:120'],
            'emergency_phone' => ['nullable', 'string', 'max:40'],
            'department' => ['nullable', 'string', 'max:120'],
            'position' => ['nullable', 'string', 'max:160'],
            'manager_name' => ['nullable', 'string', 'max:160'],
            'work_location' => ['nullable', 'string', 'max:160'],
            'contract_type' => ['nullable', 'string', 'max:120'],
            'shift' => ['nullable', 'string', 'max:120'],
            'experience_summary' => ['nullable', 'string', 'max:1200'],
        ]);

        $profile->update([
            'phone' => $data['phone'] ?? $profile->phone,
            'birth_place' => $data['birth_place'] ?? $profile->birth_place,
            'birth_date' => $data['birth_date'] ?? $profile->birth_date,
            'gender' => $data['gender'] ?? $profile->gender,
            'address' => $data['address'] ?? $profile->address,
            'bank_name' => $data['bank_name'] ?? $profile->bank_name,
            'bank_account_number' => $data['bank_account_number'] ?? $profile->bank_account_number,
            'bank_account_name' => $data['bank_account_name'] ?? $profile->bank_account_name,
            'department' => $data['department'] ?? $profile->department,
            'position' => $data['position'] ?? $profile->position,
            'manager_name' => $data['manager_name'] ?? $profile->manager_name,
            'work_location' => $data['work_location'] ?? $profile->work_location,
            'personal_data' => array_filter([
                'identity_no' => $data['identity_no'] ?? ($profile->personal_data['identity_no'] ?? null),
                'marital_status' => $data['marital_status'] ?? ($profile->personal_data['marital_status'] ?? null),
            ]),
            'education_data' => array_filter([
                'level' => $data['education_level'] ?? ($profile->education_data['level'] ?? null),
                'institution' => $data['education_institution'] ?? ($profile->education_data['institution'] ?? null),
                'major' => $data['education_major'] ?? ($profile->education_data['major'] ?? null),
                'graduation_year' => $data['graduation_year'] ?? ($profile->education_data['graduation_year'] ?? null),
            ]),
            'emergency_contact' => array_filter([
                'name' => $data['emergency_name'] ?? ($profile->emergency_contact['name'] ?? null),
                'relation' => $data['emergency_relation'] ?? ($profile->emergency_contact['relation'] ?? null),
                'phone' => $data['emergency_phone'] ?? ($profile->emergency_contact['phone'] ?? null),
            ]),
            'job_data' => array_filter([
                'contract_type' => $data['contract_type'] ?? ($profile->job_data['contract_type'] ?? null),
                'shift' => $data['shift'] ?? ($profile->job_data['shift'] ?? null),
            ]),
            'experience_data' => array_filter([
                'summary' => $data['experience_summary'] ?? ($profile->experience_data['summary'] ?? null),
            ]),
        ]);

        EmployeeRequest::create([
            'employee_profile_id' => $profile->id,
            'user_id' => $request->user()->id,
            'type' => 'data_change',
            'status' => 'submitted',
            'title' => 'Perubahan Data Pekerja',
            'description' => 'Employee memperbarui data pekerja melalui mobile app.',
            'payload' => $data,
        ]);

        return $this->portalResponse($request->user());
    }

    public function clockIn(Request $request)
    {
        return $this->clock($request, 'in');
    }

    public function clockOut(Request $request)
    {
        return $this->clock($request, 'out');
    }

    public function storeManualAttendance(Request $request)
    {
        $profile = $this->profileFor($request->user());
        $data = $request->validate([
            'attendance_date' => ['required', 'date'],
            'clock_in_at' => ['required', 'date_format:H:i'],
            'clock_out_at' => ['nullable', 'date_format:H:i', 'after:clock_in_at'],
            'device_id' => ['nullable', 'string', 'max:120'],
            'phone_number' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:1000'],
            'reason' => ['required', 'string', 'max:1200'],
        ]);

        $totalHours = ! empty($data['clock_out_at'])
            ? $this->hoursBetween($data['attendance_date'], $data['clock_in_at'], $data['attendance_date'], $data['clock_out_at'])
            : null;

        EmployeeRequest::create([
            'employee_profile_id' => $profile->id,
            'user_id' => $request->user()->id,
            'type' => 'manual_attendance',
            'status' => 'submitted',
            'title' => 'Pengajuan Presensi Manual '.$data['attendance_date'],
            'start_date' => $data['attendance_date'],
            'end_date' => $data['attendance_date'],
            'total_days' => $totalHours ?: 1,
            'description' => $data['reason'],
            'payload' => [
                'attendance_date' => $data['attendance_date'],
                'clock_in_at' => $data['clock_in_at'],
                'clock_out_at' => $data['clock_out_at'] ?? null,
                'start_time' => $data['clock_in_at'],
                'end_time' => $data['clock_out_at'] ?? null,
                'total_hours' => $totalHours,
                'device_id' => $data['device_id'] ?? null,
                'phone_number' => $data['phone_number'] ?? $profile->phone,
                'address' => $data['address'] ?? null,
                'source' => 'mobile_app',
            ],
        ]);

        return $this->portalResponse($request->user());
    }

    public function storeRequest(Request $request)
    {
        $profile = $this->profileFor($request->user());
        $data = $request->validate([
            'type' => ['required', Rule::in(['leave', 'permit', 'sick', 'overtime', 'manual_attendance', 'training', 'business_trip', 'data_change', 'information'])],
            'title' => ['required', 'string', 'max:180'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'total_days' => ['nullable', 'numeric', 'min:0'],
            'travel_destination' => ['nullable', 'string', 'max:180'],
            'travel_purpose' => ['nullable', 'string', 'max:500'],
            'transport_mode' => ['nullable', 'string', 'max:120'],
            'estimated_amount' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:1600'],
        ]);

        $totalDays = $data['total_days'] ?? null;
        $totalHours = null;

        if (! $totalDays && ! empty($data['start_date']) && ! empty($data['end_date'])) {
            $totalDays = Carbon::parse($data['start_date'])->diffInDays(Carbon::parse($data['end_date'])) + 1;
        }

        if (in_array($data['type'], ['overtime', 'manual_attendance'], true) && ! empty($data['start_date']) && ! empty($data['start_time']) && ! empty($data['end_time'])) {
            $totalHours = $this->hoursBetween(
                $data['start_date'],
                $data['start_time'],
                $data['end_date'] ?? $data['start_date'],
                $data['end_time']
            );
            $totalDays = $data['type'] === 'overtime' ? $totalHours : ($totalDays ?: $totalHours);
        }

        EmployeeRequest::create([
            'employee_profile_id' => $profile->id,
            'user_id' => $request->user()->id,
            'type' => $data['type'],
            'status' => 'submitted',
            'title' => $data['title'],
            'start_date' => $data['start_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'total_days' => $totalDays,
            'description' => $data['description'] ?? null,
            'payload' => array_filter([
                'source' => 'mobile_app',
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'total_hours' => $totalHours,
                'travel_destination' => $data['travel_destination'] ?? null,
                'travel_purpose' => $data['travel_purpose'] ?? null,
                'transport_mode' => $data['transport_mode'] ?? null,
                'estimated_amount' => $data['estimated_amount'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''),
        ]);

        return $this->portalResponse($request->user());
    }

    public function storeReimbursement(Request $request)
    {
        $profile = $this->profileFor($request->user());
        $data = $request->validate([
            'category' => ['required', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:180'],
            'amount' => ['required', 'numeric', 'min:0'],
            'expense_date' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:1600'],
            'receipt_reference' => ['nullable', 'string', 'max:180'],
        ]);

        EmployeeReimbursement::create([
            'employee_profile_id' => $profile->id,
            'user_id' => $request->user()->id,
            'category' => $data['category'],
            'title' => $data['title'],
            'amount' => $data['amount'],
            'expense_date' => $data['expense_date'] ?? null,
            'description' => $data['description'] ?? null,
            'receipt_reference' => $data['receipt_reference'] ?? null,
            'status' => 'submitted',
            'payment_method' => 'payroll',
        ]);

        return $this->portalResponse($request->user());
    }

    private function clock(Request $request, string $direction)
    {
        $profile = $this->profileFor($request->user());
        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'address' => ['nullable', 'string', 'max:1000'],
            'device_id' => ['nullable', 'string', 'max:120'],
            'phone_number' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $attendance = EmployeeAttendance::where('user_id', $request->user()->id)
            ->where('attendance_date', now()->toDateString())
            ->first();

        if ($direction === 'in' && $attendance?->clock_in_at) {
            return response()->json(['message' => 'Presensi masuk hari ini sudah tercatat.'], 422);
        }

        if ($direction === 'out' && (! $attendance || ! $attendance->clock_in_at)) {
            return response()->json(['message' => 'Presensi masuk belum tercatat.'], 422);
        }

        if ($direction === 'out' && $attendance->clock_out_at) {
            return response()->json(['message' => 'Presensi pulang hari ini sudah tercatat.'], 422);
        }

        $locationCheck = $this->attendanceLocationCheck($profile, $data);

        if (! $locationCheck['allowed']) {
            return response()->json(['message' => $locationCheck['message']], 422);
        }

        $attendance = $attendance ?: EmployeeAttendance::firstOrCreate(
            ['user_id' => $request->user()->id, 'attendance_date' => now()->toDateString()],
            [
                'employee_profile_id' => $profile->id,
                'status' => 'present',
            ]
        );

        $payload = [
            'employee_profile_id' => $profile->id,
            'source' => 'device',
            'device_id' => $attendance->device_id ?: ($data['device_id'] ?? null),
            'phone_number' => $attendance->phone_number ?: ($data['phone_number'] ?? $profile->phone),
            'approval_status' => $attendance->approval_status ?: 'approved',
            'notes' => $data['notes'] ?? $attendance->notes,
        ];

        if ($direction === 'in') {
            $payload = array_merge($payload, [
                'clock_in_at' => now(),
                'clock_in_lat' => $data['latitude'] ?? null,
                'clock_in_lng' => $data['longitude'] ?? null,
                'clock_in_address' => $data['address'] ?? null,
                'metadata' => array_merge($attendance->metadata ?? [], ['clock_in_location_check' => $locationCheck]),
            ]);
        } else {
            $payload = array_merge($payload, [
                'clock_out_at' => now(),
                'clock_out_lat' => $data['latitude'] ?? null,
                'clock_out_lng' => $data['longitude'] ?? null,
                'clock_out_address' => $data['address'] ?? null,
                'metadata' => array_merge($attendance->metadata ?? [], ['clock_out_location_check' => $locationCheck]),
            ]);
        }

        $attendance->update($payload);

        return $this->portalResponse($request->user());
    }

    private function portalResponse(User $user)
    {
        return response()->json(['portal' => $this->portalPayload($user->fresh())]);
    }

    private function portalPayload(User $user): array
    {
        $profile = $this->profileFor($user);
        $today = now()->toDateString();
        $todayAttendance = EmployeeAttendance::where('user_id', $user->id)
            ->where('attendance_date', $today)
            ->first();

        $announcements = EmployeeAnnouncement::query()
            ->published()
            ->visibleToUser($user)
            ->with(['reads' => fn ($query) => $query->where('user_id', $user->id)])
            ->orderByDesc('is_pinned')
            ->latest('publish_at')
            ->limit(8)
            ->get();

        return [
            'user' => $this->userPayload($user),
            'profile' => $this->profilePayload($profile),
            'attendanceLocationPolicy' => $this->attendanceLocationPolicyFor($profile),
            'todayAttendance' => $todayAttendance ? $this->attendancePayload($todayAttendance) : null,
            'recentAttendances' => $profile->attendances()->latest('attendance_date')->limit(7)->get()->map(fn ($item) => $this->attendancePayload($item))->values(),
            'requests' => $profile->requests()->latest()->limit(20)->get()->map(fn ($item) => $this->requestPayload($item))->values(),
            'reimbursements' => $profile->reimbursements()->latest()->limit(20)->get()->map(fn ($item) => $this->reimbursementPayload($item))->values(),
            'payrolls' => $profile->payrolls()->latest('period_month')->limit(12)->get()->map(fn ($item) => $this->payrollPayload($item))->values(),
            'announcements' => $announcements->map(fn ($item) => $this->announcementPayload($item, $user))->values(),
            'calendarEvents' => $this->calendarEventsFor($user, $profile)->values(),
        ];
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ];
    }

    private function profilePayload(EmployeeProfile $profile): array
    {
        return [
            'id' => $profile->id,
            'employee_no' => $profile->employee_no,
            'department' => $profile->department,
            'position' => $profile->position,
            'job_level' => $profile->job_level,
            'grade' => $profile->grade,
            'employment_status' => $profile->employment_status,
            'join_date' => $this->date($profile->join_date),
            'manager_name' => $profile->manager_name,
            'work_location' => $profile->work_location,
            'branch' => $profile->branch,
            'phone' => $profile->phone,
            'birth_place' => $profile->birth_place,
            'birth_date' => $this->date($profile->birth_date),
            'gender' => $profile->gender,
            'address' => $profile->address,
            'bank_name' => $profile->bank_name,
            'bank_account_number' => $profile->bank_account_number,
            'bank_account_name' => $profile->bank_account_name,
            'personal_data' => $profile->personal_data ?? [],
            'education_data' => $profile->education_data ?? [],
            'emergency_contact' => $profile->emergency_contact ?? [],
            'job_data' => $profile->job_data ?? [],
            'experience_data' => $profile->experience_data ?? [],
            'settings' => $profile->settings ?? [],
        ];
    }

    private function attendancePayload(EmployeeAttendance $attendance): array
    {
        return [
            'id' => $attendance->id,
            'attendance_date' => $this->date($attendance->attendance_date),
            'clock_in_at' => $this->datetime($attendance->clock_in_at),
            'clock_out_at' => $this->datetime($attendance->clock_out_at),
            'source' => $attendance->source ?: 'device',
            'status' => $attendance->status ?: 'present',
            'approval_status' => $attendance->approval_status,
            'clock_in_address' => $attendance->clock_in_address,
            'clock_out_address' => $attendance->clock_out_address,
        ];
    }

    private function requestPayload(EmployeeRequest $request): array
    {
        return [
            'id' => $request->id,
            'type' => $request->type,
            'status' => $request->status,
            'title' => $request->title,
            'start_date' => $this->date($request->start_date),
            'end_date' => $this->date($request->end_date),
            'total_days' => $request->total_days !== null ? (float) $request->total_days : null,
            'description' => $request->description,
            'payload' => $request->payload ?? [],
            'created_at' => $this->datetime($request->created_at),
        ];
    }

    private function reimbursementPayload(EmployeeReimbursement $reimbursement): array
    {
        return [
            'id' => $reimbursement->id,
            'category' => $reimbursement->category,
            'title' => $reimbursement->title,
            'amount' => (float) $reimbursement->amount,
            'expense_date' => $this->date($reimbursement->expense_date),
            'description' => $reimbursement->description,
            'receipt_reference' => $reimbursement->receipt_reference,
            'status' => $reimbursement->status,
            'payment_method' => $reimbursement->payment_method,
            'created_at' => $this->datetime($reimbursement->created_at),
        ];
    }

    private function payrollPayload(EmployeePayroll $payroll): array
    {
        return [
            'id' => $payroll->id,
            'period_month' => $this->date($payroll->period_month),
            'basic_salary' => (float) $payroll->basic_salary,
            'allowance' => (float) $payroll->allowance,
            'deduction' => (float) $payroll->deduction,
            'reimbursement' => (float) $payroll->reimbursement,
            'gross_salary' => (float) $payroll->gross_salary,
            'overtime_amount' => (float) $payroll->overtime_amount,
            'bonus' => (float) $payroll->bonus,
            'thr' => (float) $payroll->thr,
            'tax' => (float) $payroll->tax,
            'marketing_commission' => (float) $payroll->marketing_commission,
            'production_incentive' => (float) $payroll->production_incentive,
            'attendance_allowance' => (float) $payroll->attendance_allowance,
            'attendance_deduction' => (float) $payroll->attendance_deduction,
            'net_salary' => (float) $payroll->net_salary,
            'status' => $payroll->status,
            'paid_at' => $this->datetime($payroll->paid_at),
            'components' => $payroll->components ?? [],
            'notes' => $payroll->notes,
        ];
    }

    private function announcementPayload(EmployeeAnnouncement $announcement, User $user): array
    {
        return [
            'id' => $announcement->id,
            'kind' => $announcement->kind,
            'title' => $announcement->title,
            'summary' => $announcement->summary,
            'body' => $announcement->body,
            'category' => $announcement->category,
            'priority' => $announcement->priority,
            'is_pinned' => (bool) $announcement->is_pinned,
            'publish_at' => $this->datetime($announcement->publish_at),
            'read' => (bool) $announcement->readFor($user),
        ];
    }

    private function calendarEventsFor(User $user, EmployeeProfile $profile)
    {
        $start = now()->copy()->subMonthsNoOverflow(2)->startOfMonth();
        $end = now()->copy()->addMonthsNoOverflow(6)->endOfMonth();

        $manual = CalendarEvent::query()
            ->where(function ($query) use ($start, $end) {
                $query
                    ->whereBetween('start_at', [$start, $end])
                    ->orWhereBetween('end_at', [$start, $end])
                    ->orWhere(function ($inner) use ($start, $end) {
                        $inner->whereNotNull('end_at')->where('start_at', '<=', $start)->where('end_at', '>=', $end);
                    });
            })
            ->whereIn('audience', array_unique(array_filter(['all', 'all_systems', 'employee', $user->role, $profile->department])))
            ->orderBy('start_at')
            ->limit(30)
            ->get()
            ->map(fn (CalendarEvent $event) => [
                'id' => 'calendar-'.$event->id,
                'type' => $event->type ?: 'event',
                'title' => $event->title,
                'start_date' => $this->date($event->start_at),
                'end_date' => $this->date($event->end_at ?: $event->start_at),
                'start_time' => $event->all_day ? '' : $event->start_at?->format('H:i'),
                'end_time' => $event->all_day ? '' : $event->end_at?->format('H:i'),
                'description' => $event->description,
                'location' => $event->location,
                'status' => 'scheduled',
                'all_day' => (bool) $event->all_day,
                'source' => $event->source ?: 'erp',
            ]);

        $requests = $profile->requests()
            ->whereNotNull('start_date')
            ->where(function ($query) use ($start, $end) {
                $query
                    ->whereBetween('start_date', [$start->toDateString(), $end->toDateString()])
                    ->orWhereBetween('end_date', [$start->toDateString(), $end->toDateString()]);
            })
            ->latest('start_date')
            ->limit(30)
            ->get()
            ->map(fn (EmployeeRequest $request) => [
                'id' => 'request-'.$request->id,
                'type' => $request->type ?: 'event',
                'title' => $request->title,
                'start_date' => $this->date($request->start_date),
                'end_date' => $this->date($request->end_date ?: $request->start_date),
                'start_time' => $request->payload['start_time'] ?? $request->payload['clock_in_at'] ?? '',
                'end_time' => $request->payload['end_time'] ?? $request->payload['clock_out_at'] ?? '',
                'description' => $request->description,
                'location' => $request->payload['travel_destination'] ?? $request->payload['address'] ?? '',
                'status' => $request->status,
                'all_day' => ! in_array($request->type, ['overtime', 'manual_attendance'], true),
                'source' => 'employee_request',
            ]);

        $payrolls = $profile->payrolls()
            ->whereBetween('period_month', [$start->toDateString(), $end->toDateString()])
            ->latest('period_month')
            ->limit(12)
            ->get()
            ->map(fn (EmployeePayroll $payroll) => [
                'id' => 'payroll-'.$payroll->id,
                'type' => 'payroll',
                'title' => 'Payroll '.$this->monthLabel($payroll->period_month),
                'start_date' => $this->date($payroll->period_month),
                'end_date' => $this->date($payroll->period_month),
                'description' => 'Status payroll: '.strtoupper($payroll->status),
                'location' => '',
                'status' => $payroll->status,
                'all_day' => true,
                'source' => 'payroll',
            ]);

        return collect($manual->all())
            ->merge($requests->all())
            ->merge($payrolls->all())
            ->sortBy('start_date')
            ->values();
    }

    private function profileFor(User $user): EmployeeProfile
    {
        return EmployeeProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'employee_no' => 'EMP-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT),
                'employment_status' => 'active',
                'department' => 'General',
                'position' => 'Employee',
                'bank_account_name' => $user->name,
            ]
        );
    }

    private function attendanceLocationPolicyFor(EmployeeProfile $profile): array
    {
        $location = $profile->settings['attendance_location'] ?? [];
        $enabled = filter_var($location['enabled'] ?? false, FILTER_VALIDATE_BOOL);
        $latitude = $location['latitude'] ?? null;
        $longitude = $location['longitude'] ?? null;
        $radius = (int) ($location['radius_meters'] ?? 0);

        $isUsable = $enabled && is_numeric($latitude) && is_numeric($longitude) && $radius > 0;

        return [
            'enabled' => $isUsable,
            'name' => $location['name'] ?? $profile->work_location,
            'latitude' => $isUsable ? (float) $latitude : null,
            'longitude' => $isUsable ? (float) $longitude : null,
            'radius_meters' => $isUsable ? $radius : null,
            'address' => $location['address'] ?? null,
            'message' => $isUsable
                ? 'Presensi hanya valid dalam radius '.$radius.' meter dari '.($location['name'] ?? 'lokasi kerja').'.'
                : 'Presensi tidak dibatasi radius lokasi.',
        ];
    }

    private function attendanceLocationCheck(EmployeeProfile $profile, array $data): array
    {
        $policy = $this->attendanceLocationPolicyFor($profile);

        if (! $policy['enabled']) {
            return array_merge($policy, [
                'allowed' => true,
                'distance_meters' => null,
                'message' => 'Presensi diterima. Pegawai ini tidak dibatasi radius lokasi.',
            ]);
        }

        if (! is_numeric($data['latitude'] ?? null) || ! is_numeric($data['longitude'] ?? null)) {
            return array_merge($policy, [
                'allowed' => false,
                'distance_meters' => null,
                'message' => 'Lokasi browser belum terbaca. Izinkan akses lokasi untuk melakukan presensi.',
            ]);
        }

        $distance = $this->distanceMeters(
            (float) $data['latitude'],
            (float) $data['longitude'],
            (float) $policy['latitude'],
            (float) $policy['longitude']
        );

        $allowed = $distance <= (float) $policy['radius_meters'];

        return array_merge($policy, [
            'allowed' => $allowed,
            'distance_meters' => round($distance, 2),
            'actual_latitude' => (float) $data['latitude'],
            'actual_longitude' => (float) $data['longitude'],
            'message' => $allowed
                ? 'Presensi diterima dalam radius lokasi. Jarak '.number_format($distance, 0, ',', '.').' meter.'
                : 'Lokasi di luar radius presensi. Jarak '.number_format($distance, 0, ',', '.').' meter dari batas '.$policy['radius_meters'].' meter.',
        ]);
    }

    private function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    private function hoursBetween(string $startDate, string $startTime, string $endDate, string $endTime): float
    {
        $start = Carbon::parse($startDate.' '.$startTime);
        $end = Carbon::parse($endDate.' '.$endTime);

        if ($end->lte($start)) {
            $end->addDay();
        }

        return round($start->diffInMinutes($end) / 60, 2);
    }

    private function date($value): string
    {
        return $value ? Carbon::parse($value)->toDateString() : '';
    }

    private function datetime($value): string
    {
        return $value ? Carbon::parse($value)->toIso8601String() : '';
    }

    private function monthLabel($value): string
    {
        return $value ? Carbon::parse($value)->translatedFormat('M Y') : '';
    }
}
