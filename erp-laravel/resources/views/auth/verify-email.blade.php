@extends('layouts.guest')

@section('content')
    <p class="eyebrow">Aktivasi Akun</p>
    <h2>Konfirmasi email Anda</h2>
    <p class="muted">
        Akun {{ auth()->user()->email }} sudah dibuat, tetapi belum aktif. Klik link verifikasi yang dikirim ke email untuk mengaktifkan akun.
    </p>

    <div class="activation-box">
        <strong>Status akun</strong>
        <span>{{ strtoupper(auth()->user()->account_status) }}</span>
    </div>

    <form method="post" action="{{ route('verification.send') }}" class="form-stack">
        @csrf
        <button class="primary-button full" type="submit"><x-icon name="mail-check" />Kirim Ulang Link Verifikasi</button>
    </form>

    <form method="post" action="{{ route('logout') }}" class="form-stack">
        @csrf
        <button class="ghost-button full" type="submit"><x-icon name="log-out" />Logout</button>
    </form>
@endsection
