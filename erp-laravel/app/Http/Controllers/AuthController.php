<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\ErpNotificationService;
use App\Support\ErpModules;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()
                ->withErrors(['email' => 'Email atau password tidak sesuai.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        $user = $request->user();

        if ($user->account_status === 'inactive') {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors(['email' => 'Akun Anda sedang dinonaktifkan. Hubungi admin.'])
                ->onlyInput('email');
        }

        if (! $user->hasVerifiedEmail()) {
            return redirect()->route('verification.notice');
        }

        if ($user->account_status === 'pending') {
            $user->activate();

            app(ErpNotificationService::class)->notifyAdmins([
                'type' => 'user.activated',
                'severity' => 'success',
                'subject_user_id' => $user->id,
                'title' => 'Akun aktif: '.$user->name,
                'body' => $user->email.' berhasil verifikasi email dan otomatis aktif.',
                'action_url' => route('settings.users'),
            ], $user);
        }

        return redirect()->intended($this->landingUrl($user));
    }

    public function showRegister()
    {
        return view('auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:180', 'unique:users,email'],
            'role' => ['required', Rule::in(['member', 'user', 'employee'])],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'account_status' => 'pending',
            'password' => Hash::make($data['password']),
        ]);

        event(new Registered($user));

        app(ErpNotificationService::class)->notifyAdmins([
            'type' => 'user.registered',
            'severity' => 'warning',
            'subject_user_id' => $user->id,
            'title' => 'Registrasi user baru',
            'body' => $user->name.' mendaftar sebagai '.strtoupper($user->role).' dan menunggu konfirmasi email.',
            'action_url' => route('settings.users'),
        ], $user);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function landingUrl(User $user): string
    {
        if ($user->hasPrivilege('dashboard.view')) {
            return route('dashboard');
        }

        if ($user->hasPrivilege('employee.portal')) {
            return route('employee.portal');
        }

        foreach (array_keys(ErpModules::all()) as $module) {
            if ($user->hasPrivilege("modules.{$module}.view")) {
                return route('records.index', $module);
            }
        }

        if ($user->hasPrivilege('notifications.view')) {
            return route('notifications.index');
        }

        return route('login');
    }
}
