<?php

namespace App\Http\Controllers;

use App\Models\EmployeeAnnouncement;
use App\Models\EmployeeAttendance;
use App\Models\EmployeePayroll;
use App\Models\EmployeeProfile;
use App\Models\EmployeeReimbursement;
use App\Models\EmployeeRequest;
use App\Models\User;
use App\Services\EmployeeRequestIntegrationService;
use App\Services\ErpNotificationService;
use App\Support\ErpViewData;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmployeePortalController extends Controller
{
    public function index(Request $request)
    {
        $profile = $this->profileFor($request->user());
        $today = now()->toDateString();
        $todayAttendance = EmployeeAttendance::where('user_id', $request->user()->id)
            ->where('attendance_date', $today)
            ->first();

        $announcements = EmployeeAnnouncement::query()
            ->published()
            ->visibleToUser($request->user())
            ->with(['reads' => fn ($query) => $query->where('user_id', $request->user()->id)])
            ->orderByDesc('is_pinned')
            ->latest('publish_at')
            ->limit(6)
            ->get();

        return view('employee.portal', array_merge(ErpViewData::layout(), [
            'profile' => $profile,
            'attendanceLocationPolicy' => $this->attendanceLocationPolicyFor($profile),
            'todayAttendance' => $todayAttendance,
            'recentAttendances' => $profile->attendances()->latest('attendance_date')->limit(7)->get(),
            'requests' => $profile->requests()->latest()->limit(8)->get(),
            'reimbursements' => $profile->reimbursements()->latest()->limit(8)->get(),
            'payrolls' => $profile->payrolls()->latest('period_month')->limit(8)->get(),
            'latestPayroll' => $profile->payrolls()->latest('period_month')->first(),
            'announcements' => $announcements,
        ]));
    }

    public function updateProfile(Request $request, ErpNotificationService $notifications)
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
            'phone' => $data['phone'] ?? null,
            'birth_place' => $data['birth_place'] ?? null,
            'birth_date' => $data['birth_date'] ?? null,
            'gender' => $data['gender'] ?? null,
            'address' => $data['address'] ?? null,
            'bank_name' => $data['bank_name'] ?? null,
            'bank_account_number' => $data['bank_account_number'] ?? null,
            'bank_account_name' => $data['bank_account_name'] ?? null,
            'department' => $data['department'] ?? $profile->department,
            'position' => $data['position'] ?? $profile->position,
            'manager_name' => $data['manager_name'] ?? $profile->manager_name,
            'work_location' => $data['work_location'] ?? $profile->work_location,
            'personal_data' => [
                'identity_no' => $data['identity_no'] ?? null,
                'marital_status' => $data['marital_status'] ?? null,
            ],
            'education_data' => [
                'level' => $data['education_level'] ?? null,
                'institution' => $data['education_institution'] ?? null,
                'major' => $data['education_major'] ?? null,
                'graduation_year' => $data['graduation_year'] ?? null,
            ],
            'emergency_contact' => [
                'name' => $data['emergency_name'] ?? null,
                'relation' => $data['emergency_relation'] ?? null,
                'phone' => $data['emergency_phone'] ?? null,
            ],
            'job_data' => [
                'contract_type' => $data['contract_type'] ?? null,
                'shift' => $data['shift'] ?? null,
            ],
            'experience_data' => [
                'summary' => $data['experience_summary'] ?? null,
            ],
        ]);

        $changeRequest = EmployeeRequest::create([
            'employee_profile_id' => $profile->id,
            'user_id' => $request->user()->id,
            'type' => 'data_change',
            'status' => 'submitted',
            'title' => 'Perubahan Data Pekerja',
            'description' => 'Employee memperbarui data pribadi, pendidikan, kontak darurat, pekerjaan, atau pengalaman.',
            'payload' => $data,
        ]);

        $notifications->notifyPrivilege('employee.manage', [
            'type' => 'employee.profile.updated',
            'severity' => 'info',
            'subject_user_id' => $request->user()->id,
            'module' => 'employee',
            'title' => 'Perubahan data pekerja',
            'body' => $request->user()->name.' memperbarui data pekerja dan membutuhkan review HR.',
            'action_url' => route('settings.employees'),
            'data' => ['employee_request_id' => $changeRequest->id],
        ], $request->user());

        return back()->with('success', 'Data pekerja tersimpan dan masuk daftar review HR.');
    }

    public function clockIn(Request $request, ErpNotificationService $notifications)
    {
        $profile = $this->profileFor($request->user());
        $data = $this->attendanceLocationData($request);

        $attendance = EmployeeAttendance::where('user_id', $request->user()->id)
            ->where('attendance_date', now()->toDateString())
            ->first();

        if ($attendance?->clock_in_at) {
            return back()->withErrors(['attendance' => 'Presensi masuk hari ini sudah tercatat.']);
        }

        $locationCheck = $this->attendanceLocationCheck($profile, $data);

        if (! $locationCheck['allowed']) {
            return back()->withErrors(['attendance' => $locationCheck['message']]);
        }

        $attendance = $attendance ?: EmployeeAttendance::firstOrCreate(
            ['user_id' => $request->user()->id, 'attendance_date' => now()->toDateString()],
            [
                'employee_profile_id' => $profile->id,
                'status' => 'present',
            ]
        );

        $attendance->update([
            'employee_profile_id' => $profile->id,
            'source' => 'device',
            'device_id' => $data['device_id'] ?? null,
            'phone_number' => $data['phone_number'] ?? $profile->phone,
            'clock_in_at' => now(),
            'clock_in_lat' => $data['latitude'] ?? null,
            'clock_in_lng' => $data['longitude'] ?? null,
            'clock_in_address' => $data['address'] ?? null,
            'approval_status' => 'approved',
            'notes' => $data['notes'] ?? null,
            'metadata' => array_merge($attendance->metadata ?? [], [
                'clock_in_location_check' => $locationCheck,
            ]),
        ]);

        $notifications->notifyPrivilege('employee.manage', [
            'type' => 'employee.attendance.clock_in',
            'severity' => 'success',
            'subject_user_id' => $request->user()->id,
            'module' => 'employee',
            'title' => 'Presensi masuk',
            'body' => $request->user()->name.' clock in pada '.now()->format('H:i').'.',
            'action_url' => route('settings.employees'),
            'data' => ['attendance_id' => $attendance->id],
        ], $request->user());

        return back()->with('success', 'Presensi masuk berhasil dicatat.');
    }

    public function clockOut(Request $request, ErpNotificationService $notifications)
    {
        $profile = $this->profileFor($request->user());
        $data = $this->attendanceLocationData($request);

        $attendance = EmployeeAttendance::where('user_id', $request->user()->id)
            ->where('attendance_date', now()->toDateString())
            ->first();

        if (! $attendance || ! $attendance->clock_in_at) {
            return back()->withErrors(['attendance' => 'Presensi masuk belum tercatat.']);
        }

        if ($attendance->clock_out_at) {
            return back()->withErrors(['attendance' => 'Presensi pulang hari ini sudah tercatat.']);
        }

        $locationCheck = $this->attendanceLocationCheck($profile, $data);

        if (! $locationCheck['allowed']) {
            return back()->withErrors(['attendance' => $locationCheck['message']]);
        }

        $attendance->update([
            'employee_profile_id' => $profile->id,
            'source' => $attendance->source ?: 'device',
            'device_id' => $attendance->device_id ?: ($data['device_id'] ?? null),
            'phone_number' => $attendance->phone_number ?: ($data['phone_number'] ?? $profile->phone),
            'clock_out_at' => now(),
            'clock_out_lat' => $data['latitude'] ?? null,
            'clock_out_lng' => $data['longitude'] ?? null,
            'clock_out_address' => $data['address'] ?? null,
            'approval_status' => $attendance->approval_status ?: 'approved',
            'notes' => $data['notes'] ?? $attendance->notes,
            'metadata' => array_merge($attendance->metadata ?? [], [
                'clock_out_location_check' => $locationCheck,
            ]),
        ]);

        $notifications->notifyPrivilege('employee.manage', [
            'type' => 'employee.attendance.clock_out',
            'severity' => 'info',
            'subject_user_id' => $request->user()->id,
            'module' => 'employee',
            'title' => 'Presensi pulang',
            'body' => $request->user()->name.' clock out pada '.now()->format('H:i').'.',
            'action_url' => route('settings.employees'),
            'data' => ['attendance_id' => $attendance->id],
        ], $request->user());

        return back()->with('success', 'Presensi pulang berhasil dicatat.');
    }

    public function attendanceLocationPolicy(Request $request)
    {
        return response()->json($this->attendanceLocationPolicyFor($this->profileFor($request->user())));
    }

    public function storeRequest(Request $request, ErpNotificationService $notifications, EmployeeRequestIntegrationService $requestIntegrator)
    {
        $profile = $this->profileFor($request->user());
        $data = $request->validate([
            'type' => ['required', Rule::in(['leave', 'permit', 'sick', 'overtime', 'manual_attendance', 'training', 'business_trip', 'data_change', 'information'])],
            'title' => ['required', 'string', 'max:180'],
            'start_date' => ['nullable', 'date', Rule::requiredIf(fn () => in_array($request->input('type'), ['leave', 'permit', 'sick', 'overtime', 'manual_attendance', 'training', 'business_trip'], true))],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'start_time' => ['nullable', 'date_format:H:i', Rule::requiredIf(fn () => in_array($request->input('type'), ['overtime', 'manual_attendance'], true))],
            'end_time' => ['nullable', 'date_format:H:i', Rule::requiredIf(fn () => in_array($request->input('type'), ['overtime', 'manual_attendance'], true))],
            'total_days' => ['nullable', 'numeric', 'min:0'],
            'travel_destination' => ['nullable', 'string', 'max:180', Rule::requiredIf(fn () => $request->input('type') === 'business_trip')],
            'travel_purpose' => ['nullable', 'string', 'max:500'],
            'transport_mode' => ['nullable', 'string', 'max:120'],
            'estimated_amount' => ['nullable', 'numeric', 'min:0'],
            'description' => ['nullable', 'string', 'max:1600'],
        ]);

        $totalDays = $data['total_days'] ?? null;
        $totalHours = null;

        if (in_array($data['type'], ['overtime', 'manual_attendance'], true)) {
            $totalHours = $this->hoursBetween(
                $data['start_date'],
                $data['start_time'],
                $data['end_date'] ?? $data['start_date'],
                $data['end_time']
            );
            $totalDays = $data['type'] === 'overtime' ? $totalHours : ($totalDays ?: $totalHours);
        }

        if (! $totalDays && ! empty($data['start_date']) && ! empty($data['end_date'])) {
            $totalDays = Carbon::parse($data['start_date'])->diffInDays(Carbon::parse($data['end_date'])) + 1;
        }

        $employeeRequest = EmployeeRequest::create([
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
                'source' => 'employee_portal',
                'start_time' => $data['start_time'] ?? null,
                'end_time' => $data['end_time'] ?? null,
                'total_hours' => $totalHours,
                'travel_destination' => $data['type'] === 'business_trip' ? ($data['travel_destination'] ?? null) : null,
                'travel_purpose' => $data['type'] === 'business_trip' ? ($data['travel_purpose'] ?? null) : null,
                'transport_mode' => $data['type'] === 'business_trip' ? ($data['transport_mode'] ?? null) : null,
                'estimated_amount' => $data['type'] === 'business_trip' ? ($data['estimated_amount'] ?? null) : null,
                'attendance_date' => $data['type'] === 'manual_attendance' ? ($data['start_date'] ?? null) : null,
                'clock_in_at' => $data['type'] === 'manual_attendance' ? ($data['start_time'] ?? null) : null,
                'clock_out_at' => $data['type'] === 'manual_attendance' ? ($data['end_time'] ?? null) : null,
            ], fn ($value) => $value !== null && $value !== ''),
        ]);
        $requestIntegrator->syncToHris($employeeRequest->fresh(['profile.managerProfile.user', 'user']), $request->user());

        $notifications->notifyPrivilege('employee.manage', [
            'type' => 'employee.request.submitted',
            'severity' => 'warning',
            'subject_user_id' => $request->user()->id,
            'module' => 'employee',
            'title' => 'Pengajuan employee baru',
            'body' => $request->user()->name.' membuat pengajuan '.$employeeRequest->title.'.',
            'action_url' => route('settings.employees'),
            'data' => ['employee_request_id' => $employeeRequest->id],
        ], $request->user());

        return back()->with('success', 'Pengajuan berhasil dikirim ke HR.');
    }

    public function storeManualAttendance(Request $request, ErpNotificationService $notifications, EmployeeRequestIntegrationService $requestIntegrator)
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

        $employeeRequest = EmployeeRequest::create([
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
                'source' => 'manual_attendance_form',
            ],
        ]);
        $requestIntegrator->syncToHris($employeeRequest->fresh(['profile.managerProfile.user', 'user']), $request->user());

        $notifications->notifyPrivilege('employee.manage', [
            'type' => 'employee.attendance.manual.submitted',
            'severity' => 'warning',
            'subject_user_id' => $request->user()->id,
            'module' => 'employee',
            'title' => 'Pengajuan presensi manual',
            'body' => $request->user()->name.' mengajukan presensi manual tanggal '.$data['attendance_date'].'.',
            'action_url' => route('settings.employees'),
            'data' => ['employee_request_id' => $employeeRequest->id],
        ], $request->user());

        return back()->with('success', 'Pengajuan presensi manual dikirim ke HR.');
    }

    public function storeReimbursement(Request $request, ErpNotificationService $notifications)
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

        $reimbursement = EmployeeReimbursement::create([
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

        $notifications->notifyPrivilege('employee.manage', [
            'type' => 'employee.reimbursement.submitted',
            'severity' => 'warning',
            'subject_user_id' => $request->user()->id,
            'module' => 'employee',
            'title' => 'Reimburse baru',
            'body' => $request->user()->name.' mengajukan reimburse Rp '.number_format((float) $reimbursement->amount, 0, ',', '.').'.',
            'action_url' => route('settings.employees'),
            'data' => ['employee_reimbursement_id' => $reimbursement->id],
        ], $request->user());

        return back()->with('success', 'Reimburse berhasil dikirim ke HR.');
    }

    public function payslip(Request $request, EmployeePayroll $payroll)
    {
        abort_unless(
            $payroll->user_id === $request->user()->id || $request->user()->hasPrivilege('employee.manage'),
            404
        );

        return view('employee.payslip', array_merge(ErpViewData::layout(), [
            'payroll' => $payroll->load(['user', 'profile']),
        ]));
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

    private function attendanceLocationData(Request $request): array
    {
        return $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'address' => ['nullable', 'string', 'max:1000'],
            'device_id' => ['nullable', 'string', 'max:120'],
            'phone_number' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function attendanceLocationPolicyFor(EmployeeProfile $profile): array
    {
        $location = $profile->settings['attendance_location'] ?? [];
        $enabled = filter_var($location['enabled'] ?? false, FILTER_VALIDATE_BOOL);
        $latitude = $location['latitude'] ?? null;
        $longitude = $location['longitude'] ?? null;
        $radius = (int) ($location['radius_meters'] ?? 0);

        $isUsable = $enabled
            && is_numeric($latitude)
            && is_numeric($longitude)
            && $radius > 0;

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
}
