@extends('layouts.app')

@section('title', 'Marketing Management - '.config('app.name'))

@section('actions')
    <a class="ghost-button" href="{{ route('records.index', 'marketing') }}"><x-icon name="radio-tower" />Modul Marketing</a>
    <a class="ghost-button" href="{{ route('records.index', 'sales') }}"><x-icon name="shopping-cart" />Sales</a>
@endsection

@php
    $mapPoints = collect($trackingLocations ?? [])->map(fn ($tracking) => [
        'id' => $tracking->id,
        'lat' => (float) $tracking->latitude,
        'lng' => (float) $tracking->longitude,
        'employee' => $tracking->profile?->user?->name ?: 'Marketing',
        'branch' => $tracking->branch?->name ?: ($tracking->profile?->branch ?: 'Tanpa cabang'),
        'activity' => str($tracking->activity_type)->replace('_', ' ')->headline()->toString(),
        'customer' => $tracking->customer_name ?: '-',
        'device' => $tracking->device_id ?: '-',
        'phone' => $tracking->phone_number ?: '-',
        'tracked_at' => $tracking->tracked_at?->format('d M Y H:i') ?: '-',
        'pipeline' => (float) $tracking->pipeline_amount,
    ])->values();
    $mapCenter = $mapPoints->first();
    $mapUrl = $mapCenter ? 'https://maps.google.com/maps?q='.$mapCenter['lat'].','.$mapCenter['lng'].'&z=14&output=embed' : null;
@endphp

