<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    public function notice()
    {
        if (request()->user()->hasVerifiedEmail()) {
            if (request()->user()->account_status === 'pending') {
                request()->user()->activate();
            }

            return redirect()->route('dashboard');
        }

        return view('auth.verify-email');
    }

    public function verify(EmailVerificationRequest $request)
    {
        $request->fulfill();
        $request->user()->activate();

        return redirect()
            ->route('dashboard')
            ->with('success', 'Email berhasil dikonfirmasi dan akun Anda sudah aktif.');
    }

    public function send(Request $request)
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->route('dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('success', 'Link verifikasi baru sudah dikirim.');
    }
}
