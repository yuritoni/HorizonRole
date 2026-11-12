<?php

namespace App\Http\Controllers;

use App\Models\Privilege;
use App\Models\User;
use App\Models\UserPrivilege;
use App\Services\ErpNotificationService;
use App\Support\ErpViewData;
use App\Support\PrivilegeRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PrivilegeManagementController extends Controller
{
    public function index()
    {
        PrivilegeRegistry::syncDefaults();

        $privileges = Privilege::orderBy('group')->orderBy('module')->orderBy('action')->orderBy('label')->get();
        $rolePermissions = DB::table('role_privileges')
            ->join('privileges', 'privileges.id', '=', 'role_privileges.privilege_id')
            ->select('role_privileges.role', 'privileges.key')
            ->get()
            ->groupBy('role')
            ->map(fn ($items) => $items->pluck('key')->values());

        return view('settings.privileges', array_merge(ErpViewData::layout(), [
            'roles' => PrivilegeRegistry::roles(),
            'privileges' => $privileges,
            'privilegeGroups' => $privileges->groupBy('group'),
            'rolePermissions' => $rolePermissions,
            'users' => User::with('userPrivilegeOverrides.privilege')->orderBy('role')->orderBy('name')->get(),
        ]));
    }

    public function updateRole(Request $request, string $role)
    {
        abort_unless(array_key_exists($role, PrivilegeRegistry::roles()), 404);

        $validKeys = Privilege::pluck('key')->all();
        $data = $request->validate([
            'privileges' => ['array'],
            'privileges.*' => ['string', Rule::in($validKeys)],
        ]);

        $selectedKeys = $data['privileges'] ?? [];

        if ($role === 'admin') {
            $selectedKeys = array_values(array_unique(array_merge($selectedKeys, [
                'dashboard.view',
                'users.manage',
                'privileges.manage',
            ])));
        }

        $privilegeIds = Privilege::whereIn('key', $selectedKeys)->pluck('id');

        DB::transaction(function () use ($role, $privilegeIds) {
            DB::table('role_privileges')->where('role', $role)->delete();

            $rows = $privilegeIds->map(fn ($id) => [
                'role' => $role,
                'privilege_id' => $id,
                'created_at' => now(),
                'updated_at' => now(),
            ])->all();

            if ($rows) {
                DB::table('role_privileges')->insert($rows);
            }
        });

        app(ErpNotificationService::class)->notifyAdmins([
            'type' => 'privilege.role',
            'severity' => 'warning',
            'title' => 'Privilege role diperbarui',
            'body' => 'Hak akses role '.strtoupper($role).' diperbarui oleh '.$request->user()->name.'.',
            'action_url' => route('settings.privileges'),
        ], $request->user());

        return back()->with('success', 'Privilege role '.strtoupper($role).' berhasil diperbarui.');
    }

    public function updateUser(Request $request, User $user)
    {
        $validKeys = Privilege::pluck('key')->all();
        $data = $request->validate([
            'grants' => ['array'],
            'grants.*' => ['string', Rule::in($validKeys)],
            'denies' => ['array'],
            'denies.*' => ['string', Rule::in($validKeys)],
        ]);

        $grants = collect($data['grants'] ?? [])->unique();
        $denies = collect($data['denies'] ?? [])->unique();
        $overlap = $grants->intersect($denies);

        if ($overlap->isNotEmpty()) {
            return back()->withErrors(['privileges' => 'Privilege yang sama tidak boleh Grant dan Deny sekaligus.']);
        }

        DB::transaction(function () use ($user, $grants, $denies) {
            $user->userPrivilegeOverrides()->delete();

            $grantRows = Privilege::whereIn('key', $grants)->pluck('id')->map(fn ($id) => [
                'user_id' => $user->id,
                'privilege_id' => $id,
                'effect' => 'grant',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $denyRows = Privilege::whereIn('key', $denies)->pluck('id')->map(fn ($id) => [
                'user_id' => $user->id,
                'privilege_id' => $id,
                'effect' => 'deny',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $rows = $grantRows->merge($denyRows)->all();

            if ($rows) {
                UserPrivilege::insert($rows);
            }
        });

        app(ErpNotificationService::class)->notifyUsers($this->adminAndUser($user), [
            'type' => 'privilege.user',
            'severity' => 'warning',
            'subject_user_id' => $user->id,
            'title' => 'Override privilege user diperbarui',
            'body' => 'Akses khusus untuk '.$user->name.' diperbarui oleh '.$request->user()->name.'.',
            'action_url' => route('settings.privileges'),
        ], $request->user());

        return back()->with('success', 'Override privilege '.$user->name.' berhasil diperbarui.');
    }

    public function resetUser(Request $request, User $user)
    {
        $user->userPrivilegeOverrides()->delete();

        app(ErpNotificationService::class)->notifyUsers($this->adminAndUser($user), [
            'type' => 'privilege.user.reset',
            'severity' => 'info',
            'subject_user_id' => $user->id,
            'title' => 'Override privilege user direset',
            'body' => 'Akses '.$user->name.' kembali mengikuti role '.strtoupper($user->role).'.',
            'action_url' => route('settings.privileges'),
        ], $request->user());

        return back()->with('success', 'Override privilege '.$user->name.' direset.');
    }

    private function adminAndUser(User $user)
    {
        return User::where('account_status', 'active')
            ->get()
            ->filter(fn (User $recipient) => $recipient->hasPrivilege('privileges.manage'))
            ->push($user)
            ->unique('id')
            ->values();
    }
}
