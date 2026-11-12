@extends('layouts.app')

@section('title', 'Payroll '.$employee?->name.' - '.config('app.name'))

@section('actions')
    <a class="ghost-button" href="{{ route('settings.employees') }}"><x-icon name="arrow-left" />Employee Management</a>
    <a class="ghost-button" href="{{ route('settings.employees.attendance', $profile) }}"><x-icon name="calendar-days" />Presensi</a>
    <a class="ghost-button" href="{{ route('settings.employees.requests', $profile) }}"><x-icon name="clipboard-list" />Pengajuan</a>
    <a class="ghost-button" href="{{ route('settings.employees.edit', $profile) }}"><x-icon name="pencil" />Edit</a>
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
            <p class="eyebrow">Payroll Employee</p>
            <h1>{{ $employee?->name ?: $profile->employee_no }}</h1>
            <p>{{ $profile->employee_no }} - {{ $profile->department ?: 'Tanpa departemen' }} - {{ $profile->position ?: 'Employee' }}</p>
        </div>
        <div class="role-card">
            <span>Periode</span>
            <strong>{{ $monthNames[$month] }} {{ $year }}</strong>
        </div>
    </header>

    <form class="filter-row" method="get" action="{{ route('settings.employees.payrolls', $profile) }}">
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
        <a class="ghost-button" href="{{ route('settings.employees.payrolls', $profile) }}"><x-icon name="rotate-ccw" />Reset</a>
    </form>

    <div class="metrics-grid">
        <article class="metric-card">
            <span>Payroll</span>
            <strong>{{ $summary['count'] }}</strong>
            <small>{{ $summary['draft'] }} draft, {{ $summary['released'] }} released, {{ $summary['paid'] }} paid</small>
        </article>
        <article class="metric-card">
            <span>Gross</span>
            <strong>Rp {{ number_format((float) $summary['gross_salary'], 0, ',', '.') }}</strong>
            <small>Gaji pokok, tunjangan, reimburse, lembur, bonus, THR</small>
        </article>
        <article class="metric-card">
            <span>Net</span>
            <strong>Rp {{ number_format((float) $summary['net_salary'], 0, ',', '.') }}</strong>
            <small>Nilai payroll setelah pajak dan potongan</small>
        </article>
        <article class="metric-card">
            <span>Benefit</span>
            <strong>Rp {{ number_format((float) ($summary['reimbursement'] + $summary['overtime_amount']), 0, ',', '.') }}</strong>
            <small>Reimburse + lembur yang masuk payroll</small>
        </article>
    </div>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Detail Payroll</h2>
                <span>{{ $periodStart->format('d M Y') }} - {{ $periodEnd->format('d M Y') }}</span>
            </div>
            <span>Tax Rp {{ number_format((float) $summary['tax'], 0, ',', '.') }} - Potongan Rp {{ number_format((float) $summary['deduction'], 0, ',', '.') }}</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Periode</th>
                        <th>Gaji Pokok</th>
                        <th>Tunjangan</th>
                        <th>Lembur</th>
                        <th>Reimburse</th>
                        <th>Bonus / THR</th>
                        <th>Pajak</th>
                        <th>Potongan</th>
                        <th>Gross</th>
                        <th>Net</th>
                        <th>Status</th>
                        <th>Integrasi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payrolls as $payroll)
                        @php
                            $hrisRecord = $payrollHrisRecords[(int) $payroll->id] ?? null;
                            $financeRecord = $hrisRecord?->outgoingLinks?->first(fn($link) => $link->targetRecord?->module === 'finance')?->targetRecord;
                            $accountingRecord = $hrisRecord?->outgoingLinks?->first(fn($link) => $link->targetRecord?->module === 'accounting')?->targetRecord
                                ?: $financeRecord?->outgoingLinks?->first(fn($link) => $link->targetRecord?->module === 'accounting')?->targetRecord;
                            $payment = $financeRecord?->payments?->sortByDesc('created_at')->first();
                            $statusClass = in_array($payroll->status, ['released', 'paid'], true) ? 'success' : 'warning';
                        @endphp
                        <tr>
                            <td>{{ $payroll->period_month?->format('M Y') ?: '-' }}</td>
                            <td>Rp {{ number_format((float) $payroll->basic_salary, 0, ',', '.') }}</td>
                            <td>Rp {{ number_format((float) ($payroll->allowance + $payroll->attendance_allowance), 0, ',', '.') }}</td>
                            <td>Rp {{ number_format((float) $payroll->overtime_amount, 0, ',', '.') }}</td>
                            <td>Rp {{ number_format((float) $payroll->reimbursement, 0, ',', '.') }}</td>
                            <td>Rp {{ number_format((float) ($payroll->bonus + $payroll->thr), 0, ',', '.') }}</td>
                            <td>Rp {{ number_format((float) $payroll->tax, 0, ',', '.') }}</td>
                            <td>Rp {{ number_format((float) ($payroll->deduction + $payroll->attendance_deduction), 0, ',', '.') }}</td>
                            <td>Rp {{ number_format((float) $payroll->gross_salary, 0, ',', '.') }}</td>
                            <td><strong>Rp {{ number_format((float) $payroll->net_salary, 0, ',', '.') }}</strong></td>
                            <td>
                                <span class="status {{ $statusClass }}">{{ strtoupper($payroll->status) }}</span>
                                @if($payroll->paid_at)
                                    <br><small>{{ $payroll->paid_at->format('d M Y H:i') }}</small>
                                @endif
                            </td>
                            <td>
                                <div class="row-actions">
                                    <a class="ghost-button" href="{{ route('employee.payrolls.slip', $payroll) }}"><x-icon name="file-text" />Slip</a>
                                    @if($hrisRecord)
                                        <a class="ghost-button" href="{{ route('records.index', ['module' => 'hris', 'q' => $hrisRecord->code]) }}"><x-icon name="id-card" />HRIS</a>
                                    @else
                                        <span class="status warning">HRIS belum sync</span>
                                    @endif
                                    @if($financeRecord)
                                        <a class="ghost-button" href="{{ route('records.index', ['module' => 'finance', 'q' => $financeRecord->code]) }}"><x-icon name="wallet" />Finance</a>
                                    @endif
                                    @if($accountingRecord)
                                        <a class="ghost-button" href="{{ route('records.index', ['module' => 'accounting', 'q' => $accountingRecord->code]) }}"><x-icon name="book-open" />Accounting</a>
                                    @endif
                                    @if($payment)
                                        <a class="ghost-button" href="{{ route('payments.show', $payment) }}"><x-icon name="credit-card" />Payment</a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="12" class="empty-state">Belum ada payroll untuk periode ini.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection
