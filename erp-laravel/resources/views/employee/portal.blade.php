@extends('layouts.app')

@section('title', 'Employee Portal - '.config('app.name'))

@section('content')
    @php
        $personal = $profile->personal_data ?? [];
        $education = $profile->education_data ?? [];
        $emergency = $profile->emergency_contact ?? [];
        $job = $profile->job_data ?? [];
        $experience = $profile->experience_data ?? [];
        $pendingRequests = $requests->where('status', 'submitted')->count();
        $pendingReimbursements = $reimbursements->where('status', 'submitted')->count();
    @endphp

    <header class="page-header">
        <div>
            <p class="eyebrow">Employee Self Service</p>
            <h1>Portal Employee</h1>
            <p>Presensi lokasi, pengajuan, cuti, payroll, slip gaji, reimburse, data pekerja, dan informasi internal.</p>
        </div>
        <div class="profile-card">
            @if(auth()->user()->photo_path)
                <img class="profile-avatar" src="{{ asset(auth()->user()->photo_path) }}" alt="{{ auth()->user()->name }}">
            @else
                <div class="profile-avatar fallback">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</div>
            @endif
            <div>
                <span>Nomor pekerja</span>
                <strong>{{ $profile->employee_no }}</strong>
                <a href="{{ route('account.edit') }}">Ubah profil akun</a>
            </div>
        </div>
    </header>

    <div class="metrics-grid">
        <article class="metric-card">
            <span>Presensi hari ini</span>
            <strong>{{ $todayAttendance?->clock_in_at ? $todayAttendance->clock_in_at->format('H:i') : '--:--' }}</strong>
            <small>{{ $todayAttendance?->clock_out_at ? 'Pulang '.$todayAttendance->clock_out_at->format('H:i') : 'Belum clock out' }}</small>
        </article>
        <article class="metric-card">
            <span>Pengajuan pending</span>
            <strong>{{ $pendingRequests }}</strong>
            <small>Cuti, izin, lembur, informasi, dan perubahan data</small>
        </article>
        <article class="metric-card">
            <span>Reimburse pending</span>
            <strong>{{ $pendingReimbursements }}</strong>
            <small>Menunggu review HR atau Finance</small>
        </article>
        <article class="metric-card">
            <span>Payroll terbaru</span>
            <strong>Rp {{ number_format((float) ($latestPayroll?->net_salary ?? 0), 0, ',', '.') }}</strong>
            <small>{{ $latestPayroll ? $latestPayroll->period_month->format('M Y').' - '.strtoupper($latestPayroll->status) : 'Belum ada payroll' }}</small>
        </article>
    </div>

    <div class="employee-grid">
        <section class="panel employee-attendance">
            <div class="panel-header">
                <h2>Presensi Lokasi</h2>
                <span>{{ now()->format('d M Y') }}</span>
            </div>
            <div class="attendance-body">
                <div class="attendance-clock">
                    <strong>{{ now()->format('H:i') }}</strong>
                    <span id="geoStatus">Lokasi siap dicek dari browser</span>
                    @if(($attendanceLocationPolicy['enabled'] ?? false))
                        <small>{{ $attendanceLocationPolicy['name'] ?: 'Lokasi presensi' }} - radius {{ number_format((float) $attendanceLocationPolicy['radius_meters'], 0, ',', '.') }} meter</small>
                    @else
                        <small>Radius lokasi belum dibatasi untuk pegawai ini</small>
                    @endif
                </div>
                <div class="attendance-actions">
                    <form method="post" action="{{ route('employee.attendance.clock-in') }}" data-attendance-form>
                        @csrf
                        <input type="hidden" name="latitude" data-geo-lat>
                        <input type="hidden" name="longitude" data-geo-lng>
                        <input type="hidden" name="address" data-geo-address>
                        <input type="hidden" name="device_id" data-device-id>
                        <input type="hidden" name="phone_number" value="{{ $profile->phone }}">
                        <button class="primary-button full" type="submit" @disabled($todayAttendance?->clock_in_at)><x-icon name="map-pin" />Clock In</button>
                    </form>
                    <form method="post" action="{{ route('employee.attendance.clock-out') }}" data-attendance-form>
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="latitude" data-geo-lat>
                        <input type="hidden" name="longitude" data-geo-lng>
                        <input type="hidden" name="address" data-geo-address>
                        <input type="hidden" name="device_id" data-device-id>
                        <input type="hidden" name="phone_number" value="{{ $profile->phone }}">
                        <button class="ghost-button full" type="submit" @disabled(! $todayAttendance?->clock_in_at || $todayAttendance?->clock_out_at)><x-icon name="map-pin" />Clock Out</button>
                    </form>
                </div>
            </div>
            <div class="form-panel compact-manual-attendance">
                <form class="record-form" method="post" action="{{ route('employee.attendance.manual') }}">
                    @csrf
                    <div class="form-grid compact">
                        <label>Tanggal <input type="date" name="attendance_date" value="{{ old('attendance_date', now()->toDateString()) }}" required></label>
                        <label>Clock in <input type="time" name="clock_in_at" value="{{ old('clock_in_at') }}" required></label>
                        <label>Clock out <input type="time" name="clock_out_at" value="{{ old('clock_out_at') }}"></label>
                        <label>No telepon <input name="phone_number" value="{{ old('phone_number', $profile->phone) }}"></label>
                        <label>Device ID <input name="device_id" value="{{ old('device_id') }}" data-device-id-input></label>
                        <label>Alamat <input name="address" value="{{ old('address') }}" data-geo-address-input></label>
                        <label class="full">Alasan <textarea name="reason" required>{{ old('reason') }}</textarea></label>
                    </div>
                    <div class="form-actions">
                        <button class="ghost-button" type="submit"><x-icon name="file-clock" />Ajukan Presensi Manual</button>
                    </div>
                </form>
            </div>
            <div class="mini-list">
                @forelse($recentAttendances as $attendance)
                    <div class="mini-item">
                        <div>
                            <strong>{{ $attendance->attendance_date->format('d M Y') }}</strong>
                            <span>{{ $attendance->clock_in_at?->format('H:i') ?: '--:--' }} - {{ $attendance->clock_out_at?->format('H:i') ?: '--:--' }} | {{ strtoupper($attendance->source ?: 'DEVICE') }}</span>
                        </div>
                        <span class="status {{ $attendance->clock_out_at ? 'success' : 'warning' }}">{{ strtoupper($attendance->status) }}</span>
                    </div>
                @empty
                    <div class="empty-state">Belum ada riwayat presensi.</div>
                @endforelse
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2>Informasi</h2>
                <span>{{ $announcements->count() }} update</span>
            </div>
            <div class="mini-list">
                @forelse($announcements as $announcement)
                    <a class="mini-item" href="{{ route('announcements.show', $announcement) }}">
                        <div>
                            <strong>{{ $announcement->title }}</strong>
                            <span>{{ $announcement->summary ?: \Illuminate\Support\Str::limit($announcement->body, 120) }}</span>
                        </div>
                        <span class="status {{ $announcement->kind === 'news' ? 'success' : '' }}">{{ $announcement->kind === 'news' ? 'BERITA' : strtoupper($announcement->category) }}</span>
                    </a>
                @empty
                    <div class="empty-state">Belum ada informasi baru.</div>
                @endforelse
            </div>
        </section>
    </div>

    <div class="employee-section-grid">
        <section class="panel">
            <div class="panel-header">
                <h2>Pengajuan dan Cuti</h2>
                <span>{{ $requests->count() }} terakhir</span>
            </div>
            <div class="form-panel">
                <form class="record-form" method="post" action="{{ route('employee.requests.store') }}">
                    @csrf
                    <div class="form-grid compact" data-employee-request-form>
                        <label>
                            Tipe
                            <select name="type" required data-request-type>
                                <option value="leave">Cuti</option>
                                <option value="permit">Izin</option>
                                <option value="sick">Sakit</option>
                                <option value="overtime">Lembur</option>
                                <option value="manual_attendance">Presensi Manual</option>
                                <option value="training">Pelatihan</option>
                                <option value="business_trip">Perjalanan Dinas</option>
                                <option value="information">Informasi</option>
                                <option value="data_change">Perubahan Data</option>
                            </select>
                        </label>
                        <label>
                            Judul
                            <input name="title" value="{{ old('title') }}" required>
                        </label>
                        <label>
                            Mulai
                            <input type="date" name="start_date" value="{{ old('start_date') }}" data-request-start-date>
                        </label>
                        <label>
                            Selesai
                            <input type="date" name="end_date" value="{{ old('end_date') }}" data-request-end-date>
                        </label>
                        <label>
                            Jam mulai
                            <input type="time" name="start_time" value="{{ old('start_time') }}" data-request-start-time>
                        </label>
                        <label>
                            Jam selesai
                            <input type="time" name="end_time" value="{{ old('end_time') }}" data-request-end-time>
                        </label>
                        <label>
                            Durasi hari/jam
                            <input type="number" min="0" step="0.25" name="total_days" value="{{ old('total_days') }}" data-request-duration placeholder="Auto">
                        </label>
                        <label data-business-trip-field>
                            Tujuan perjalanan
                            <input name="travel_destination" value="{{ old('travel_destination') }}" data-business-trip-required placeholder="Kota / lokasi tujuan">
                        </label>
                        <label data-business-trip-field>
                            Estimasi biaya
                            <input type="number" min="0" name="estimated_amount" value="{{ old('estimated_amount') }}" placeholder="Transport, hotel, uang jalan">
                        </label>
                        <label data-business-trip-field>
                            Transportasi
                            <input name="transport_mode" value="{{ old('transport_mode') }}" placeholder="Pesawat / kereta / kendaraan dinas">
                        </label>
                        <label data-business-trip-field>
                            Agenda
                            <input name="travel_purpose" value="{{ old('travel_purpose') }}" placeholder="Meeting / audit / kunjungan cabang">
                        </label>
                        <label class="full">
                            Keterangan
                            <textarea name="description">{{ old('description') }}</textarea>
                        </label>
                    </div>
                    <div class="form-actions">
                        <button class="primary-button" type="submit"><x-icon name="send" />Kirim Pengajuan</button>
                    </div>
                </form>
            </div>
            <div class="mini-list">
                @forelse($requests as $employeeRequest)
                    <div class="mini-item">
                        <div>
                            <strong>{{ $employeeRequest->title }}</strong>
                            <span>
                                {{ strtoupper(str_replace('_', ' ', $employeeRequest->type)) }}
                                {{ $employeeRequest->start_date ? '- '.$employeeRequest->start_date->format('d M Y') : '' }}
                                @if(!empty($employeeRequest->payload['start_time']) || !empty($employeeRequest->payload['end_time']))
                                    - {{ $employeeRequest->payload['start_time'] ?? '--:--' }}-{{ $employeeRequest->payload['end_time'] ?? '--:--' }}
                                @endif
                                @if($employeeRequest->total_days)
                                    - {{ number_format((float) $employeeRequest->total_days, 2, ',', '.') }} {{ in_array($employeeRequest->type, ['overtime', 'manual_attendance'], true) ? 'jam' : 'hari' }}
                                @endif
                            </span>
                        </div>
                        <span class="status {{ $employeeRequest->status === 'approved' ? 'success' : ($employeeRequest->status === 'submitted' ? 'warning' : '') }}">{{ strtoupper($employeeRequest->status) }}</span>
                    </div>
                @empty
                    <div class="empty-state">Belum ada pengajuan.</div>
                @endforelse
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2>Reimburse</h2>
                <span>{{ $reimbursements->count() }} terakhir</span>
            </div>
            <div class="form-panel">
                <form class="record-form" method="post" action="{{ route('employee.reimbursements.store') }}">
                    @csrf
                    <div class="form-grid compact">
                        <label>
                            Kategori
                            <input name="category" value="{{ old('category') }}" placeholder="Transport / Medical / Meal" required>
                        </label>
                        <label>
                            Nominal
                            <input type="number" min="0" name="amount" value="{{ old('amount') }}" required>
                        </label>
                        <label>
                            Judul
                            <input name="title" value="{{ old('title') }}" required>
                        </label>
                        <label>
                            Tanggal
                            <input type="date" name="expense_date" value="{{ old('expense_date') }}">
                        </label>
                        <label class="full">
                            Referensi bukti
                            <input name="receipt_reference" value="{{ old('receipt_reference') }}">
                        </label>
                        <label class="full">
                            Keterangan
                            <textarea name="description">{{ old('description') }}</textarea>
                        </label>
                    </div>
                    <div class="form-actions">
                        <button class="primary-button" type="submit"><x-icon name="receipt" />Kirim Reimburse</button>
                    </div>
                </form>
            </div>
            <div class="mini-list">
                @forelse($reimbursements as $reimbursement)
                    <div class="mini-item">
                        <div>
                            <strong>{{ $reimbursement->title }}</strong>
                            <span>Rp {{ number_format((float) $reimbursement->amount, 0, ',', '.') }} - {{ $reimbursement->category }}</span>
                        </div>
                        <span class="status {{ in_array($reimbursement->status, ['approved', 'paid'], true) ? 'success' : ($reimbursement->status === 'submitted' ? 'warning' : '') }}">{{ strtoupper($reimbursement->status) }}</span>
                    </div>
                @empty
                    <div class="empty-state">Belum ada reimburse.</div>
                @endforelse
            </div>
        </section>
    </div>

    <section class="panel employee-profile-panel">
        <div class="panel-header">
            <h2>Data Pekerja</h2>
            <span>{{ $profile->department ?: 'General' }} - {{ $profile->position ?: 'Employee' }}</span>
        </div>
        <div class="form-panel">
            <form class="record-form" method="post" action="{{ route('employee.profile.update') }}">
                @csrf
                @method('PATCH')
                <div class="employee-form-band">
                    <h3>Pribadi dan Rekening</h3>
                    <div class="form-grid">
                        <label>Telepon <input name="phone" value="{{ old('phone', $profile->phone) }}"></label>
                        <label>Jenis kelamin
                            <select name="gender">
                                <option value="">Pilih</option>
                                <option value="Pria" @selected(old('gender', $profile->gender) === 'Pria')>Pria</option>
                                <option value="Wanita" @selected(old('gender', $profile->gender) === 'Wanita')>Wanita</option>
                                <option value="Lainnya" @selected(old('gender', $profile->gender) === 'Lainnya')>Lainnya</option>
                            </select>
                        </label>
                        <label>Tempat lahir <input name="birth_place" value="{{ old('birth_place', $profile->birth_place) }}"></label>
                        <label>Tanggal lahir <input type="date" name="birth_date" value="{{ old('birth_date', $profile->birth_date?->toDateString()) }}"></label>
                        <label>No identitas <input name="identity_no" value="{{ old('identity_no', $personal['identity_no'] ?? '') }}"></label>
                        <label>Status nikah <input name="marital_status" value="{{ old('marital_status', $personal['marital_status'] ?? '') }}"></label>
                        <label>Bank <input name="bank_name" value="{{ old('bank_name', $profile->bank_name) }}"></label>
                        <label>No rekening <input name="bank_account_number" value="{{ old('bank_account_number', $profile->bank_account_number) }}"></label>
                        <label class="full">Nama rekening <input name="bank_account_name" value="{{ old('bank_account_name', $profile->bank_account_name) }}"></label>
                        <label class="full">Alamat <textarea name="address">{{ old('address', $profile->address) }}</textarea></label>
                    </div>
                </div>

                <div class="employee-form-band">
                    <h3>Pendidikan dan Kontak Darurat</h3>
                    <div class="form-grid">
                        <label>Pendidikan <input name="education_level" value="{{ old('education_level', $education['level'] ?? '') }}"></label>
                        <label>Institusi <input name="education_institution" value="{{ old('education_institution', $education['institution'] ?? '') }}"></label>
                        <label>Jurusan <input name="education_major" value="{{ old('education_major', $education['major'] ?? '') }}"></label>
                        <label>Tahun lulus <input name="graduation_year" value="{{ old('graduation_year', $education['graduation_year'] ?? '') }}"></label>
                        <label>Kontak darurat <input name="emergency_name" value="{{ old('emergency_name', $emergency['name'] ?? '') }}"></label>
                        <label>Hubungan <input name="emergency_relation" value="{{ old('emergency_relation', $emergency['relation'] ?? '') }}"></label>
                        <label class="full">Telepon darurat <input name="emergency_phone" value="{{ old('emergency_phone', $emergency['phone'] ?? '') }}"></label>
                    </div>
                </div>

                <div class="employee-form-band">
                    <h3>Pekerjaan dan Pengalaman</h3>
                    <div class="form-grid">
                        <label>Departemen <input name="department" value="{{ old('department', $profile->department) }}"></label>
                        <label>Jabatan <input name="position" value="{{ old('position', $profile->position) }}"></label>
                        <label>Atasan <input name="manager_name" value="{{ old('manager_name', $profile->manager_name) }}"></label>
                        <label>Lokasi kerja <input name="work_location" value="{{ old('work_location', $profile->work_location) }}"></label>
                        <label>Tipe kontrak <input name="contract_type" value="{{ old('contract_type', $job['contract_type'] ?? '') }}"></label>
                        <label>Shift <input name="shift" value="{{ old('shift', $job['shift'] ?? '') }}"></label>
                        <label class="full">Pengalaman <textarea name="experience_summary">{{ old('experience_summary', $experience['summary'] ?? '') }}</textarea></label>
                    </div>
                </div>

                <div class="form-actions">
                    <button class="primary-button" type="submit"><x-icon name="save" />Simpan Data Pekerja</button>
                </div>
            </form>
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <h2>Payroll dan Slip Gaji</h2>
            <span>{{ $payrolls->count() }} periode</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Periode</th>
                        <th>Gaji Pokok</th>
                        <th>Tunjangan</th>
                        <th>Lembur</th>
                        <th>Komisi</th>
                        <th>Pajak</th>
                        <th>Potongan</th>
                        <th>Reimburse</th>
                        <th>Net</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payrolls as $payroll)
                        <tr>
                            <td>{{ $payroll->period_month->format('M Y') }}</td>
                            <td>Rp {{ number_format((float) $payroll->basic_salary, 0, ',', '.') }}</td>
                            <td>Rp {{ number_format((float) $payroll->allowance, 0, ',', '.') }}</td>
                            <td>Rp {{ number_format((float) $payroll->overtime_amount, 0, ',', '.') }}</td>
                            <td>Rp {{ number_format((float) $payroll->marketing_commission, 0, ',', '.') }}</td>
                            <td>Rp {{ number_format((float) $payroll->tax, 0, ',', '.') }}</td>
                            <td>Rp {{ number_format((float) $payroll->deduction, 0, ',', '.') }}</td>
                            <td>Rp {{ number_format((float) $payroll->reimbursement, 0, ',', '.') }}</td>
                            <td><strong>Rp {{ number_format((float) $payroll->net_salary, 0, ',', '.') }}</strong></td>
                            <td><span class="status {{ in_array($payroll->status, ['released', 'paid'], true) ? 'success' : 'warning' }}">{{ strtoupper($payroll->status) }}</span></td>
                            <td><a class="ghost-button" href="{{ route('employee.payrolls.slip', $payroll) }}"><x-icon name="file-text" />Slip</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="empty-state">Belum ada payroll.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <script>
        (function () {
            const status = document.getElementById('geoStatus');
            const latFields = document.querySelectorAll('[data-geo-lat]');
            const lngFields = document.querySelectorAll('[data-geo-lng]');
            const addressFields = document.querySelectorAll('[data-geo-address]');
            const addressInputs = document.querySelectorAll('[data-geo-address-input]');
            const deviceFields = document.querySelectorAll('[data-device-id]');
            const deviceInputs = document.querySelectorAll('[data-device-id-input]');
            const deviceId = (navigator.platform || 'browser') + '|' + (navigator.userAgent || '').slice(0, 90);
            const requestForm = document.querySelector('[data-employee-request-form]');
            const attendancePolicy = @json($attendanceLocationPolicy ?? ['enabled' => false]);

            deviceFields.forEach((field) => field.value = deviceId);
            deviceInputs.forEach((field) => field.value = field.value || deviceId);

            if (requestForm) {
                const typeField = requestForm.querySelector('[data-request-type]');
                const startDateField = requestForm.querySelector('[data-request-start-date]');
                const endDateField = requestForm.querySelector('[data-request-end-date]');
                const startTimeField = requestForm.querySelector('[data-request-start-time]');
                const endTimeField = requestForm.querySelector('[data-request-end-time]');
                const durationField = requestForm.querySelector('[data-request-duration]');
                const businessTripFields = requestForm.querySelectorAll('[data-business-trip-field]');
                const businessTripRequiredFields = requestForm.querySelectorAll('[data-business-trip-required]');

                function syncRequestFields() {
                    const type = typeField.value;
                    const needsDate = ['leave', 'permit', 'sick', 'overtime', 'manual_attendance', 'training', 'business_trip'].includes(type);
                    const needsTime = ['overtime', 'manual_attendance'].includes(type);
                    const isBusinessTrip = type === 'business_trip';
                    startDateField.required = needsDate;
                    startTimeField.required = needsTime;
                    endTimeField.required = needsTime;
                    startTimeField.closest('label').style.display = needsTime ? '' : 'none';
                    endTimeField.closest('label').style.display = needsTime ? '' : 'none';
                    businessTripFields.forEach((field) => field.style.display = isBusinessTrip ? '' : 'none');
                    businessTripRequiredFields.forEach((field) => field.required = isBusinessTrip);
                    durationField.placeholder = needsTime ? 'Auto jam' : 'Auto hari';
                    calculateRequestDuration();
                }

                function calculateRequestDuration() {
                    if (!durationField || durationField.value) {
                        return;
                    }

                    const type = typeField.value;

                    if (['overtime', 'manual_attendance'].includes(type) && startDateField.value && startTimeField.value && endTimeField.value) {
                        const start = new Date(`${startDateField.value}T${startTimeField.value}`);
                        const endDate = endDateField.value || startDateField.value;
                        let end = new Date(`${endDate}T${endTimeField.value}`);

                        if (end <= start) {
                            end.setDate(end.getDate() + 1);
                        }

                        durationField.value = Math.max(0, ((end - start) / 3600000)).toFixed(2);
                        return;
                    }

                    if (!['overtime', 'manual_attendance'].includes(type) && startDateField.value && endDateField.value) {
                        const start = new Date(`${startDateField.value}T00:00`);
                        const end = new Date(`${endDateField.value}T00:00`);
                        durationField.value = String(Math.max(1, Math.round((end - start) / 86400000) + 1));
                    }
                }

                [typeField, startDateField, endDateField, startTimeField, endTimeField].forEach((field) => {
                    field.addEventListener('change', () => {
                        durationField.value = '';
                        syncRequestFields();
                    });
                });
                syncRequestFields();
            }

            if (!navigator.geolocation) {
                if (status) status.textContent = 'Geolocation tidak tersedia';
                return;
            }

            navigator.geolocation.getCurrentPosition(function (position) {
                const lat = position.coords.latitude.toFixed(7);
                const lng = position.coords.longitude.toFixed(7);
                latFields.forEach((field) => field.value = lat);
                lngFields.forEach((field) => field.value = lng);
                addressFields.forEach((field) => field.value = lat + ', ' + lng);
                addressInputs.forEach((field) => field.value = field.value || (lat + ', ' + lng));
                if (status) {
                    if (attendancePolicy.enabled && attendancePolicy.latitude && attendancePolicy.longitude) {
                        const distance = distanceMeters(Number(lat), Number(lng), Number(attendancePolicy.latitude), Number(attendancePolicy.longitude));
                        const inside = distance <= Number(attendancePolicy.radius_meters || 0);
                        status.textContent = (inside ? 'Dalam radius' : 'Di luar radius') + ': ' + Math.round(distance).toLocaleString('id-ID') + ' meter dari ' + (attendancePolicy.name || 'lokasi kerja');
                    } else {
                        status.textContent = 'Lokasi terbaca: ' + lat + ', ' + lng;
                    }
                }
            }, function () {
                if (status) status.textContent = 'Lokasi belum diizinkan';
            }, { enableHighAccuracy: true, timeout: 9000, maximumAge: 60000 });

            function distanceMeters(lat1, lng1, lat2, lng2) {
                const earthRadius = 6371000;
                const toRad = (value) => value * Math.PI / 180;
                const latDelta = toRad(lat2 - lat1);
                const lngDelta = toRad(lng2 - lng1);
                const a = Math.sin(latDelta / 2) ** 2
                    + Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) * Math.sin(lngDelta / 2) ** 2;

                return earthRadius * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            }
        })();
    </script>
@endsection
