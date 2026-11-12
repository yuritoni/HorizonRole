<?php

namespace App\Http\Controllers;

use App\Models\EmployeeProfile;
use App\Models\ErpRecord;
use App\Models\MarketingBranch;
use App\Models\MarketingTracking;
use App\Models\User;
use App\Services\ErpModuleIntegrationService;
use App\Services\ErpNotificationService;
use App\Support\ErpViewData;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class MarketingController extends Controller
{
    public function index()
    {
        $profiles = EmployeeProfile::with(['user', 'managerProfile.user'])
            ->where(function ($query) {
                $query->where('department', 'Marketing')
                    ->orWhere('position', 'like', '%Marketing%');
            })
            ->orderBy('branch')
            ->orderBy('employee_no')
            ->get();

        $trackings = MarketingTracking::with(['profile.user', 'branch'])
            ->latest('tracked_at')
            ->paginate(15);

        $trackingLocations = MarketingTracking::with(['profile.user', 'branch'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->latest('tracked_at')
            ->limit(100)
            ->get();

        return view('marketing.index', array_merge(ErpViewData::layout(), [
            'profiles' => $profiles,
            'branches' => MarketingBranch::orderBy('name')->get(),
            'trackings' => $trackings,
            'trackingLocations' => $trackingLocations,
            'marketingRecords' => ErpRecord::where('module', 'marketing')->latest()->limit(12)->get(),
            'salesFromMarketing' => ErpRecord::where('module', 'sales')
                ->where('extra->integration_source_module', 'marketing')
                ->latest()
                ->limit(12)
                ->get(),
        ]));
    }

    public function storeBranch(Request $request)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:marketing_branches,code'],
            'name' => ['required', 'string', 'max:160'],
            'region' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:1000'],
            'manager_name' => ['nullable', 'string', 'max:160'],
        ]);

        MarketingBranch::create($data + ['is_active' => true]);

        return back()->with('success', 'Cabang marketing berhasil dibuat.');
    }

    public function storeEmployee(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
            'employee_no' => ['nullable', 'string', 'max:80', 'unique:employee_profiles,employee_no'],
            'branch' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'device_id' => ['nullable', 'string', 'max:120'],
            'manager_profile_id' => ['nullable', 'exists:employee_profiles,id'],
            'marketing_target' => ['nullable', 'numeric', 'min:0'],
            'commission_rate' => ['nullable', 'numeric', 'min:0'],
            'base_salary' => ['nullable', 'numeric', 'min:0'],
            'fixed_allowance' => ['nullable', 'numeric', 'min:0'],
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
            'employee_no' => $data['employee_no'] ?: 'MKT-'.now()->format('ym').'-'.str_pad((string) $user->id, 4, '0', STR_PAD_LEFT),
            'department' => 'Marketing',
            'position' => 'Marketing Executive',
            'job_level' => 'Staff',
            'employment_status' => 'active',
            'join_date' => now()->toDateString(),
            'manager_name' => $managerName,
            'branch' => $data['branch'] ?? null,
            'phone' => $data['phone'] ?? null,
            'base_salary' => $data['base_salary'] ?? 0,
            'fixed_allowance' => $data['fixed_allowance'] ?? 0,
            'marketing_target' => $data['marketing_target'] ?? 0,
            'commission_rate' => $data['commission_rate'] ?? 0,
            'settings' => [
                'device_id' => $data['device_id'] ?? null,
                'tracking_phone' => $data['phone'] ?? null,
                'created_from' => 'marketing_management',
            ],
        ]);

        return back()->with('success', 'Pegawai marketing dibuat sebagai data master Employee/SDM dan siap dipakai tracking.');
    }

    public function storeTracking(Request $request, ErpModuleIntegrationService $moduleIntegration, ErpNotificationService $notifications)
    {
        $data = $request->validate([
            'employee_profile_id' => ['required', 'exists:employee_profiles,id'],
            'marketing_branch_id' => ['nullable', 'exists:marketing_branches,id'],
            'tracked_at' => ['nullable', 'date'],
            'device_id' => ['nullable', 'string', 'max:120'],
            'phone_number' => ['nullable', 'string', 'max:40'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'address' => ['nullable', 'string', 'max:1000'],
            'activity_type' => ['required', Rule::in(['customer_visit', 'lead_follow_up', 'telemarketing', 'event', 'check_in'])],
            'customer_name' => ['nullable', 'string', 'max:180'],
            'pipeline_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['reported', 'verified', 'converted'])],
            'notes' => ['nullable', 'string', 'max:1200'],
        ]);

        $profile = EmployeeProfile::with('user')->findOrFail($data['employee_profile_id']);
        $branch = ! empty($data['marketing_branch_id']) ? MarketingBranch::find($data['marketing_branch_id']) : null;

        $tracking = MarketingTracking::create([
            'employee_profile_id' => $profile->id,
            'user_id' => $profile->user_id,
            'marketing_branch_id' => $branch?->id,
            'tracked_at' => ! empty($data['tracked_at']) ? Carbon::parse($data['tracked_at']) : now(),
            'device_id' => $data['device_id'] ?: ($profile->settings['device_id'] ?? null),
            'phone_number' => $data['phone_number'] ?: $profile->phone,
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'address' => $data['address'] ?? null,
            'activity_type' => $data['activity_type'],
            'customer_name' => $data['customer_name'] ?? null,
            'pipeline_amount' => $data['pipeline_amount'] ?? 0,
            'status' => $data['status'],
            'notes' => $data['notes'] ?? null,
            'metadata' => ['source' => 'marketing_management'],
        ]);

        $record = ErpRecord::create([
            'module' => 'marketing',
            'code' => $this->marketingRecordCode(),
            'title' => $tracking->customer_name ?: $profile->user?->name,
            'party' => $branch?->name ?: $profile->branch,
            'category' => str($tracking->activity_type)->replace('_', ' ')->headline()->toString(),
            'amount' => $tracking->pipeline_amount,
            'progress' => $tracking->status === 'converted' ? 100 : ($tracking->status === 'verified' ? 70 : 30),
            'status' => $tracking->status === 'converted' ? 'Converted' : ($tracking->status === 'verified' ? 'Qualified' : 'Lead Captured'),
            'owner' => $profile->user?->name,
            'due_date' => $tracking->tracked_at->copy()->addDays(3)->toDateString(),
            'notes' => $tracking->notes,
            'extra' => [
                'marketing_tracking_id' => $tracking->id,
                'device_id' => $tracking->device_id,
                'phone_number' => $tracking->phone_number,
                'latitude' => $tracking->latitude,
                'longitude' => $tracking->longitude,
                'branch_id' => $branch?->id,
            ],
            'created_by' => $request->user()->id,
        ]);

        $moduleIntegration->syncRecord($record, $request->user());

        $notifications->notifyPrivilege('marketing.manage', [
            'type' => 'marketing.tracking.created',
            'severity' => $tracking->status === 'converted' ? 'success' : 'info',
            'module' => 'marketing',
            'erp_record_id' => $record->id,
            'title' => 'Tracking marketing: '.$profile->user?->name,
            'body' => ($tracking->customer_name ?: 'Aktivitas').' tercatat dari device/no telp '.$tracking->phone_number.'.',
            'action_url' => route('marketing.index'),
        ], $request->user());

        return back()->with('success', 'Tracking marketing tersimpan dan pipeline disinkronkan ke Marketing/Sales.');
    }

    private function marketingRecordCode(): string
    {
        $period = now()->format('ym');
        $sequence = ErpRecord::where('module', 'marketing')->count() + 1;

        do {
            $code = 'MKT-'.$period.'-'.str_pad((string) $sequence, 3, '0', STR_PAD_LEFT);
            $sequence++;
        } while (ErpRecord::where('code', $code)->exists());

        return $code;
    }
}
