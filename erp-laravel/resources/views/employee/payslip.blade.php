@extends('layouts.app')

@section('title', 'Slip Gaji - '.config('app.name'))

@section('actions')
    <button class="ghost-button" type="button" onclick="window.print()"><x-icon name="printer" />Print</button>
@endsection

@section('content')
    @php($components = $payroll->components ?? [])

    <header class="page-header">
        <div>
            <p class="eyebrow">Payroll</p>
            <h1>Slip Gaji {{ $payroll->period_month->format('M Y') }}</h1>
            <p>{{ $payroll->user->name }} - {{ $payroll->profile?->employee_no ?: '-' }}</p>
        </div>
        <div class="role-card">
            <span>Status</span>
            <strong>{{ strtoupper($payroll->status) }}</strong>
        </div>
    </header>

    <section class="panel payslip">
        <div class="payslip-head">
            <div>
                <strong>Horizon ERP</strong>
                <span>Employee Self Service Payroll</span>
            </div>
            <div>
                <span>Periode</span>
                <strong>{{ $payroll->period_month->format('M Y') }}</strong>
            </div>
        </div>

        <div class="payslip-info">
            <div>
                <span>Nama</span>
                <strong>{{ $payroll->user->name }}</strong>
            </div>
            <div>
                <span>NIP</span>
                <strong>{{ $payroll->profile?->employee_no ?: '-' }}</strong>
            </div>
            <div>
                <span>Departemen</span>
                <strong>{{ $payroll->profile?->department ?: '-' }}</strong>
            </div>
            <div>
                <span>Jabatan</span>
                <strong>{{ $payroll->profile?->position ?: '-' }}</strong>
            </div>
            <div>
                <span>Rekening</span>
                <strong>{{ $payroll->profile?->bank_name ?: '-' }} {{ $payroll->profile?->bank_account_number ?: '' }}</strong>
            </div>
            <div>
                <span>Dibayar</span>
                <strong>{{ $payroll->paid_at ? $payroll->paid_at->format('d M Y H:i') : '-' }}</strong>
            </div>
        </div>

        <div class="payslip-lines">
            <div>
                <span>Gaji Pokok</span>
                <strong>Rp {{ number_format((float) $payroll->basic_salary, 0, ',', '.') }}</strong>
            </div>
            <div>
                <span>Tunjangan</span>
                <strong>Rp {{ number_format((float) $payroll->allowance, 0, ',', '.') }}</strong>
            </div>
            <div>
                <span>Reimburse</span>
                <strong>Rp {{ number_format((float) $payroll->reimbursement, 0, ',', '.') }}</strong>
            </div>
            <div>
                <span>Lembur</span>
                <strong>Rp {{ number_format((float) $payroll->overtime_amount, 0, ',', '.') }}</strong>
            </div>
            <div>
                <span>Bonus</span>
                <strong>Rp {{ number_format((float) $payroll->bonus, 0, ',', '.') }}</strong>
            </div>
            <div>
                <span>THR</span>
                <strong>Rp {{ number_format((float) $payroll->thr, 0, ',', '.') }}</strong>
            </div>
            <div>
                <span>Komisi Marketing</span>
                <strong>Rp {{ number_format((float) $payroll->marketing_commission, 0, ',', '.') }}</strong>
            </div>
            <div>
                <span>Insentif Produksi</span>
                <strong>Rp {{ number_format((float) $payroll->production_incentive, 0, ',', '.') }}</strong>
            </div>
            <div>
                <span>Allowance Presensi</span>
                <strong>Rp {{ number_format((float) $payroll->attendance_allowance, 0, ',', '.') }}</strong>
            </div>
            <div>
                <span>Pajak</span>
                <strong>- Rp {{ number_format((float) $payroll->tax, 0, ',', '.') }}</strong>
            </div>
            <div>
                <span>Potongan Total</span>
                <strong>- Rp {{ number_format((float) $payroll->deduction, 0, ',', '.') }}</strong>
            </div>
        </div>

        @if($components)
            <div class="payslip-components">
                @foreach($components as $name => $value)
                    <div>
                        <span>{{ ucwords(str_replace('_', ' ', $name)) }}</span>
                        <strong>{{ is_numeric($value) ? number_format((float) $value, 2, ',', '.') : ($value ?: '-') }}</strong>
                    </div>
                @endforeach
            </div>
        @endif

        <div class="payslip-total gross">
            <span>Gross Salary</span>
            <strong>Rp {{ number_format((float) $payroll->gross_salary, 0, ',', '.') }}</strong>
        </div>

        <div class="payslip-total">
            <span>Total Diterima</span>
            <strong>Rp {{ number_format((float) $payroll->net_salary, 0, ',', '.') }}</strong>
        </div>

        @if($payroll->notes)
            <p class="payslip-note">{{ $payroll->notes }}</p>
        @endif
    </section>
@endsection
