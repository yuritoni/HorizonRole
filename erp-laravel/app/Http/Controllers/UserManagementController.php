<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ErpNotificationService;
use App\Support\ErpViewData;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index()
    {
        return view('settings.users', array_merge(ErpViewData::layout(), [
            'users' => User::orderBy('role')->orderBy('name')->get(),
            'roles' => ['admin', 'member', 'user', 'employee'],
        ]));
    }

    public function updateRole(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => ['required', Rule::in(['admin', 'member', 'user', 'employee'])],
        ]);

        if ($request->user()->id === $user->id && $user->role === 'admin' && $data['role'] !== 'admin') {
            return back()->withErrors(['role' => 'Admin tidak bisa menurunkan role akun sendiri.']);
        }

        $oldRole = $user->role;
        $user->update(['role' => $data['role']]);

        app(ErpNotificationService::class)->notifyUsers($this->adminAndUser($user), [
            'type' => 'user.role',
            'severity' => 'warning',
            'subject_user_id' => $user->id,
            'title' => 'Role user diperbarui',
            'body' => $user->name.' berubah dari '.strtoupper($oldRole).' menjadi '.strtoupper($user->role).'.',
            'action_url' => route('settings.users'),
        ], $request->user());

        return back()->with('success', 'Role '.$user->name.' berhasil diperbarui.');
    }

    public function updateStatus(Request $request, User $user)
    {
        $data = $request->validate([
            'account_status' => ['required', Rule::in(['pending', 'active', 'inactive'])],
        ]);

        if ($request->user()->id === $user->id && $data['account_status'] !== 'active') {
            return back()->withErrors(['account_status' => 'Admin tidak bisa menonaktifkan akun sendiri.']);
        }

        $payload = ['account_status' => $data['account_status']];

        if ($data['account_status'] === 'active') {
            $payload['email_verified_at'] = $user->email_verified_at ?: now();
            $payload['activated_at'] = $user->activated_at ?: now();
        }

        if ($data['account_status'] !== 'active') {
            $payload['activated_at'] = null;
        }

        $oldStatus = $user->account_status;
        $user->update($payload);

        app(ErpNotificationService::class)->notifyUsers($this->adminAndUser($user), [
            'type' => 'user.status',
            'severity' => $user->account_status === 'active' ? 'success' : 'warning',
            'subject_user_id' => $user->id,
            'title' => 'Status akun diperbarui',
            'body' => $user->name.' berubah dari '.strtoupper($oldStatus).' menjadi '.strtoupper($user->account_status).'.',
            'action_url' => route('settings.users'),
        ], $request->user());

        return back()->with('success', 'Status akun '.$user->name.' berhasil diperbarui.');
    }

    private function adminAndUser(User $user)
    {
        return User::where('account_status', 'active')
            ->get()
            ->filter(fn (User $recipient) => $recipient->hasPrivilege('users.manage'))
            ->push($user)
            ->unique('id')
            ->values();
    }
}
