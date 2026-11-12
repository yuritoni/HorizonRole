@extends('layouts.app')

@section('title', 'Edit SDM / Employee - '.config('app.name'))

@section('actions')
    <a class="ghost-button" href="{{ route('settings.employees') }}"><x-icon name="arrow-left" />Kembali</a>
@endsection

@section('content')
    @php
        $attendanceLocation = $profile->settings['attendance_location'] ?? [];
        $attendanceLocationEnabled = old('attendance_location_enabled', !empty($attendanceLocation['enabled']));
    @endphp

    <header class="page-header">
        <div>
            <p class="eyebrow">Master SDM / Employee</p>
            <h1>Edit {{ $employee?->name ?: $profile->employee_no }}</h1>
            <p>Perubahan di sini menjadi master data pegawai yang dipakai HRIS, payroll, marketing, finance, accounting, dan employee portal.</p>
        </div>
        <div class="role-card">
            <span>NIP</span>
            <strong>{{ $profile->employee_no }}</strong>
        </div>
    </header>

    <section class="panel form-panel">
        <div class="panel-header">
            <div>
                <h2>Data Pegawai</h2>
                <span>Akun user + profile SDM dalam satu form</span>
            </div>
        </div>

        <form class="record-form" method="post" action="{{ route('settings.employees.update', $profile) }}">
            @csrf
            @method('PATCH')

            <div class="form-grid compact">
                <label>Nama
                    <input name="name" value="{{ old('name', $employee?->name) }}" required>
                    @error('name')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label>Email
                    <input type="email" name="email" value="{{ old('email', $employee?->email) }}" required>
                    @error('email')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label>Password baru
                    <input type="password" name="password" autocomplete="new-password" placeholder="Kosongkan jika tidak diganti">
                    @error('password')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label>Konfirmasi password
                    <input type="password" name="password_confirmation" autocomplete="new-password">
                </label>
                <label>Status akun
                    <select name="account_status" required>
                        @foreach(['pending' => 'Pending', 'active' => 'Active', 'inactive' => 'Inactive'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('account_status', $employee?->account_status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('account_status')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label>NIP
                    <input name="employee_no" value="{{ old('employee_no', $profile->employee_no) }}" required>
                    @error('employee_no')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label>Atasan struktur
                    <select name="manager_profile_id">
                        <option value="">Tanpa atasan</option>
                        @foreach($hierarchyProfiles as $managerProfile)
                            <option value="{{ $managerProfile->id }}" @selected((string) old('manager_profile_id', $profile->manager_profile_id) === (string) $managerProfile->id)>
                                {{ $managerProfile->user?->name ?: $managerProfile->employee_no }} - {{ $managerProfile->department ?: 'General' }} / {{ $managerProfile->position ?: 'Employee' }}
                            </option>
                        @endforeach
                    </select>
                    @error('manager_profile_id')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label>Departemen
                    <select name="department">
                        <option value="">Pilih departemen</option>
                        @foreach($departments as $department)
                            <option value="{{ $department }}" @selected(old('department', $profile->department) === $department)>{{ $department }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Jabatan
                    <input name="position" value="{{ old('position', $profile->position) }}">
                </label>
                <label>Level
                    <input name="job_level" value="{{ old('job_level', $profile->job_level) }}">
                </label>
                <label>Grade
                    <input name="grade" value="{{ old('grade', $profile->grade) }}">
                </label>
                <label>Status kerja
                    <select name="employment_status" required>
                        @foreach(['active' => 'Active', 'on_leave' => 'On Leave', 'recruiting' => 'Recruiting', 'probation' => 'Probation', 'contract' => 'Contract', 'inactive' => 'Inactive'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('employment_status', $profile->employment_status) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('employment_status')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label>Tanggal masuk
                    <input type="date" name="join_date" value="{{ old('join_date', $profile->join_date?->toDateString()) }}">
                </label>
                <label>Nama atasan manual
                    <input name="manager_name" value="{{ old('manager_name', $profile->manager_name) }}">
                </label>
                <label>Lokasi kerja
                    <select name="work_location">
                        <option value="">Pilih lokasi</option>
                        @foreach($locations as $location)
                            <option value="{{ $location }}" @selected(old('work_location', $profile->work_location) === $location)>{{ $location }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Cabang
                    <input name="branch" value="{{ old('branch', $profile->branch) }}">
                </label>
                <label>Telepon
                    <input name="phone" value="{{ old('phone', $profile->phone) }}">
                </label>
                <label>Bank
                    <input name="bank_name" value="{{ old('bank_name', $profile->bank_name) }}">
                </label>
                <label>No rekening
                    <input name="bank_account_number" value="{{ old('bank_account_number', $profile->bank_account_number) }}">
                </label>
                <label>Nama rekening
                    <input name="bank_account_name" value="{{ old('bank_account_name', $profile->bank_account_name) }}">
                </label>
                <label>Gaji pokok
                    <input type="number" min="0" name="base_salary" value="{{ old('base_salary', $profile->base_salary) }}">
                </label>
                <label>Tunjangan tetap
                    <input type="number" min="0" name="fixed_allowance" value="{{ old('fixed_allowance', $profile->fixed_allowance) }}">
                </label>
                <label>Status pajak
                    <input name="tax_status" value="{{ old('tax_status', $profile->tax_status) }}" placeholder="TK/0, K/1">
                </label>
                <label>Target marketing
                    <input type="number" min="0" name="marketing_target" value="{{ old('marketing_target', $profile->marketing_target) }}">
                </label>
                <label>Rate komisi marketing
                    <input type="number" min="0" step="0.0001" name="commission_rate" value="{{ old('commission_rate', $profile->commission_rate) }}">
                </label>
                <label>Rate KPI produksi
                    <input type="number" min="0" step="0.0001" name="production_kpi_rate" value="{{ old('production_kpi_rate', $profile->production_kpi_rate) }}">
                </label>
            </div>

            <div class="form-actions">
                <a class="ghost-button" href="{{ route('settings.employees') }}"><x-icon name="x" />Batal</a>
                <button class="primary-button" type="submit"><x-icon name="save" />Simpan Perubahan</button>
            </div>
        </form>
    </section>

    <section class="panel form-panel">
        <div class="panel-header">
            <div>
                <h2>Setting Lokasi Presensi</h2>
                <span>Radius geofence per pegawai untuk validasi clock in/out dari portal employee</span>
            </div>
            <span class="status {{ !empty($attendanceLocation['enabled']) ? 'success' : 'warning' }}">
                {{ !empty($attendanceLocation['enabled']) ? 'AKTIF' : 'NONAKTIF' }}
            </span>
        </div>

        <form class="record-form" method="post" action="{{ route('settings.employees.attendance-location', $profile) }}" data-geofence-form>
            @csrf
            @method('PATCH')

            <div class="form-grid compact">
                <label class="checkbox-row full">
                    <input type="checkbox" name="attendance_location_enabled" value="1" @checked($attendanceLocationEnabled)>
                    Aktifkan batas lokasi presensi untuk pegawai ini
                </label>
                <label>Nama lokasi
                    <input name="attendance_location_name" value="{{ old('attendance_location_name', $attendanceLocation['name'] ?? $profile->work_location) }}" placeholder="Kantor pusat / cabang / site">
                    @error('attendance_location_name')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label>Radius meter
                    <input type="number" min="10" max="50000" name="attendance_radius_meters" value="{{ old('attendance_radius_meters', $attendanceLocation['radius_meters'] ?? 150) }}" placeholder="150">
                    @error('attendance_radius_meters')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label>Latitude
                    <input type="number" step="0.0000001" name="attendance_latitude" value="{{ old('attendance_latitude', $attendanceLocation['latitude'] ?? '') }}" data-geofence-lat placeholder="-6.2000000">
                    @error('attendance_latitude')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label>Longitude
                    <input type="number" step="0.0000001" name="attendance_longitude" value="{{ old('attendance_longitude', $attendanceLocation['longitude'] ?? '') }}" data-geofence-lng placeholder="106.8166660">
                    @error('attendance_longitude')<small class="field-error">{{ $message }}</small>@enderror
                </label>
                <label class="full">Alamat / catatan lokasi
                    <textarea name="attendance_location_address" data-geofence-address>{{ old('attendance_location_address', $attendanceLocation['address'] ?? '') }}</textarea>
                    @error('attendance_location_address')<small class="field-error">{{ $message }}</small>@enderror
                </label>
            </div>

            <div class="form-actions">
                <button class="ghost-button" type="button" data-geofence-current><x-icon name="map-pin" />Ambil Lokasi Browser</button>
                <button class="primary-button" type="submit"><x-icon name="save" />Simpan Lokasi Presensi</button>
            </div>
        </form>
    </section>

    <script>
        (function () {
            const button = document.querySelector('[data-geofence-current]');
            const latField = document.querySelector('[data-geofence-lat]');
            const lngField = document.querySelector('[data-geofence-lng]');
            const addressField = document.querySelector('[data-geofence-address]');

            if (!button || !navigator.geolocation) {
                return;
            }

            button.addEventListener('click', function () {
                button.disabled = true;
                button.textContent = 'Membaca lokasi...';

                navigator.geolocation.getCurrentPosition(function (position) {
                    const lat = position.coords.latitude.toFixed(7);
                    const lng = position.coords.longitude.toFixed(7);
                    latField.value = lat;
                    lngField.value = lng;
                    if (addressField && !addressField.value) {
                        addressField.value = lat + ', ' + lng;
                    }
                    button.disabled = false;
                    button.textContent = 'Ambil Lokasi Browser';
                }, function () {
                    button.disabled = false;
                    button.textContent = 'Ambil Lokasi Browser';
                }, { enableHighAccuracy: true, timeout: 9000, maximumAge: 60000 });
            });
        })();
    </script>
@endsection