@section('content')
    <header class="page-header">
        <div>
            <p class="eyebrow">Marketing</p>
            <h1>Marketing Management</h1>
            <p>Kelola cabang, pegawai marketing, device/no telepon, tracking aktivitas, pipeline, dan sinkronisasi otomatis ke sales.</p>
        </div>
        <div class="role-card">
            <span>Tracking</span>
            <strong>{{ $trackings->total() }}</strong>
        </div>
    </header>

    <div class="metrics-grid">
        <article class="metric-card">
            <span>Pegawai marketing</span>
            <strong>{{ $profiles->count() }}</strong>
            <small>User employee dengan departemen/posisi marketing.</small>
        </article>
        <article class="metric-card">
            <span>Cabang aktif</span>
            <strong>{{ $branches->where('is_active', true)->count() }}</strong>
            <small>Dipakai untuk tracking dan pipeline.</small>
        </article>
        <article class="metric-card">
            <span>Pipeline tercatat</span>
            <strong>Rp {{ number_format((float) $trackings->getCollection()->sum('pipeline_amount'), 0, ',', '.') }}</strong>
            <small>Dari data tracking pada halaman ini.</small>
        </article>
        <article class="metric-card">
            <span>Sales dari marketing</span>
            <strong>{{ $salesFromMarketing->count() }}</strong>
            <small>Order penjualan hasil konversi marketing.</small>
        </article>
    </div>

    <section class="panel marketing-map-panel">
        <div class="panel-header">
            <div>
                <h2>Peta Tracking Location</h2>
                <span>{{ $mapPoints->count() }} titik lokasi dari tracking marketing terbaru</span>
            </div>
            @if($mapCenter)
                <a class="ghost-button" target="_blank" href="https://www.google.com/maps?q={{ $mapCenter['lat'] }},{{ $mapCenter['lng'] }}">
                    <x-icon name="external-link" />Buka Maps
                </a>
            @endif
        </div>
        <div class="marketing-map-layout">
            <div class="marketing-map-frame-wrap">
                @if($mapUrl)
                    <iframe class="marketing-map-frame" data-marketing-map-frame src="{{ $mapUrl }}" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                @else
                    <div class="marketing-map-empty">
                        <x-icon name="map-pin-off" />
                        <strong>Belum ada titik lokasi</strong>
                        <span>Isi latitude dan longitude pada tracking marketing untuk menampilkan peta.</span>
                    </div>
                @endif
            </div>
            <div class="marketing-map-list">
                @forelse($mapPoints as $point)
                    <button class="marketing-map-point {{ $loop->first ? 'is-active' : '' }}" type="button" data-map-lat="{{ $point['lat'] }}" data-map-lng="{{ $point['lng'] }}" data-map-label="{{ $point['employee'] }}">
                        <span class="marketing-map-pin"><x-icon name="map-pin" /></span>
                        <span>
                            <strong>{{ $point['employee'] }}</strong>
                            <small>{{ $point['tracked_at'] }} - {{ $point['activity'] }} - {{ $point['branch'] }}</small>
                            <small>Device {{ $point['device'] }} / {{ $point['phone'] }} - {{ $point['customer'] }} - Rp {{ number_format($point['pipeline'], 0, ',', '.') }}</small>
                        </span>
                    </button>
                @empty
                    <div class="empty-state">Belum ada tracking dengan koordinat.</div>
                @endforelse
            </div>
        </div>
    </section>

    <div class="employee-section-grid">
        <section class="panel">
            <div class="panel-header">
                <h2>Cabang Marketing</h2>
                <span>{{ $branches->count() }} cabang</span>
            </div>
            <div class="form-panel">
                <form class="record-form" method="post" action="{{ route('marketing.branches.store') }}">
                    @csrf
                    <div class="form-grid compact">
                        <label>Kode <input name="code" value="{{ old('code') }}" placeholder="MKT-BDG" required></label>
                        <label>Nama cabang <input name="name" value="{{ old('name') }}" required></label>
                        <label>Region <input name="region" value="{{ old('region') }}"></label>
                        <label>No telepon <input name="phone" value="{{ old('phone') }}"></label>
                        <label>Manager <input name="manager_name" value="{{ old('manager_name') }}"></label>
                        <label class="full">Alamat <textarea name="address">{{ old('address') }}</textarea></label>
                    </div>
                    <div class="form-actions">
                        <button class="primary-button" type="submit"><x-icon name="building-2" />Tambah Cabang</button>
                    </div>
                </form>
            </div>
            <div class="mini-list">
                @forelse($branches as $branch)
                    <div class="mini-item">
                        <div>
                            <strong>{{ $branch->name }}</strong>
                            <span>{{ $branch->code }} - {{ $branch->region ?: 'Tanpa region' }} - {{ $branch->manager_name ?: 'Tanpa manager' }}</span>
                        </div>
                        <span class="status {{ $branch->is_active ? 'success' : 'warning' }}">{{ $branch->is_active ? 'AKTIF' : 'NONAKTIF' }}</span>
                    </div>
                @empty
                    <div class="empty-state">Belum ada cabang marketing.</div>
                @endforelse
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2>User Pegawai Marketing</h2>
                <span>Akun + employee profile</span>
            </div>
            <div class="form-panel">
                <form class="record-form" method="post" action="{{ route('marketing.employees.store') }}">
                    @csrf
                    <div class="form-grid compact">
                        <label>Nama <input name="name" value="{{ old('name') }}" required></label>
                        <label>Email <input type="email" name="email" value="{{ old('email') }}" required></label>
                        <label>Password <input type="password" name="password" autocomplete="new-password"></label>
                        <label>Konfirmasi <input type="password" name="password_confirmation" autocomplete="new-password"></label>
                        <label>NIP <input name="employee_no" value="{{ old('employee_no') }}"></label>
                        <label>Cabang
                            <select name="branch">
                                <option value="">Pilih cabang</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->name }}" @selected(old('branch') === $branch->name)>{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>No telepon <input name="phone" value="{{ old('phone') }}"></label>
                        <label>Device ID <input name="device_id" value="{{ old('device_id') }}" placeholder="IMEI / kode device"></label>
                        <label>Atasan
                            <select name="manager_profile_id">
                                <option value="">Pilih atasan</option>
                                @foreach($profiles as $profile)
                                    <option value="{{ $profile->id }}" @selected((string) old('manager_profile_id') === (string) $profile->id)>{{ $profile->user?->name }} - {{ $profile->position }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Target bulanan <input type="number" min="0" name="marketing_target" value="{{ old('marketing_target', 0) }}"></label>
                        <label>Komisi rate <input type="number" min="0" step="0.0001" name="commission_rate" value="{{ old('commission_rate', '0.0100') }}"></label>
                        <label>Gaji pokok <input type="number" min="0" name="base_salary" value="{{ old('base_salary', 0) }}"></label>
                        <label>Tunjangan tetap <input type="number" min="0" name="fixed_allowance" value="{{ old('fixed_allowance', 0) }}"></label>
                    </div>
                    <div class="form-actions">
                        <button class="primary-button" type="submit"><x-icon name="user-plus" />Buat Pegawai Marketing</button>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <div class="employee-section-grid">
        <section class="panel">
            <div class="panel-header">
                <h2>Input Tracking</h2>
                <span>Device / no telepon</span>
            </div>
            <div class="form-panel">
                <form class="record-form" method="post" action="{{ route('marketing.trackings.store') }}">
                    @csrf
                    <div class="form-grid compact">
                        <label>Marketing
                            <select name="employee_profile_id" required>
                                @foreach($profiles as $profile)
                                    <option value="{{ $profile->id }}">{{ $profile->user?->name }} - {{ $profile->employee_no }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Cabang
                            <select name="marketing_branch_id">
                                <option value="">Pilih cabang</option>
                                @foreach($branches as $branch)
                                    <option value="{{ $branch->id }}">{{ $branch->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Waktu tracking <input type="datetime-local" name="tracked_at" value="{{ old('tracked_at') }}"></label>
                        <label>Tipe aktivitas
                            <select name="activity_type" required>
                                <option value="customer_visit">Customer Visit</option>
                                <option value="lead_follow_up">Lead Follow Up</option>
                                <option value="telemarketing">Telemarketing</option>
                                <option value="event">Event</option>
                                <option value="check_in">Check In</option>
                            </select>
                        </label>
                        <label>Device ID <input name="device_id" value="{{ old('device_id') }}"></label>
                        <label>No telepon <input name="phone_number" value="{{ old('phone_number') }}"></label>
                        <label>Latitude <input type="number" step="0.0000001" name="latitude" value="{{ old('latitude') }}" data-marketing-latitude></label>
                        <label>Longitude <input type="number" step="0.0000001" name="longitude" value="{{ old('longitude') }}" data-marketing-longitude></label>
                        <div class="full marketing-location-tools">
                            <button class="ghost-button" type="button" data-marketing-current-location><x-icon name="locate-fixed" />Ambil Lokasi Perangkat</button>
                            <small data-marketing-location-status>Koordinat bisa diisi manual atau dari GPS/browser perangkat.</small>
                        </div>
                        <label>Customer <input name="customer_name" value="{{ old('customer_name') }}"></label>
                        <label>Pipeline <input type="number" min="0" name="pipeline_amount" value="{{ old('pipeline_amount', 0) }}"></label>
                        <label>Status
                            <select name="status" required>
                                <option value="reported">Reported</option>
                                <option value="verified">Verified</option>
                                <option value="converted">Converted</option>
                            </select>
                        </label>
                        <label class="full">Alamat <textarea name="address">{{ old('address') }}</textarea></label>
                        <label class="full">Catatan <textarea name="notes">{{ old('notes') }}</textarea></label>
                    </div>
                    <div class="form-actions">
                        <button class="primary-button" type="submit"><x-icon name="map-pin" />Simpan Tracking</button>
                    </div>
                </form>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2>Pipeline ke Sales</h2>
                <span>{{ $marketingRecords->count() }} marketing lead</span>
            </div>
            <div class="mini-list">
                @forelse($marketingRecords as $record)
                    <a class="mini-item" href="{{ route('records.index', ['module' => 'marketing', 'q' => $record->code]) }}">
                        <div>
                            <strong>{{ $record->code }} - {{ $record->title }}</strong>
                            <span>{{ $record->party ?: '-' }} - Rp {{ number_format((float) $record->amount, 0, ',', '.') }}</span>
                        </div>
                        <span class="status {{ in_array($record->status, ['Converted', 'Closed'], true) ? 'success' : 'warning' }}">{{ strtoupper($record->status) }}</span>
                    </a>
                @empty
                    <div class="empty-state">Belum ada pipeline marketing.</div>
                @endforelse
            </div>

            <div class="panel-header no-border">
                <h2>Sales Terkonversi</h2>
                <span>{{ $salesFromMarketing->count() }} order</span>
            </div>
            <div class="mini-list compact-list">
                @forelse($salesFromMarketing as $record)
                    <a class="mini-item" href="{{ route('records.index', ['module' => 'sales', 'q' => $record->code]) }}">
                        <div>
                            <strong>{{ $record->code }} - {{ $record->title }}</strong>
                            <span>Invoice/VA dibuat di finance saat sales confirmed.</span>
                        </div>
                        <span class="status {{ $record->status === 'Paid' ? 'success' : 'warning' }}">{{ strtoupper($record->status) }}</span>
                    </a>
                @empty
                    <div class="empty-state">Belum ada sales dari marketing.</div>
                @endforelse
            </div>
        </section>
    </div>

    <section class="panel">
        <div class="panel-header">
            <h2>Monitoring Tracking Marketing</h2>
            <span>{{ $trackings->total() }} data</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Pegawai</th>
                        <th>Cabang</th>
                        <th>Device / Telp</th>
                        <th>Lokasi</th>
                        <th>Aktivitas</th>
                        <th>Customer</th>
                        <th>Pipeline</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($trackings as $tracking)
                        <tr>
                            <td>{{ $tracking->tracked_at?->format('d M Y H:i') }}</td>
                            <td>{{ $tracking->profile?->user?->name ?: '-' }}</td>
                            <td>{{ $tracking->branch?->name ?: ($tracking->profile?->branch ?: '-') }}</td>
                            <td>{{ $tracking->device_id ?: '-' }}<br><span class="muted">{{ $tracking->phone_number ?: '-' }}</span></td>
                            <td>
                                @if($tracking->latitude && $tracking->longitude)
                                    <a class="status" target="_blank" href="https://www.google.com/maps?q={{ $tracking->latitude }},{{ $tracking->longitude }}">Map</a>
                                @else
                                    {{ $tracking->address ?: '-' }}
                                @endif
                            </td>
                            <td>{{ str($tracking->activity_type)->replace('_', ' ')->headline() }}</td>
                            <td>{{ $tracking->customer_name ?: '-' }}</td>
                            <td>Rp {{ number_format((float) $tracking->pipeline_amount, 0, ',', '.') }}</td>
                            <td><span class="status {{ $tracking->status === 'converted' ? 'success' : 'warning' }}">{{ strtoupper($tracking->status) }}</span></td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="empty-state">Belum ada tracking marketing.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $trackings->links() }}</div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const mapFrame = document.querySelector('[data-marketing-map-frame]');
            const mapButtons = document.querySelectorAll('[data-map-lat][data-map-lng]');

            mapButtons.forEach((button) => {
                button.addEventListener('click', function () {
                    const lat = this.dataset.mapLat;
                    const lng = this.dataset.mapLng;

                    if (mapFrame && lat && lng) {
                        mapFrame.src = `https://maps.google.com/maps?q=${lat},${lng}&z=15&output=embed`;
                    }

                    mapButtons.forEach((item) => item.classList.remove('is-active'));
                    this.classList.add('is-active');
                });
            });

            const currentLocationButton = document.querySelector('[data-marketing-current-location]');
            const latitudeInput = document.querySelector('[data-marketing-latitude]');
            const longitudeInput = document.querySelector('[data-marketing-longitude]');
            const locationStatus = document.querySelector('[data-marketing-location-status]');

            currentLocationButton?.addEventListener('click', function () {
                if (! navigator.geolocation) {
                    if (locationStatus) {
                        locationStatus.textContent = 'Browser/perangkat belum mendukung geolocation.';
                    }
                    return;
                }

                currentLocationButton.disabled = true;
                if (locationStatus) {
                    locationStatus.textContent = 'Mengambil lokasi perangkat...';
                }

                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        if (latitudeInput) {
                            latitudeInput.value = position.coords.latitude.toFixed(7);
                        }
                        if (longitudeInput) {
                            longitudeInput.value = position.coords.longitude.toFixed(7);
                        }
                        if (locationStatus) {
                            locationStatus.textContent = 'Lokasi berhasil diambil. Silakan cek koordinat sebelum disimpan.';
                        }
                        currentLocationButton.disabled = false;
                    },
                    () => {
                        if (locationStatus) {
                            locationStatus.textContent = 'Tidak bisa mengambil lokasi. Pastikan izin lokasi browser aktif.';
                        }
                        currentLocationButton.disabled = false;
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 12000,
                        maximumAge: 60000,
                    }
                );
            });
        });
    </script>
@endsection
