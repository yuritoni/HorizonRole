@extends('layouts.app')

@section('title', 'Presensi '.$employee?->name.' - '.config('app.name'))

@section('actions')
    <a class="ghost-button" href="{{ route('settings.employees') }}"><x-icon name="arrow-left" />Employee Management</a>
    <a class="ghost-button" href="{{ route('settings.employees.edit', $profile) }}"><x-icon name="pencil" />Edit Employee</a>
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
    @endphp

    <header class="page-header">
        <div>
            <p class="eyebrow">Presensi Employee</p>
            <h1>{{ $employee?->name ?: $profile->employee_no }}</h1>
            <p>{{ $profile->employee_no }} - {{ $profile->department ?: 'Tanpa departemen' }} - {{ $profile->position ?: 'Employee' }}</p>
        </div>
        <div class="role-card">
            <span>Periode</span>
            <strong>{{ $monthNames[$month] }} {{ $year }}</strong>
        </div>
    </header>

    <form class="filter-row" method="get" action="{{ route('settings.employees.attendance', $profile) }}">
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
        <a class="ghost-button" href="{{ route('settings.employees.attendance', $profile) }}"><x-icon name="rotate-ccw" />Reset</a>
    </form>

    <div class="metrics-grid">
        <article class="metric-card">
            <span>Hari kerja</span>
            <strong>{{ $summary['working_days'] }}</strong>
            <small>Dihitung Senin sampai Jumat pada periode ini</small>
        </article>
        <article class="metric-card">
            <span>Hadir</span>
            <strong>{{ $summary['present_days'] }}</strong>
            <small>Presensi device atau manual approved/pending</small>
        </article>
        <article class="metric-card">
            <span>Tidak tercatat</span>
            <strong>{{ $summary['missing_working_days'] }}</strong>
            <small>Hari kerja tanpa record presensi</small>
        </article>
        <article class="metric-card">
            <span>Total jam</span>
            <strong>{{ number_format($summary['total_hours'], 2, ',', '.') }}</strong>
            <small>Akumulasi jam dari clock in sampai clock out</small>
        </article>
    </div>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Detail Presensi / Absensi</h2>
                <span>{{ $periodStart->format('d M Y') }} - {{ $periodEnd->format('d M Y') }}</span>
            </div>
            <span>{{ $summary['manual_days'] }} manual, {{ $summary['pending_approval'] }} pending approval</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Hari</th>
                        <th>Masuk</th>
                        <th>Pulang</th>
                        <th>Total Jam</th>
                        <th>Source</th>
                        <th>Status</th>
                        <th>Approval</th>
                        <th>Device / Telepon</th>
                        <th>Lokasi / Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        @php
                            $attendance = $row['attendance'];
                            $statusClass = $attendance
                                ? (in_array($attendance->status, ['present', 'manual_present'], true) ? 'success' : 'warning')
                                : ($row['is_weekend'] ? '' : 'warning');
                            $statusLabel = $attendance
                                ? strtoupper(str_replace('_', ' ', $attendance->status))
                                : ($row['is_weekend'] ? 'LIBUR' : 'TIDAK TERCATAT');
                        @endphp
                        <tr>
                            <td>{{ $row['date']->format('d M Y') }}</td>
                            <td>{{ $row['date']->translatedFormat('l') }}</td>
                            <td>{{ $attendance?->clock_in_at?->format('H:i') ?: '--:--' }}</td>
                            <td>{{ $attendance?->clock_out_at?->format('H:i') ?: '--:--' }}</td>
                            <td>{{ $row['work_hours'] > 0 ? number_format($row['work_hours'], 2, ',', '.').' jam' : '-' }}</td>
                            <td>{{ strtoupper($attendance?->source ?: ($row['is_weekend'] ? 'WEEKEND' : 'ABSEN')) }}</td>
                            <td><span class="status {{ $statusClass }}">{{ $statusLabel }}</span></td>
                            <td>
                                @if($attendance)
                                    <span class="status {{ $attendance->approval_status === 'approved' ? 'success' : 'warning' }}">{{ strtoupper($attendance->approval_status ?: 'PENDING') }}</span>
                                    @if($attendance->approver)
                                        <br><small>{{ $attendance->approver->name }}</small>
                                    @endif
                                @else
                                    <span class="muted">-</span>
                                @endif
                            </td>
                            <td>
                                {{ $attendance?->device_id ?: '-' }}
                                <br><span class="muted">{{ $attendance?->phone_number ?: '-' }}</span>
                            </td>
                            <td>
                                {{ $attendance?->clock_in_address ?: $attendance?->clock_out_address ?: '-' }}
                                @if($attendance?->notes)
                                    <br><span class="muted">{{ $attendance->notes }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
@endsection
