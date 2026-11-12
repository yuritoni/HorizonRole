@extends('layouts.app')

@section('title', 'Privilege - '.config('app.name'))

@section('content')
    <header class="page-header">
        <div>
            <p class="eyebrow">Access Control</p>
            <h1>Management Privilege</h1>
            <p>Kelola akses menu, modul, aksi workflow, payment, dan override khusus per user.</p>
        </div>
        <div class="role-card">
            <span>Total privilege</span>
            <strong>{{ $privileges->count() }}</strong>
        </div>
    </header>

    <div class="metrics-grid">
        @foreach($roles as $roleKey => $roleLabel)
            <article class="metric-card">
                <span>{{ $roleLabel }}</span>
                <strong>{{ ($rolePermissions[$roleKey] ?? collect())->count() }}</strong>
                <small>Privilege aktif untuk role {{ strtoupper($roleKey) }}</small>
            </article>
        @endforeach
        <article class="metric-card">
            <span>Override User</span>
            <strong>{{ $users->sum(fn($user) => $user->userPrivilegeOverrides->count()) }}</strong>
            <small>Grant atau deny khusus di luar role</small>
        </article>
    </div>

    <section class="panel privilege-panel">
        <div class="panel-header">
            <h2>Privilege Role</h2>
            <span>{{ count($roles) }} role</span>
        </div>

        <div class="privilege-tabs">
            @foreach($roles as $roleKey => $roleLabel)
                <form class="privilege-role-card" method="post" action="{{ route('settings.privileges.roles', $roleKey) }}">
                    @csrf
                    @method('PATCH')
                    <div class="privilege-role-header">
                        <div>
                            <strong>{{ $roleLabel }}</strong>
                            <span>{{ strtoupper($roleKey) }}</span>
                        </div>
                        <button class="primary-button" type="submit"><x-icon name="save" />Simpan Role</button>
                    </div>

                    <div class="privilege-groups">
                        @foreach($privilegeGroups as $group => $items)
                            <fieldset class="privilege-group">
                                <legend>{{ $group }}</legend>
                                @foreach($items as $privilege)
                                    @php($checked = ($rolePermissions[$roleKey] ?? collect())->contains($privilege->key))
                                    @php($locked = $roleKey === 'admin' && in_array($privilege->key, ['dashboard.view', 'users.manage', 'privileges.manage'], true))
                                    <label class="privilege-check">
                                        <input
                                            type="checkbox"
                                            name="privileges[]"
                                            value="{{ $privilege->key }}"
                                            @checked($checked || $locked)
                                            @disabled($locked)
                                        >
                                        @if($locked)
                                            <input type="hidden" name="privileges[]" value="{{ $privilege->key }}">
                                        @endif
                                        <span>
                                            <strong>{{ $privilege->label }}</strong>
                                            <small>{{ $privilege->description }}</small>
                                        </span>
                                    </label>
                                @endforeach
                            </fieldset>
                        @endforeach
                    </div>
                </form>
            @endforeach
        </div>
    </section>

    <section class="panel privilege-panel">
        <div class="panel-header">
            <h2>Override User</h2>
            <span>{{ $users->count() }} akun</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>User</th>
                        <th>Role</th>
                        <th>Grant Khusus</th>
                        <th>Deny Khusus</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $user)
                        @php($grantKeys = $user->userPrivilegeOverrides->where('effect', 'grant')->pluck('privilege.key')->filter()->values())
                        @php($denyKeys = $user->userPrivilegeOverrides->where('effect', 'deny')->pluck('privilege.key')->filter()->values())
                        <tr>
                            <td>
                                <strong>{{ $user->name }}</strong><br>
                                <span class="muted">{{ $user->email }}</span>
                            </td>
                            <td><span class="status">{{ strtoupper($user->role) }}</span></td>
                            <td>
                                <form id="user-privilege-{{ $user->id }}" method="post" action="{{ route('settings.privileges.users', $user) }}">
                                    @csrf
                                    @method('PATCH')
                                    <select class="privilege-select" name="grants[]" multiple size="7">
                                        @foreach($privileges as $privilege)
                                            <option value="{{ $privilege->key }}" @selected($grantKeys->contains($privilege->key))>
                                                {{ $privilege->label }}
                                            </option>
                                        @endforeach
                                    </select>
                                </form>
                            </td>
                            <td>
                                <select class="privilege-select" name="denies[]" form="user-privilege-{{ $user->id }}" multiple size="7">
                                    @foreach($privileges as $privilege)
                                        <option value="{{ $privilege->key }}" @selected($denyKeys->contains($privilege->key))>
                                            {{ $privilege->label }}
                                        </option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <div class="row-actions">
                                    <button class="ghost-button" type="submit" form="user-privilege-{{ $user->id }}"><x-icon name="save" />Simpan</button>
                                    <form method="post" action="{{ route('settings.privileges.users.reset', $user) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button class="icon-link danger-text" type="submit"><x-icon name="rotate-ccw" />Reset</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
