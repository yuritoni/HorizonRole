@extends('layouts.guest')

@section('content')
    <p class="eyebrow">Register</p>
    <h2>Buat akun baru</h2>
    <p class="muted">Akun baru dibuat sebagai pending sampai email berhasil dikonfirmasi.</p>

    <form method="post" action="{{ route('register.store') }}" class="form-stack">
        @csrf
        <label>
            <span>Nama</span>
            <input type="text" name="name" value="{{ old('name') }}" required autofocus>
        </label>
        <label>
            <span>Email</span>
            <input type="email" name="email" value="{{ old('email') }}" required>
        </label>
        <label>
            <span>Role</span>
            <select name="role" required>
                <option value="member" @selected(old('role') === 'member')>Member</option>
                <option value="user" @selected(old('role') === 'user')>User</option>
                <option value="employee" @selected(old('role') === 'employee')>Employee</option>
            </select>
        </label>
        <label>
            <span>Password</span>
            <input type="password" name="password" required>
        </label>
        <label>
            <span>Konfirmasi Password</span>
            <input type="password" name="password_confirmation" required>
        </label>
        <button class="primary-button full" type="submit"><x-icon name="user-plus" />Register</button>
    </form>

    <p class="auth-switch">Sudah punya akun? <a href="{{ route('login') }}"><x-icon name="log-in" />Login</a></p>
@endsection
