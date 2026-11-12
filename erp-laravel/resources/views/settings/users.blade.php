@extends('layouts.app')

@section('title', 'Pengaturan User - '.config('app.name'))

@section('content')
    <header class="page-header">
        <div>
            <p class="eyebrow">Admin</p>
            <h1>Pengaturan User</h1>
            <p>Kelola role, verifikasi email, dan status aktivasi akun.</p>
        </div>
        <div class="role-card">
            <span>Total user</span>
            <strong>{{ $users->count() }}</strong>
        </div>
    </header>

    <div class="metrics-grid">
        <article class="metric-card">
            <span>Admin</span>
            <strong>{{ $users->where('role', 'admin')->count() }}</strong>
            <small>Akses penuh, termasuk hapus data dan role</small>
        </article>
        <article class="metric-card">
            <span>Member</span>
            <strong>{{ $users->where('role', 'member')->count() }}</strong>
            <small>Bisa tambah, edit, dan advance workflow</small>
        </article>
        <article class="metric-card">
            <span>User</span>
            <strong>{{ $users->where('role', 'user')->count() }}</strong>
            <small>Read only untuk seluruh modul ERP</small>
        </article>
        <article class="metric-card">
            <span>Employee</span>
            <strong>{{ $users->where('role', 'employee')->count() }}</strong>
            <small>Akses Employee Self Service dan notifikasi</small>
        </article>
        <article class="metric-card">
            <span>Pending</span>
            <strong>{{ $users->where('account_status', 'pending')->count() }}</strong>
            <small>Menunggu konfirmasi email atau aktivasi admin</small>
        </article>
    </div>

    <section class="panel">
        <div class="panel-header">
            <h2>User dan Role</h2>
            <span>{{ $users->count() }} akun</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Email</th>
                        <th>Status</th>
                        <th>Dibuat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                        <tr>
                            <td>{{ $user->name }}</td>
                            <td>{{ $user->email }}</td>
                            <td><span class="status">{{ strtoupper($user->role) }}</span></td>
                            <td>
                                @if($user->hasVerifiedEmail())
                                    <span class="status">VERIFIED</span>
                                @else
                                    <span class="status warning">UNVERIFIED</span>
                                @endif
                            </td>
                            <td><span class="status {{ $user->account_status !== 'active' ? 'warning' : '' }}">{{ strtoupper($user->account_status) }}</span></td>
                            <td>{{ $user->created_at->format('d M Y') }}</td>
                            <td>
                                <form class="inline-form" method="post" action="{{ route('settings.users.role', $user) }}">
                                    @csrf
                                    @method('PATCH')
                                    <select name="role">
                                        @foreach($roles as $role)
                                            <option value="{{ $role }}" @selected($user->role === $role)>{{ strtoupper($role) }}</option>
                                        @endforeach
                                    </select>
                                    <button class="ghost-button" type="submit"><x-icon name="refresh-cw" />Update</button>
                                </form>
                                <form class="inline-form" method="post" action="{{ route('settings.users.status', $user) }}">
                                    @csrf
                                    @method('PATCH')
                                    <select name="account_status">
                                        <option value="pending" @selected($user->account_status === 'pending')>PENDING</option>
                                        <option value="active" @selected($user->account_status === 'active')>ACTIVE</option>
                                        <option value="inactive" @selected($user->account_status === 'inactive')>INACTIVE</option>
                                    </select>
                                    <button class="ghost-button" type="submit"><x-icon name="user-check" />Aktivasi</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
