@extends('layouts.app')

@section('title', 'Cuti Izin Sakit '.$employee?->name.' - '.config('app.name'))

@section('actions')
    <a class="ghost-button" href="{{ route('settings.employees') }}"><x-icon name="arrow-left" />Employee Management</a>
    <a class="ghost-button" href="{{ route('settings.employees.requests', $profile) }}"><x-icon name="clipboard-list" />Semua Pengajuan</a>
    <a class="ghost-button" href="{{ route('settings.employees.attendance', $profile) }}"><x-icon name="calendar-days" />Presensi</a>
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
        $typeLabels = ['leave' => 'Cuti', 'permit' => 'Izin', 'sick' => 'Sakit'];
    @endphp

    <header class="page-header">
        <div>
            <p class="eyebrow">Izin / Cuti / Sakit</p>
            <h1>{{ $employee?->name ?: $profile->employee_no }}</h1>
            <p>{{ $profile->employee_no }} - {{ $profile->department ?: 'Tanpa departemen' }} - {{ $profile->position ?: 'Employee' }}</p>
        </div>
        <div class="role-card">
            <span>Periode</span>
            <strong>{{ $monthNames[$month] }} {{ $year }}</strong>
        </div>
    </header>

    <form class="filter-row" method="get" action="{{ route('settings.employees.leaves', $profile) }}">
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
        <a class="ghost-button" href="{{ route('settings.employees.leaves', $profile) }}"><x-icon name="rotate-ccw" />Reset</a>
    </form>

    <div class="metrics-grid">
        <article class="metric-card">
            <span>Total</span>
            <strong>{{ $summary['count'] }}</strong>
            <small>{{ $summary['pending'] }} masih pending approval</small>
        </article>
        <article class="metric-card">
            <span>Cuti</span>
            <strong>{{ $summary['leave'] }}</strong>
            <small>Pengajuan leave dari portal employee</small>
        </article>
        <article class="metric-card">
            <span>Izin / Sakit</span>
            <strong>{{ $summary['permit'] + $summary['sick'] }}</strong>
            <small>{{ $summary['permit'] }} izin, {{ $summary['sick'] }} sakit</small>
        </article>
        <article class="metric-card">
            <span>Approved</span>
            <strong>{{ number_format((float) $summary['approved_days'], 2, ',', '.') }}</strong>
            <small>Total hari approved pada periode ini</small>
        </article>
    </div>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Detail Cuti, Izin, dan Sakit</h2>
                <span>{{ $periodStart->format('d M Y') }} - {{ $periodEnd->format('d M Y') }}</span>
            </div>
            <span>Terhubung ke HRIS dan approval Employee/SDM</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Tipe</th>
                        <th>Judul</th>
                        <th>Durasi Hari</th>
                        <th>Status</th>
                        <th>Reviewer</th>
                        <th>HRIS</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employeeRequests as $employeeRequest)
                        @php
                            $hrisRecord = $requestHrisRecords[(int) $employeeRequest->id] ?? null;
                            $statusClass = $employeeRequest->status === 'approved' ? 'success' : 'warning';
                        @endphp
                        <tr>
                            <td>
                                {{ $employeeRequest->start_date?->format('d M Y') ?: '-' }}
                                @if($employeeRequest->end_date && $employeeRequest->end_date->ne($employeeRequest->start_date))
                                    <br><small>s/d {{ $employeeRequest->end_date->format('d M Y') }}</small>
                                @endif
                            </td>
                            <td><span class="status">{{ $typeLabels[$employeeRequest->type] ?? strtoupper($employeeRequest->type) }}</span></td>
                            <td>{{ $employeeRequest->title }}</td>
                            <td>{{ number_format((float) $employeeRequest->total_days, 2, ',', '.') }}</td>
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
                        <tr><td colspan="8" class="empty-state">Belum ada cuti, izin, atau sakit untuk periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
