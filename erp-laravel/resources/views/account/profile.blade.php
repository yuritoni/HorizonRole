@extends('layouts.app')

@section('title', 'Profil Akun - '.config('app.name'))

@section('content')
    @php($profile = $user->employeeProfile)

    <header class="page-header">
        <div>
            <p class="eyebrow">Akun</p>
            <h1>Profil Saya</h1>
            <p>Ubah email, password, dan photo profile yang dipakai di user account serta data employee terkait.</p>
        </div>
        <div class="profile-card">
            @if($user->photo_path)
                <img class="profile-avatar" src="{{ asset($user->photo_path) }}" alt="{{ $user->name }}">
            @else
                <div class="profile-avatar fallback">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
            @endif
            <div>
                <strong>{{ $user->name }}</strong>
                <span>{{ $profile?->employee_no ?: strtoupper($user->role) }}</span>
            </div>
        </div>
    </header>

    <div class="employee-section-grid">
        <section class="panel">
            <div class="panel-header">
                <h2>Account Login</h2>
                <span>Email, password, photo</span>
            </div>
            <div class="form-panel">
                <form class="record-form" method="post" action="{{ route('account.update') }}" enctype="multipart/form-data">
                    @csrf
                    @method('PATCH')
                    <div class="form-grid">
                        <label>Nama <input name="name" value="{{ old('name', $user->name) }}" required></label>
                        <label>Email <input type="email" name="email" value="{{ old('email', $user->email) }}" required></label>
                        <label>Password baru <input type="password" name="password" autocomplete="new-password"></label>
                        <label>Konfirmasi password <input type="password" name="password_confirmation" autocomplete="new-password"></label>
                        <label class="full">Photo profile <input type="file" name="photo" accept="image/*"></label>
                    </div>
                    <div class="form-actions">
                        <button class="primary-button" type="submit"><x-icon name="save" />Simpan Profil</button>
                    </div>
                </form>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2>Data Employee</h2>
                <span>{{ $profile?->department ?: 'Belum lengkap' }}</span>
            </div>
            <div class="payment-summary">
                <div><span>NIP</span><strong>{{ $profile?->employee_no ?: '-' }}</strong></div>
                <div><span>Departemen</span><strong>{{ $profile?->department ?: '-' }}</strong></div>
                <div><span>Jabatan</span><strong>{{ $profile?->position ?: '-' }}</strong></div>
                <div><span>Atasan</span><strong>{{ $profile?->managerProfile?->user?->name ?: ($profile?->manager_name ?: '-') }}</strong></div>
                <div><span>Cabang</span><strong>{{ $profile?->branch ?: '-' }}</strong></div>
                <div><span>Status</span><strong>{{ strtoupper($profile?->employment_status ?: $user->account_status) }}</strong></div>
            </div>
            @if($user->hasPrivilege('employee.portal'))
                <div class="form-actions payment-actions">
                    <a class="ghost-button" href="{{ route('employee.portal') }}"><x-icon name="user-check" />Buka Employee Portal</a>
                </div>
            @endif
        </section>
    </div>
@endsection
