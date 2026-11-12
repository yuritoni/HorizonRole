@extends('layouts.app')

@section('title', 'Pengajuan '.$employee?->name.' - '.config('app.name'))

@section('actions')
    <a class="ghost-button" href="{{ route('settings.employees') }}"><x-icon name="arrow-left" />Employee Management</a>
    <a class="ghost-button" href="{{ route('settings.employees.attendance', $profile) }}"><x-icon name="calendar-days" />Presensi</a>
    <a class="ghost-button" href="{{ route('settings.employees.leaves', $profile) }}"><x-icon name="calendar-check" />Cuti/Izin</a>
    <a class="ghost-button" href="{{ route('settings.employees.overtimes', $profile) }}"><x-icon name="clock" />Lembur</a>
@endsection

@section('content')
    @php
        $monthNames = [
            1 => 'Januari',
            2 => 'Februari',
            3 => 'Maret',
            4 => 'April',
            5 => 'Mei',
            6 => 'Juni',
            7 => 'Juli',
            8 => 'Agustus',
            9 => 'September',
            10 => 'Oktober',
            11 => 'November',
            12 => 'Desember',
        ];
        $typeLabels = [
            'leave' => 'Cuti',
            'permit' => 'Izin',
            'sick' => 'Sakit',
            'overtime' => 'Lembur',
            'manual_attendance' => 'Presensi Manual',
            'training' => 'Pelatihan',
            'business_trip' => 'Perjalanan Dinas',
            'data_change' => 'Perubahan Data',
            'information' => 'Informasi',
        ];
    @endphp

    <header class="page-header">
        <div>
            <p class="eyebrow">Pengajuan Employee</p>
            <h1>{{ $employee?->name ?: $profile->employee_no }}</h1>
            <p>{{ $profile->employee_no }} - {{ $profile->department ?: 'Tanpa departemen' }} - {{ $profile->position ?: 'Employee' }}</p>
        </div>
        <div class="role-card">
            <span>Periode</span>
            <strong>{{ $monthNames[$month] }} {{ $year }}</strong>
        </div>
    </header>

    <form class="filter-row" method="get" action="{{ route('settings.employees.requests', $profile) }}">
        <select name="month">
            @foreach($monthNames as $monthNumber => $monthLabel)
                <option value="{{ $monthNumber }}" @selected((int) $month === (int) $monthNumber)>{{ $monthLabel }}</option>
            @endforeach
        </select>
        <select name="year">
            @foreach($years as $yearOption)
                <option value="{{ $yearOption }}" @selected((int) $year === (int) $yearOption)>{{ $yearOption }}</option>
            @endforeach
        </select>
        <button class="ghost-button" type="submit"><x-icon name="filter" />Tampilkan</button>
        <a class="ghost-button" href="{{ route('settings.employees.requests', $profile) }}"><x-icon name="rotate-ccw" />Reset</a>
    </form>

    <div class="metrics-grid">
        <article class="metric-card">
            <span>Total pengajuan</span>
            <strong>{{ $summary['count'] }}</strong>
            <small>{{ $summary['hris_linked'] }} sudah punya record HRIS</small>
        </article>
        <article class="metric-card">
            <span>Pending</span>
            <strong>{{ $summary['submitted'] }}</strong>
            <small>Masih menunggu approval HR/atasan</small>
        </article>
        <article class="metric-card">
            <span>Approved</span>
            <strong>{{ $summary['approved'] }}</strong>
            <small>Sudah masuk proses operasional HRIS</small>
        </article>
        <article class="metric-card">
            <span>Durasi</span>
            <strong>{{ number_format((float) $summary['duration'], 2, ',', '.') }}</strong>
            <small>Hari/jam sesuai tipe pengajuan</small>
        </article>
    </div>

    @if($typeGroups->isNotEmpty())
        <section class="panel">
            <div class="panel-header">
                <h2>Ringkasan Per Tipe</h2>
                <span>{{ $typeGroups->count() }} tipe pengajuan pada periode ini</span>
            </div>
            <div class="mini-list">
                @foreach($typeGroups as $group)
                    <div class="mini-item">
                        <div>
                            <strong>{{ $group['label'] }}</strong>
                            <span>{{ $group['pending'] }} pending approval</span>
                        </div>
                        <b>{{ $group['count'] }}</b>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Detail Pengajuan</h2>
                <span>{{ $periodStart->format('d M Y') }} - {{ $periodEnd->format('d M Y') }}</span>
            </div>
            <span>{{ $summary['rejected'] }} rejected</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Tipe</th>
                        <th>Judul</th>
                        <th>Durasi</th>
                        <th>Jam</th>
                        <th>Status</th>
                        <th>Reviewer</th>
                        <th>HRIS</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employeeRequests as $employeeRequest)
                        @php
                            $payload = $employeeRequest->payload ?? [];
                            $hrisRecord = $requestHrisRecords[(int) $employeeRequest->id] ?? null;
                            $statusClass = $employeeRequest->status === 'approved' ? 'success' : 'warning';
                            $typeLabel = $typeLabels[$employeeRequest->type] ?? str($employeeRequest->type)->replace('_', ' ')->headline();
                        @endphp
                        <tr>
                            <td>
                                {{ $employeeRequest->start_date?->format('d M Y') ?: '-' }}
                                @if($employeeRequest->end_date && $employeeRequest->end_date->ne($employeeRequest->start_date))
                                    <br><small>s/d {{ $employeeRequest->end_date->format('d M Y') }}</small>
                                @endif
                            </td>
                            <td><span class="status">{{ $typeLabel }}</span></td>
                            <td>{{ $employeeRequest->title }}</td>
                            <td>{{ number_format((float) $employeeRequest->total_days, 2, ',', '.') }}</td>
                            <td>{{ $payload['start_time'] ?? $payload['clock_in_at'] ?? '--:--' }} - {{ $payload['end_time'] ?? $payload['clock_out_at'] ?? '--:--' }}</td>
                            <td><span class="status {{ $statusClass }}">{{ strtoupper($employeeRequest->status) }}</span></td>
                            <td>
                                {{ $employeeRequest->reviewer?->name ?: '-' }}
                                @if($employeeRequest->reviewed_at)
                                    <br><small>{{ $employeeRequest->reviewed_at->format('d M Y H:i') }}</small>
                                @endif
                            </td>
                            <td>
                                @if($hrisRecord)
                                    <a class="ghost-button" href="{{ route('records.index', ['module' => 'hris', 'q' => $hrisRecord->code]) }}"><x-icon name="id-card" />{{ $hrisRecord->code }}</a>
                                @else
                                    <span class="status warning">Belum sync</span>
                                @endif
                            </td>
                            <td>
                                {{ $employeeRequest->description ?: '-' }}
                                @if($employeeRequest->review_note)
                                    <br><span class="muted">{{ $employeeRequest->review_note }}</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="empty-state">Belum ada pengajuan untuk periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
