@extends('layouts.app')

@section('title', 'Lembur '.$employee?->name.' - '.config('app.name'))

@section('actions')
    <a class="ghost-button" href="{{ route('settings.employees') }}"><x-icon name="arrow-left" />Employee Management</a>
    <a class="ghost-button" href="{{ route('settings.employees.requests', $profile) }}"><x-icon name="clipboard-list" />Semua Pengajuan</a>
    <a class="ghost-button" href="{{ route('settings.employees.payrolls', $profile) }}"><x-icon name="wallet" />Payroll</a>
    <a class="ghost-button" href="{{ route('settings.employees.attendance', $profile) }}"><x-icon name="calendar-days" />Presensi</a>
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
            <p class="eyebrow">Lembur Employee</p>
            <h1>{{ $employee?->name ?: $profile->employee_no }}</h1>
            <p>{{ $profile->employee_no }} - {{ $profile->department ?: 'Tanpa departemen' }} - {{ $profile->position ?: 'Employee' }}</p>
        </div>
        <div class="role-card">
            <span>Periode</span>
            <strong>{{ $monthNames[$month] }} {{ $year }}</strong>
        </div>
    </header>

    <form class="filter-row" method="get" action="{{ route('settings.employees.overtimes', $profile) }}">
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
        <a class="ghost-button" href="{{ route('settings.employees.overtimes', $profile) }}"><x-icon name="rotate-ccw" />Reset</a>
    </form>

    <div class="metrics-grid">
        <article class="metric-card">
            <span>Pengajuan lembur</span>
            <strong>{{ $summary['count'] }}</strong>
            <small>{{ $summary['pending'] }} masih pending approval</small>
        </article>
        <article class="metric-card">
            <span>Total jam</span>
            <strong>{{ number_format((float) $summary['total_hours'], 2, ',', '.') }}</strong>
            <small>Jam dari semua pengajuan lembur</small>
        </article>
        <article class="metric-card">
            <span>Jam approved</span>
            <strong>{{ number_format((float) $summary['approved_hours'], 2, ',', '.') }}</strong>
            <small>Masuk kalkulasi payroll otomatis</small>
        </article>
        <article class="metric-card">
            <span>Estimasi payroll</span>
            <strong>Rp {{ number_format((float) $summary['estimated_amount'], 0, ',', '.') }}</strong>
            <small>Rate Rp {{ number_format((float) $hourlyRate, 0, ',', '.') }} per jam</small>
        </article>
    </div>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Detail Lembur</h2>
                <span>{{ $periodStart->format('d M Y') }} - {{ $periodEnd->format('d M Y') }}</span>
            </div>
            <span>Lembur approved digabungkan ke payroll, bukan payable finance terpisah</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Judul</th>
                        <th>Jam Mulai</th>
                        <th>Jam Selesai</th>
                        <th>Total Jam</th>
                        <th>Estimasi Nilai</th>
                        <th>Status</th>
                        <th>HRIS</th>
                        <th>Catatan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($employeeRequests as $employeeRequest)
                        @php
                            $payload = $employeeRequest->payload ?? [];
                            $hours = (float) ($payload['total_hours'] ?? $employeeRequest->total_days ?? 0);
                            $amount = $employeeRequest->status === 'approved' ? $hours * $hourlyRate : 0;
                            $hrisRecord = $requestHrisRecords[(int) $employeeRequest->id] ?? null;
                            $statusClass = $employeeRequest->status === 'approved' ? 'success' : 'warning';
                        @endphp
                        <tr>
                            <td>{{ $employeeRequest->start_date?->format('d M Y') ?: '-' }}</td>
                            <td>{{ $employeeRequest->title }}</td>
                            <td>{{ $payload['start_time'] ?? '--:--' }}</td>
                            <td>{{ $payload['end_time'] ?? '--:--' }}</td>
                            <td>{{ number_format($hours, 2, ',', '.') }} jam</td>
                            <td>Rp {{ number_format((float) $amount, 0, ',', '.') }}</td>
                            <td><span class="status {{ $statusClass }}">{{ strtoupper($employeeRequest->status) }}</span></td>
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
                        <tr><td colspan="9" class="empty-state">Belum ada lembur untuk periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
