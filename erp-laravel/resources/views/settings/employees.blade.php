@extends('layouts.app')

@section('title', 'SDM / Employee Management - '.config('app.name'))

@section('actions')
    <form method="post" action="{{ route('settings.employees.sync-sdm') }}">
        @csrf
        <button class="primary-button" type="submit"><x-icon name="combine" />Gabungkan Legacy SDM</button>
    </form>
    <form method="post" action="{{ route('settings.employees.sync-hris') }}">
        @csrf
        <button class="ghost-button" type="submit"><x-icon name="refresh-cw" />Sync HRIS</button>
    </form>
@endsection

@section('content')
    @php
        $recruitmentStages = ['applied', 'screening', 'meeting', 'interview', 'test', 'offered', 'onboarding', 'hired', 'rejected'];
        $marketingEmployees = $employees->filter(fn($user) => in_array($user->employeeProfile?->department, ['Marketing', 'Sales'], true));
        $attendanceToday = $attendances->filter(fn($attendance) => $attendance->attendance_date?->isToday())->count();
        $employeeRequests = collect($employeeRequests ?? []);
        $employeeReimbursements = collect($employeeReimbursements ?? []);
        $hrisTypeGroups = $hrisRecords
            ->groupBy(fn($record) => $record->category ?: 'Tanpa tipe')
            ->map(fn($items, $type) => [
                'type' => $type,
                'count' => $items->count(),
                'pending' => $items->whereIn('status', ['Draft', 'Scheduled', 'Pending Approval'])->count(),
            ])
            ->sortByDesc('count')
            ->values();
        $requestTypeGroups = $employeeRequests
            ->groupBy(fn($request) => $request->type ?: 'unknown')
            ->map(fn($items, $type) => [
                'type' => str($type)->replace('_', ' ')->headline()->toString(),
                'count' => $items->count(),
                'pending' => $items->where('status', 'submitted')->count(),
            ])
            ->sortByDesc('count')
            ->values();
        $employeeWorkload = $employees
            ->map(function ($employee) use ($employeeRequests, $employeeReimbursements, $attendances, $payrolls) {
                $requestCount = $employeeRequests->where('user_id', $employee->id)->count();
                $pendingRequestCount = $employeeRequests->where('user_id', $employee->id)->where('status', 'submitted')->count();
                $reimburseCount = $employeeReimbursements->where('user_id', $employee->id)->count();
                $attendanceCount = $attendances->where('user_id', $employee->id)->count();
                $payrollCount = $payrolls->where('user_id', $employee->id)->count();

                return [
                    'employee' => $employee,
                    'score' => ($pendingRequestCount * 4) + $requestCount + $reimburseCount + $attendanceCount + $payrollCount,
                    'requests' => $requestCount,
                    'pending' => $pendingRequestCount,
                    'reimbursements' => $reimburseCount,
                    'attendances' => $attendanceCount,
                    'payrolls' => $payrollCount,
                ];
            })
            ->sortByDesc('score')
            ->values();
        $employeeActivityGroups = collect([
            ['label' => 'Master Employee', 'icon' => 'users', 'count' => $employees->count(), 'detail' => $hierarchyProfiles->count().' profile dalam struktur'],
            ['label' => 'Presensi & Pengajuan', 'icon' => 'calendar-clock', 'count' => $attendances->count() + $employeeRequests->count(), 'detail' => $pendingRequests->count().' pengajuan pending'],
            ['label' => 'Payroll & Benefit', 'icon' => 'wallet', 'count' => $payrolls->count() + $employeeReimbursements->count(), 'detail' => $pendingReimbursements->count().' reimburse pending'],
            ['label' => 'Recruitment & Informasi', 'icon' => 'newspaper', 'count' => $recruitmentCandidates->count() + $announcements->count(), 'detail' => $recruitmentCandidates->count().' kandidat aktif'],
        ]);
        $employeeOperationalGroups = collect([
            ['label' => 'Butuh Approval HR', 'icon' => 'clipboard-check', 'count' => $pendingRequests->count() + $pendingReimbursements->count(), 'status' => 'warning'],
            ['label' => 'HRIS Perlu Link', 'icon' => 'link-2-off', 'count' => $unlinkedHrisRecords->count(), 'status' => $unlinkedHrisRecords->isNotEmpty() ? 'warning' : 'success'],
            ['label' => 'Presensi Hari Ini', 'icon' => 'map-pin-check', 'count' => $attendanceToday, 'status' => 'success'],
            ['label' => 'Payroll Aktif', 'icon' => 'badge-dollar-sign', 'count' => $payrolls->whereIn('status', ['draft', 'released'])->count(), 'status' => 'warning'],
        ]);
    @endphp

    <header class="page-header">
        <div>
            <p class="eyebrow">HRIS</p>
            <h1>SDM / Employee Management</h1>
            <p>Satu master data pegawai untuk user employee, SDM, HRIS, struktur jabatan, presensi, recruitment, onboarding, payroll otomatis, dan integrasi ke finance/accounting.</p>
        </div>
        <div class="role-card">
            <span>Total employee</span>
            <strong>{{ $employees->count() }}</strong>
        </div>
    </header>

    <div class="metrics-grid">
        <article class="metric-card">
            <span>Employee aktif</span>
            <strong>{{ $employees->filter(fn($user) => $user->employeeProfile?->employment_status === 'active')->count() }}</strong>
            <small>Memiliki akun ESS aktif</small>
        </article>
        <article class="metric-card">
            <span>Master SDM</span>
            <strong>{{ $sdmRecordsCount }}</strong>
            <small>Data utama dari employee profile</small>
        </article>
        <article class="metric-card">
            <span>Legacy SDM</span>
            <strong>{{ $unsyncedSdmRecords->count() }}</strong>
            <small>Record lama yang akan digabung</small>
        </article>
        <article class="metric-card">
            <span>HRIS terhubung</span>
            <strong>{{ $linkedHrisRecordsCount }}</strong>
            <small>Memakai master Employee Profile</small>
        </article>
        <article class="metric-card">
            <span>HRIS belum link</span>
            <strong>{{ $unlinkedHrisRecords->count() }}</strong>
            <small>Perlu pilih pegawai master</small>
        </article>
        <article class="metric-card">
            <span>Pengajuan pending</span>
            <strong>{{ $pendingRequests->count() }}</strong>
            <small>Cuti, izin, dinas, lembur, data change</small>
        </article>
        <article class="metric-card">
            <span>Reimburse pending</span>
            <strong>{{ $pendingReimbursements->count() }}</strong>
            <small>Menunggu approval dan pembayaran</small>
        </article>
        <article class="metric-card">
            <span>Payroll terakhir</span>
            <strong>{{ $payrolls->count() }}</strong>
            <small>Draft, released, dan paid</small>
        </article>
        <article class="metric-card">
            <span>Presensi terbaru</span>
            <strong>{{ $attendanceToday }}</strong>
            <small>Clock in/out dan presensi manual hari ini</small>
        </article>
        <article class="metric-card">
            <span>Recruitment</span>
            <strong>{{ $recruitmentCandidates->count() }}</strong>
            <small>Kandidat, meeting, wawancara, test, onboarding</small>
        </article>
    </div>

    <div class="employee-section-grid">
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Per Kegiatan</h2>
                    <span>Kelompok aktivitas utama Employee/SDM</span>
                </div>
            </div>
            <div class="mini-list">
                @foreach($employeeActivityGroups as $group)
                    <div class="mini-item">
                        <div class="module-card-title">
                            <x-icon :name="$group['icon']" />
                            <div>
                                <strong>{{ $group['label'] }}</strong>
                                <span>{{ $group['detail'] }}</span>
                            </div>
                        </div>
                        <b>{{ $group['count'] }}</b>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Per Operasional</h2>
                    <span>Antrian kerja HR, presensi, dan payroll</span>
                </div>
            </div>
            <div class="mini-list">
                @foreach($employeeOperationalGroups as $group)
                    <div class="mini-item">
                        <div class="module-card-title">
                            <x-icon :name="$group['icon']" />
                            <div>
                                <strong>{{ $group['label'] }}</strong>
                                <span>{{ $group['count'] > 0 ? 'Ada data untuk ditinjau' : 'Tidak ada antrian' }}</span>
                            </div>
                        </div>
                        <span class="status {{ $group['status'] }}">{{ $group['count'] }}</span>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Per Type</h2>
                    <span>Tipe request dan kategori HRIS</span>
                </div>
            </div>
            <div class="mini-list compact-list">
                @forelse($requestTypeGroups->take(5) as $group)
                    <div class="mini-item">
                        <div>
                            <strong>{{ $group['type'] }}</strong>
                            <span>Employee request - {{ $group['pending'] }} pending</span>
                        </div>
                        <b>{{ $group['count'] }}</b>
                    </div>
                @empty
                    <div class="empty-state">Belum ada request employee.</div>
                @endforelse
                @foreach($hrisTypeGroups->take(5) as $group)
                    <a class="mini-item" href="{{ route('records.index', ['module' => 'hris', 'q' => $group['type']]) }}">
                        <div>
                            <strong>{{ $group['type'] }}</strong>
                            <span>HRIS - {{ $group['pending'] }} pending</span>
                        </div>
                        <b>{{ $group['count'] }}</b>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Per Employee</h2>
                    <span>Aktivitas pegawai yang paling perlu dipantau</span>
                </div>
            </div>
            <div class="mini-list compact-list">
                @forelse($employeeWorkload->take(8) as $group)
                    <div class="mini-item">
                        <div>
                            <strong>{{ $group['employee']->name }}</strong>
                            <span>{{ $group['employee']->employeeProfile?->department ?: 'Tanpa departemen' }} - {{ $group['employee']->employeeProfile?->position ?: 'Employee' }}</span>
                            <span>{{ $group['requests'] }} request, {{ $group['reimbursements'] }} reimburse, {{ $group['attendances'] }} presensi, {{ $group['payrolls'] }} payroll</span>
                        </div>
                        <span class="status {{ $group['pending'] > 0 ? 'warning' : 'success' }}">{{ $group['pending'] }} pending</span>
                    </div>
                @empty
                    <div class="empty-state">Belum ada employee.</div>
                @endforelse
            </div>
        </section>
    </div>

    @if($unsyncedSdmRecords->isNotEmpty())
        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Legacy SDM Siap Digabung</h2>
                    <span>{{ $unsyncedSdmRecords->count() }} record lama akan dipindahkan ke master employee</span>
                </div>
                <form method="post" action="{{ route('settings.employees.sync-sdm') }}">
                    @csrf
                    <button class="ghost-button" type="submit"><x-icon name="combine" />Gabungkan Sekarang</button>
                </form>
            </div>
            <div class="mini-list">
                @foreach($unsyncedSdmRecords->take(8) as $record)
                    <div class="mini-item">
                        <div>
                            <strong>{{ $record->title }}</strong>
                            <span>{{ $record->code }} - {{ $record->party ?: 'Tanpa departemen' }} - {{ $record->category ?: 'Tanpa jabatan' }}</span>
                        </div>
                        <span class="status warning">{{ strtoupper($record->status) }}</span>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Operasional HRIS Terintegrasi</h2>
                <span>Presensi, cuti, lembur, reimburse, payroll, finance, dan accounting memakai master pegawai yang sama</span>
            </div>
            <form method="post" action="{{ route('settings.employees.sync-hris') }}">
                @csrf
                <button class="ghost-button" type="submit"><x-icon name="refresh-cw" />Sinkronkan</button>
            </form>
        </div>
        <div class="mini-list">
            @forelse($hrisRecords->take(10) as $record)
                @php
                    $employeeName = $record->extra['employee_name'] ?? null;
                @endphp
                <div class="mini-item">
                    <div>
                        <strong>{{ $record->code }} - {{ $record->title }}</strong>
                        <span>{{ $record->category ?: 'HRIS' }} - {{ $record->party ?: 'Tanpa departemen' }}{{ $employeeName ? ' - '.$employeeName : '' }}</span>
                    </div>
                    @if(!empty($record->extra['employee_profile_id']))
                        <span class="status success">EMPLOYEE LINKED</span>
                    @elseif(($record->extra['source_master'] ?? null) === 'employee_profiles_summary')
                        <span class="status success">PAYROLL SUMMARY</span>
                    @elseif(in_array($record->category, ['Recruitment', 'Interview', 'Test', 'Onboarding'], true))
                        <span class="status warning">CANDIDATE</span>
                    @else
                        <span class="status warning">PERLU LINK</span>
                    @endif
                </div>
            @empty
                <div class="empty-state">Belum ada record operasional HRIS.</div>
            @endforelse
        </div>
        @if($unlinkedHrisRecords->isNotEmpty())
            <div class="empty-state">
                {{ $unlinkedHrisRecords->count() }} record HRIS belum punya pegawai master. Buka modul HRIS lalu edit record terkait untuk memilih Pegawai / Master SDM.
            </div>
        @endif
    </section>

    <div class="employee-section-grid">
        <section class="panel">
            <div class="panel-header">
                <h2>Buat User Employee</h2>
                <span>Akun + profil</span>
            </div>
            <div class="form-panel">
                <form class="record-form" method="post" action="{{ route('settings.employees.store') }}">
                    @csrf
                    <div class="form-grid compact">
                        <label>Nama <input name="name" value="{{ old('name') }}" required></label>
                        <label>Email <input type="email" name="email" value="{{ old('email') }}" required></label>
                        <label>Password <input type="password" name="password" autocomplete="new-password"></label>
                        <label>Konfirmasi <input type="password" name="password_confirmation" autocomplete="new-password"></label>
                        <label>NIP <input name="employee_no" value="{{ old('employee_no') }}"></label>
                        <label>Atasan struktur
                            <select name="manager_profile_id">
                                <option value="">Pilih atasan</option>
                                @foreach($hierarchyProfiles as $managerProfile)
                                    <option value="{{ $managerProfile->id }}" @selected((string) old('manager_profile_id') === (string) $managerProfile->id)>
                                        {{ $managerProfile->user?->name }} - {{ $managerProfile->department ?: 'General' }} / {{ $managerProfile->position ?: 'Employee' }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label>Departemen
                            <select name="department">
                                <option value="">Pilih</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department }}" @selected(old('department') === $department)>{{ $department }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Jabatan <input name="position" value="{{ old('position') }}"></label>
                        <label>Level <input name="job_level" value="{{ old('job_level') }}"></label>
                        <label>Grade <input name="grade" value="{{ old('grade') }}" placeholder="Staff-1 / MGR-2"></label>
                        <label>Status
                            <select name="employment_status">
                                <option value="active">Active</option>
                                <option value="on_leave">On Leave</option>
                                <option value="recruiting">Recruiting</option>
                                <option value="probation">Probation</option>
                                <option value="contract">Contract</option>
                                <option value="inactive">Inactive</option>
                            </select>
                        </label>
                        <label>Tanggal masuk <input type="date" name="join_date" value="{{ old('join_date') }}"></label>
                        <label>Atasan <input name="manager_name" value="{{ old('manager_name') }}"></label>
                        <label>Lokasi kerja
                            <select name="work_location">
                                <option value="">Pilih</option>
                                @foreach($locations as $location)
                                    <option value="{{ $location }}" @selected(old('work_location') === $location)>{{ $location }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Cabang <input name="branch" value="{{ old('branch') }}" placeholder="Cabang / site"></label>
                        <label>Telepon <input name="phone" value="{{ old('phone') }}"></label>
                        <label>Bank <input name="bank_name" value="{{ old('bank_name') }}"></label>
                        <label>No rekening <input name="bank_account_number" value="{{ old('bank_account_number') }}"></label>
                        <label>Nama rekening <input name="bank_account_name" value="{{ old('bank_account_name') }}"></label>
                        <label>Gaji pokok <input type="number" min="0" name="base_salary" value="{{ old('base_salary', 0) }}"></label>
                        <label>Tunjangan tetap <input type="number" min="0" name="fixed_allowance" value="{{ old('fixed_allowance', 0) }}"></label>
                        <label>Status pajak <input name="tax_status" value="{{ old('tax_status') }}" placeholder="TK/0, K/1"></label>
                        <label>Target marketing <input type="number" min="0" name="marketing_target" value="{{ old('marketing_target', 0) }}"></label>
                        <label>Rate komisi marketing <input type="number" min="0" step="0.0001" name="commission_rate" value="{{ old('commission_rate', 0) }}"></label>
                        <label>Rate KPI produksi <input type="number" min="0" step="0.0001" name="production_kpi_rate" value="{{ old('production_kpi_rate', 0) }}"></label>
                    </div>
                    <div class="form-actions">
                        <button class="primary-button" type="submit"><x-icon name="user-plus" />Simpan Employee</button>
                    </div>
                </form>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2>Payroll</h2>
                <span>Kalkulasi otomatis + sync finance/accounting</span>
            </div>
            <div class="form-panel">
                <form class="record-form" method="post" action="{{ route('settings.employees.payrolls.store') }}">
                    @csrf
                    <div class="form-grid compact">
                        <label>Employee
                            <select name="user_id" required>
                                @foreach($employees as $employee)
                                    <option value="{{ $employee->id }}">{{ $employee->name }} - {{ $employee->employeeProfile?->employee_no ?: 'NO NIP' }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Periode <input type="month" name="period_month" value="{{ old('period_month', now()->format('Y-m')) }}" required></label>
                        <label>Gaji pokok override <input type="number" min="0" name="basic_salary" value="{{ old('basic_salary') }}" placeholder="Auto dari profile"></label>
                        <label>Tunjangan override <input type="number" min="0" name="allowance" value="{{ old('allowance') }}" placeholder="Auto dari tunjangan tetap"></label>
                        <label>Potongan manual <input type="number" min="0" name="deduction" value="{{ old('deduction', 0) }}"></label>
                        <label>Reimburse override <input type="number" min="0" name="reimbursement" value="{{ old('reimbursement') }}" placeholder="Auto dari reimburse approved/paid"></label>
                        <label>Lembur override <input type="number" min="0" name="overtime_amount" value="{{ old('overtime_amount') }}" placeholder="Auto dari pengajuan lembur"></label>
                        <label>Bonus <input type="number" min="0" name="bonus" value="{{ old('bonus', 0) }}"></label>
                        <label>THR <input type="number" min="0" name="thr" value="{{ old('thr', 0) }}"></label>
                        <label>Pajak override <input type="number" min="0" name="tax" value="{{ old('tax') }}" placeholder="Auto 5%"></label>
                        <label>Komisi marketing override <input type="number" min="0" name="marketing_commission" value="{{ old('marketing_commission') }}" placeholder="Auto dari pipeline"></label>
                        <label>Insentif produksi override <input type="number" min="0" name="production_incentive" value="{{ old('production_incentive') }}" placeholder="Auto dari output produksi"></label>
                        <label>Status
                            <select name="status" required>
                                <option value="draft">Draft</option>
                                <option value="released">Released</option>
                                <option value="paid">Paid</option>
                            </select>
                        </label>
                        <label class="full">Catatan <textarea name="notes" placeholder="Jika dikosongkan, komponen otomatis dihitung dari presensi, lembur, reimburse, komisi marketing, kinerja produksi, pajak, dan THR/bonus.">{{ old('notes') }}</textarea></label>
                    </div>
                    <div class="form-actions">
                        <button class="primary-button" type="submit"><x-icon name="calculator" />Hitung & Simpan Payroll</button>
                    </div>
                </form>
            </div>
        </section>
    </div>

    <div class="employee-section-grid">
        <section class="panel">
            <div class="panel-header">
                <h2>Recruitment & Onboarding</h2>
                <span>Meeting, wawancara, test</span>
            </div>
            <div class="form-panel">
                <form class="record-form" method="post" action="{{ route('settings.employees.recruitment.store') }}">
                    @csrf
                    <div class="form-grid compact">
                        <label>Nama kandidat <input name="candidate_name" value="{{ old('candidate_name') }}" required></label>
                        <label>Email <input type="email" name="email" value="{{ old('email') }}"></label>
                        <label>No telepon <input name="phone" value="{{ old('phone') }}"></label>
                        <label>Departemen
                            <select name="department">
                                <option value="">Pilih</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department }}">{{ $department }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Jabatan <input name="position" value="{{ old('position') }}"></label>
                        <label>Tahap
                            <select name="stage" required>
                                @foreach($recruitmentStages as $stage)
                                    <option value="{{ $stage }}">{{ str($stage)->headline() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Meeting <input type="datetime-local" name="meeting_at" value="{{ old('meeting_at') }}"></label>
                        <label>URL meeting <input name="meeting_url" value="{{ old('meeting_url') }}"></label>
                        <label>Interviewer <input name="interviewer" value="{{ old('interviewer') }}"></label>
                        <label>Nilai test <input type="number" min="0" max="100" name="test_score" value="{{ old('test_score') }}"></label>
                        <label>Mulai onboarding <input type="date" name="onboarding_start_date" value="{{ old('onboarding_start_date') }}"></label>
                        <label class="full">Catatan <textarea name="notes">{{ old('notes') }}</textarea></label>
                    </div>
                    <div class="form-actions">
                        <button class="primary-button" type="submit"><x-icon name="briefcase-business" />Simpan Kandidat</button>
                    </div>
                </form>
            </div>
            <div class="mini-list">
                @forelse($recruitmentCandidates as $candidate)
                    <div class="mini-item recruitment-item">
                        <div>
                            <strong>{{ $candidate->candidate_name }} - {{ $candidate->position ?: 'Posisi belum diisi' }}</strong>
                            <span>{{ strtoupper($candidate->stage) }} {{ $candidate->meeting_at ? '- '.$candidate->meeting_at->format('d M Y H:i') : '' }}</span>
                            @if($candidate->hrisRecord)
                                <a class="relation-chip" href="{{ route('records.index', ['module' => 'hris', 'q' => $candidate->hrisRecord->code]) }}"><x-icon name="link" /><span>{{ $candidate->hrisRecord->code }}</span></a>
                            @endif
                        </div>
                        <form class="inline-form" method="post" action="{{ route('settings.employees.recruitment.update', $candidate) }}">
                            @csrf
                            @method('PATCH')
                            <select name="stage">
                                @foreach($recruitmentStages as $stage)
                                    <option value="{{ $stage }}" @selected($candidate->stage === $stage)>{{ str($stage)->headline() }}</option>
                                @endforeach
                            </select>
                            <input class="table-input" type="datetime-local" name="meeting_at" value="{{ $candidate->meeting_at?->format('Y-m-d\TH:i') }}">
                            <input class="table-input" name="interviewer" value="{{ $candidate->interviewer }}" placeholder="Interviewer">
                            <input class="table-input" type="number" min="0" max="100" name="test_score" value="{{ $candidate->test_score }}" placeholder="Nilai">
                            <input type="hidden" name="meeting_url" value="{{ $candidate->meeting_url }}">
                            <input type="hidden" name="onboarding_start_date" value="{{ $candidate->onboarding_start_date?->toDateString() }}">
                            <input type="hidden" name="notes" value="{{ $candidate->notes }}">
                            <button class="ghost-button" type="submit"><x-icon name="save" />Update</button>
                        </form>
                    </div>
                @empty
                    <div class="empty-state">Belum ada kandidat recruitment.</div>
                @endforelse
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2>Daftar Presensi Pegawai</h2>
                <span>{{ $attendances->count() }} data terbaru</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Tanggal</th>
                            <th>Employee</th>
                            <th>Masuk</th>
                            <th>Pulang</th>
                            <th>Device / Telp</th>
                            <th>Source</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($attendances as $attendance)
                            <tr>
                                <td>{{ $attendance->attendance_date?->format('d M Y') }}</td>
                                <td>{{ $attendance->user?->name ?: '-' }}</td>
                                <td>{{ $attendance->clock_in_at?->format('H:i') ?: '--:--' }}</td>
                                <td>{{ $attendance->clock_out_at?->format('H:i') ?: '--:--' }}</td>
                                <td>{{ $attendance->device_id ?: '-' }}<br><span class="muted">{{ $attendance->phone_number ?: '-' }}</span></td>
                                <td>{{ strtoupper($attendance->source ?: 'DEVICE') }}</td>
                                <td><span class="status {{ $attendance->approval_status === 'approved' ? 'success' : 'warning' }}">{{ strtoupper($attendance->status) }}</span></td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="empty-state">Belum ada data presensi.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="employee-section-grid">
        <section class="panel">
            <div class="panel-header">
                <h2>Approval Pengajuan</h2>
                <span>{{ $pendingRequests->count() }} pending</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Tipe</th>
                            <th>Judul</th>
                            <th>Tanggal</th>
                            <th>Jam/Durasi</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pendingRequests as $employeeRequest)
                            @php
                                $requestPayload = $employeeRequest->payload ?? [];
                                $isHourlyRequest = in_array($employeeRequest->type, ['overtime', 'manual_attendance'], true);
                            @endphp
                            <tr>
                                <td>{{ $employeeRequest->user?->name }}</td>
                                <td><span class="status warning">{{ strtoupper(str_replace('_', ' ', $employeeRequest->type)) }}</span></td>
                                <td>{{ $employeeRequest->title }}</td>
                                <td>{{ $employeeRequest->start_date?->format('d M Y') ?: '-' }}</td>
                                <td>
                                    {{ $requestPayload['start_time'] ?? $requestPayload['clock_in_at'] ?? '--:--' }}
                                    -
                                    {{ $requestPayload['end_time'] ?? $requestPayload['clock_out_at'] ?? '--:--' }}
                                    @if($employeeRequest->total_days)
                                        <br><small>{{ number_format((float) $employeeRequest->total_days, 2, ',', '.') }} {{ $isHourlyRequest ? 'jam' : 'hari' }}</small>
                                    @endif
                                </td>
                                <td>
                                    <form class="inline-form" method="post" action="{{ route('settings.employees.requests.review', $employeeRequest) }}">
                                        @csrf
                                        @method('PATCH')
                                        <select name="status">
                                            <option value="approved">Approve</option>
                                            <option value="rejected">Reject</option>
                                        </select>
                                        <input class="table-input" name="review_note" placeholder="Catatan">
                                        <button class="ghost-button" type="submit"><x-icon name="check" />Simpan</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="empty-state">Tidak ada pengajuan pending.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2>Approval Reimburse</h2>
                <span>{{ $pendingReimbursements->count() }} pending</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Kategori</th>
                            <th>Judul</th>
                            <th>Nominal</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pendingReimbursements as $reimbursement)
                            <tr>
                                <td>{{ $reimbursement->user?->name }}</td>
                                <td>{{ $reimbursement->category }}</td>
                                <td>{{ $reimbursement->title }}</td>
                                <td>Rp {{ number_format((float) $reimbursement->amount, 0, ',', '.') }}</td>
                                <td>
                                    <form class="inline-form" method="post" action="{{ route('settings.employees.reimbursements.review', $reimbursement) }}">
                                        @csrf
                                        @method('PATCH')
                                        <select name="status">
                                            <option value="approved">Approve</option>
                                            <option value="paid">Paid</option>
                                            <option value="rejected">Reject</option>
                                        </select>
                                        <select name="payment_method">
                                            <option value="payroll" @selected(($reimbursement->payment_method ?? 'payroll') === 'payroll')>Gabung payroll</option>
                                            <option value="separate" @selected(($reimbursement->payment_method ?? 'payroll') === 'separate')>Bayar terpisah</option>
                                        </select>
                                        <input class="table-input" name="review_note" placeholder="Catatan">
                                        <button class="ghost-button" type="submit"><x-icon name="check" />Simpan</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="empty-state">Tidak ada reimburse pending.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <div class="employee-section-grid">
        <section class="panel">
            <div class="panel-header">
                <h2>Informasi Employee</h2>
                <span>{{ $announcements->count() }} publikasi</span>
            </div>
            <div class="form-panel">
                <form class="record-form" method="post" action="{{ route('settings.employees.announcements.store') }}">
                    @csrf
                    <div class="form-grid compact">
                        <label>Judul <input name="title" value="{{ old('title') }}" required></label>
                        <label>Kategori <input name="category" value="{{ old('category', 'info') }}" required></label>
                        <label>Audience
                            <select name="audience" required>
                                <option value="all">All</option>
                                <option value="employee">Employee</option>
                                @foreach($departments as $department)
                                    <option value="{{ $department }}">{{ $department }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>Publish <input type="datetime-local" name="publish_at" value="{{ old('publish_at') }}"></label>
                        <label class="full">Isi informasi <textarea name="body" required>{{ old('body') }}</textarea></label>
                        <label class="checkbox-row"><input type="checkbox" name="is_published" value="1" checked> Published</label>
                    </div>
                    <div class="form-actions">
                        <button class="primary-button" type="submit"><x-icon name="send" />Publikasikan</button>
                    </div>
                </form>
            </div>
            <div class="mini-list">
                @forelse($announcements as $announcement)
                    <div class="mini-item">
                        <div>
                            <strong>{{ $announcement->title }}</strong>
                            <span>{{ strtoupper($announcement->audience) }} - {{ $announcement->created_at->format('d M Y') }}</span>
                        </div>
                        <span class="status {{ $announcement->is_published ? 'success' : 'warning' }}">{{ $announcement->is_published ? 'LIVE' : 'DRAFT' }}</span>
                    </div>
                @empty
                    <div class="empty-state">Belum ada informasi.</div>
                @endforelse
            </div>
        </section>

        <section class="panel" id="payroll-list">
            <div class="panel-header">
                <div>
                    <h2>Payroll Terakhir</h2>
                    <span>{{ $payrolls->count() }} data tersimpan di Employee Payroll dan tersambung ke HRIS</span>
                </div>
            </div>
            <div class="mini-list">
                @forelse($payrolls as $payroll)
                    @php
                        $hrisRecord = $payrollHrisRecords[$payroll->id] ?? null;
                        $financeRecord = $hrisRecord?->outgoingLinks?->first(fn($link) => $link->targetRecord?->module === 'finance')?->targetRecord;
                    @endphp
                    <div class="mini-item">
                        <div>
                            <strong>{{ $payroll->user?->name }} - {{ $payroll->period_month->format('M Y') }}</strong>
                            <span>
                                Rp {{ number_format((float) $payroll->net_salary, 0, ',', '.') }}
                                @if($hrisRecord)
                                    - HRIS {{ $hrisRecord->code }}
                                @else
                                    - Menunggu sync HRIS
                                @endif
                            </span>
                        </div>
                        <div class="row-actions">
                            <span class="status {{ in_array($payroll->status, ['released', 'paid'], true) ? 'success' : 'warning' }}">{{ strtoupper($payroll->status) }}</span>
                            <a class="ghost-button" href="{{ route('employee.payrolls.slip', $payroll) }}"><x-icon name="file-text" />Slip</a>
                            @if($hrisRecord)
                                <a class="ghost-button" href="{{ route('records.index', ['module' => 'hris', 'q' => $hrisRecord->code]) }}"><x-icon name="id-card" />HRIS</a>
                            @endif
                            @if($financeRecord)
                                <a class="ghost-button" href="{{ route('records.index', ['module' => 'finance', 'q' => $financeRecord->code]) }}"><x-icon name="wallet" />Finance</a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="empty-state">Belum ada payroll.</div>
                @endforelse
            </div>
        </section>
    </div>

    <section class="panel hierarchy-panel">
        <div class="panel-header">
            <h2>Struktur Hirarki Jabatan</h2>
            <span>{{ $hierarchyProfiles->count() }} profile terstruktur</span>
        </div>
        <div class="hierarchy-list">
            @forelse($hierarchyProfiles->whereNull('manager_profile_id') as $profile)
                <div class="hierarchy-node">
                    <div>
                        <strong>{{ $profile->user?->name ?: $profile->employee_no }}</strong>
                        <span>{{ $profile->department ?: 'General' }} - {{ $profile->position ?: 'Employee' }} - {{ $profile->grade ?: $profile->job_level ?: 'No grade' }}</span>
                    </div>
                    <b>{{ $profile->directReports->count() }} bawahan</b>
                </div>
                @foreach($profile->directReports as $report)
                    <div class="hierarchy-node child">
                        <div>
                            <strong>{{ $report->user?->name ?: $report->employee_no }}</strong>
                            <span>{{ $report->department ?: 'General' }} - {{ $report->position ?: 'Employee' }} - {{ $report->branch ?: 'No branch' }}</span>
                        </div>
                        <b>{{ $report->directReports->count() }} bawahan</b>
                    </div>
                @endforeach
            @empty
                <div class="empty-state">Belum ada struktur hirarki.</div>
            @endforelse
        </div>
    </section>

    <section class="panel">
        <div class="panel-header">
            <h2>Data Employee</h2>
            <span>{{ $employees->count() }} akun</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th>Email</th>
                        <th>NIP</th>
                        <th>Departemen</th>
                        <th>Jabatan</th>
                        <th>Atasan</th>
                        <th>Cabang</th>
                        <th>Gaji Pokok</th>
                        <th>Status Kerja</th>
                        <th>Master SDM</th>
                        <th>Lokasi</th>
                        <th>Masuk</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employees as $employee)
                        <tr>
                            <td>
                                <span class="avatar-row">
                                    @if($employee->photo_path)
                                        <img class="mini-avatar" src="{{ asset($employee->photo_path) }}" alt="{{ $employee->name }}">
                                    @else
                                        <span class="mini-avatar fallback">{{ strtoupper(substr($employee->name, 0, 1)) }}</span>
                                    @endif
                                    <span>{{ $employee->name }}</span>
                                </span>
                            </td>
                            <td>{{ $employee->email }}</td>
                            <td>{{ $employee->employeeProfile?->employee_no ?: '-' }}</td>
                            <td>{{ $employee->employeeProfile?->department ?: '-' }}</td>
                            <td>{{ $employee->employeeProfile?->position ?: '-' }}</td>
                            <td>{{ $employee->employeeProfile?->managerProfile?->user?->name ?: ($employee->employeeProfile?->manager_name ?: '-') }}</td>
                            <td>{{ $employee->employeeProfile?->branch ?: '-' }}</td>
                            <td>Rp {{ number_format((float) ($employee->employeeProfile?->base_salary ?? 0), 0, ',', '.') }}</td>
                            <td><span class="status {{ $employee->employeeProfile?->employment_status === 'active' ? 'success' : 'warning' }}">{{ strtoupper($employee->employeeProfile?->employment_status ?: $employee->account_status) }}</span></td>
                            <td>
                                <span class="status success">EMPLOYEE PROFILE</span>
                            </td>
                            <td>{{ $employee->employeeProfile?->work_location ?: '-' }}</td>
                            <td>{{ $employee->employeeProfile?->join_date?->format('d M Y') ?: '-' }}</td>
                            <td>
                                @if($employee->employeeProfile)
                                    <div class="row-actions">
                                        <a class="ghost-button" href="{{ route('settings.employees.attendance', $employee->employeeProfile) }}"><x-icon name="calendar-days" />Presensi</a>
                                        <a class="ghost-button" href="{{ route('settings.employees.payrolls', $employee->employeeProfile) }}"><x-icon name="wallet" />Payroll</a>
                                        <a class="ghost-button" href="{{ route('settings.employees.requests', $employee->employeeProfile) }}"><x-icon name="clipboard-list" />Pengajuan</a>
                                        <a class="ghost-button" href="{{ route('settings.employees.leaves', $employee->employeeProfile) }}"><x-icon name="calendar-check" />Cuti/Izin</a>
                                        <a class="ghost-button" href="{{ route('settings.employees.overtimes', $employee->employeeProfile) }}"><x-icon name="clock" />Lembur</a>
                                        <a class="ghost-button" href="{{ route('settings.employees.edit', $employee->employeeProfile) }}"><x-icon name="pencil" />Edit</a>
                                    </div>
                                @else
                                    <span class="status warning">BELUM ADA PROFILE</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="13" class="empty-state">Belum ada employee.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
