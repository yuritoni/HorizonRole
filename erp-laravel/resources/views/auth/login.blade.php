@extends('layouts.guest')

@section('content')
    <p class="eyebrow">Masuk</p>
    <h2>Login ke ERP</h2>
    <p class="muted">Gunakan akun yang sudah aktif atau selesaikan konfirmasi email setelah register.</p>

    <form method="post" action="{{ route('login.store') }}" class="form-stack">
        @csrf
        <label>
            <span>Email</span>
            <input type="email" name="email" value="{{ old('email') }}" required autofocus>
        </label>
        <label>
            <span>Password</span>
            <input type="password" name="password" required>
        </label>
        <label class="checkbox-row">
            <input type="checkbox" name="remember" value="1">
            <span>Ingat saya</span>
        </label>
        <button class="primary-button full" type="submit"><x-icon name="log-in" />Login</button>
    </form>

    <p class="auth-switch">Belum punya akun? <a href="{{ route('register') }}"><x-icon name="user-plus" />Register</a></p>
@endsection
