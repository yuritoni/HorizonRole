@extends('layouts.app')

@php
    $totalBilled = $allPlans->sum(fn ($plan) => (float) $plan->total_amount);
    $paidAmount = $allPlans->sum(fn ($plan) => (float) $plan->installments->where('status', 'paid')->sum('amount'));
    $outstandingAmount = max($totalBilled - $paidAmount, 0);
    $overdueCount = $allPlans->sum(fn ($plan) => $plan->installments
        ->where('status', '!=', 'paid')
        ->filter(fn ($installment) => $installment->due_date && $installment->due_date->lt(today()))
        ->count());
    $selectedSales = $availableSales->firstWhere('id', $selectedSalesId);
    $selectedGross = $selectedSales ? round(((float) $selectedSales->amount) * 1.11, 2) : null;
@endphp

@section('title', 'Cicilan Penjualan - '.config('app.name'))

@section('actions')
    <a class="ghost-button" href="{{ route('records.index', 'sales') }}"><x-icon name="arrow-left" />Penjualan</a>
@endsection

@section('content')
    <header class="page-header">
        <div>
            <p class="eyebrow">Order to Cash</p>
            <h1>Manajemen Cicilan Penjualan</h1>
            <p>Jadwal termin dari sales order, AR Invoice Finance per cicilan, VA, pembayaran Midtrans, dan posting Accounting tersambung otomatis.</p>
        </div>
        <div class="role-card">
            <span>Outstanding</span>
            <strong>Rp {{ number_format($outstandingAmount, 0, ',', '.') }}</strong>
        </div>
    </header>

    <div class="metrics-grid">
        <article class="metric-card">
            <span>Total Plan</span>
            <strong>{{ number_format($allPlans->count(), 0, ',', '.') }}</strong>
            <small>Sales order memakai cicilan</small>
        </article>
        <article class="metric-card">
            <span>Total Tagihan</span>
            <strong>Rp {{ number_format($totalBilled, 0, ',', '.') }}</strong>
            <small>Gross termasuk PPN 11%</small>
        </article>
        <article class="metric-card">
            <span>Terbayar</span>
            <strong>Rp {{ number_format($paidAmount, 0, ',', '.') }}</strong>
            <small>Termin sudah paid di Finance</small>
        </article>
        <article class="metric-card">
            <span>Overdue</span>
            <strong>{{ number_format($overdueCount, 0, ',', '.') }}</strong>
            <small>Cicilan melewati jatuh tempo</small>
        </article>
    </div>

    <div class="dashboard-grid">
        <section class="panel form-panel">
            <div class="panel-header no-border">
                <div>
                    <h2>Buat Jadwal Cicilan</h2>
                    <span>Sales order dipilih dari modul Penjualan, lalu Finance dibuat per termin.</span>
                </div>
            </div>

            @if($canManage)
                <form class="record-form" method="post" action="{{ route('sales.installments.store') }}">
                    @csrf
                    <div class="form-grid">
                        <label class="full">
                            Sales Order
                            <select name="sales_record_id" required>
                                <option value="">Pilih sales order</option>
                                @foreach($availableSales as $sales)
                                    @php
                                        $gross = (float) ($sales->extra['gross_amount'] ?? round(((float) $sales->amount) * 1.11, 2));
                                    @endphp
                                    <option value="{{ $sales->id }}" @selected((int) old('sales_record_id', $selectedSalesId) === $sales->id)>
                                        {{ $sales->code }} - {{ $sales->title }} - Rp {{ number_format($gross, 0, ',', '.') }}
                                    </option>
                                @endforeach
                            </select>
                            @error('sales_record_id') <small class="field-error">{{ $message }}</small> @enderror
                        </label>

                        <label>
                            Total Tagihan Cicilan
                            <input type="number" name="total_amount" min="1" step="0.01" value="{{ old('total_amount', $selectedGross) }}" placeholder="Otomatis gross + PPN">
                            @error('total_amount') <small class="field-error">{{ $message }}</small> @enderror
                        </label>

                        <label>
                            Uang Muka
                            <input type="number" name="down_payment" min="0" step="0.01" value="{{ old('down_payment', 0) }}">
                            @error('down_payment') <small class="field-error">{{ $message }}</small> @enderror
                        </label>

                        <label>
                            Jumlah Termin
                            <input type="number" name="installment_count" min="2" max="36" value="{{ old('installment_count', 3) }}" required>
                            @error('installment_count') <small class="field-error">{{ $message }}</small> @enderror
                        </label>

                        <label>
                            Interval Bulan
                            <input type="number" name="interval_months" min="1" max="12" value="{{ old('interval_months', 1) }}" required>
                            @error('interval_months') <small class="field-error">{{ $message }}</small> @enderror
                        </label>

                        <label>
                            Jatuh Tempo Pertama
                            <input type="date" name="first_due_date" value="{{ old('first_due_date', now()->addMonth()->toDateString()) }}" required>
                            @error('first_due_date') <small class="field-error">{{ $message }}</small> @enderror
                        </label>

                        <label class="full">
                            Catatan
                            <textarea name="notes" rows="3">{{ old('notes') }}</textarea>
                            @error('notes') <small class="field-error">{{ $message }}</small> @enderror
                        </label>
                    </div>

                    <div class="form-actions">
                        <button class="primary-button" type="submit"><x-icon name="calendar-clock" />Buat Cicilan</button>
                    </div>
                </form>
            @else
                <div class="empty-state">Akun ini hanya bisa melihat jadwal cicilan.</div>
            @endif
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Sales Siap Cicilan</h2>
                    <span>{{ $availableSales->count() }} sales order belum memiliki jadwal</span>
                </div>
            </div>
            <div class="mini-list compact-list">
                @forelse($availableSales->take(8) as $sales)
                    @php
                        $gross = (float) ($sales->extra['gross_amount'] ?? round(((float) $sales->amount) * 1.11, 2));
                    @endphp
                    <a class="mini-item" href="{{ route('sales.installments.index', ['sales_id' => $sales->id]) }}">
                        <div class="module-card-title">
                            <x-icon name="shopping-cart" />
                            <div>
                                <strong>{{ $sales->code }} - {{ $sales->title }}</strong>
                                <span>{{ $sales->status }} - gross Rp {{ number_format($gross, 0, ',', '.') }}</span>
                            </div>
                        </div>
                        <x-icon name="chevron-right" />
                    </a>
                @empty
                    <div class="empty-state">Tidak ada sales order yang perlu dibuat cicilan.</div>
                @endforelse
            </div>
        </section>
    </div>

    <form class="filter-row" method="get" action="{{ route('sales.installments.index') }}">
        <input type="search" name="q" value="{{ $search }}" placeholder="Cari sales, customer, status cicilan...">
        <button class="ghost-button" type="submit"><x-icon name="filter" />Filter</button>
        <a class="ghost-button" href="{{ route('sales.installments.index') }}"><x-icon name="rotate-ccw" />Reset</a>
    </form>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Daftar Cicilan</h2>
                <span>{{ $plans->total() }} plan</span>
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Sales</th>
                        <th>Customer</th>
                        <th>Plan</th>
                        <th>Progress</th>
                        <th>Jadwal Cicilan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($plans as $plan)
                        @php
                            $paidCount = $plan->installments->where('status', 'paid')->count();
                            $paid = (float) $plan->installments->where('status', 'paid')->sum('amount');
                            $outstanding = max((float) $plan->total_amount - $paid, 0);
                            $percent = (float) $plan->total_amount > 0 ? min(100, round(($paid / (float) $plan->total_amount) * 100)) : 0;
                        @endphp
                        <tr>
                            <td>
                                @if($plan->salesRecord)
                                    <a class="relation-chip" href="{{ route('records.index', ['module' => 'sales', 'q' => $plan->salesRecord->code]) }}">
                                        <x-icon name="shopping-cart" />
                                        <span>{{ $plan->salesRecord->code }}</span>
                                    </a>
                                @else
                                    <span class="muted">Sales terhapus</span>
                                @endif
                            </td>
                            <td>
                                <strong>{{ $plan->customer ?: '-' }}</strong>
                                <br>
                                <span class="muted">Rp {{ number_format($plan->total_amount, 0, ',', '.') }}</span>
                            </td>
                            <td>
                                <span class="status {{ $plan->status === 'paid' ? 'success' : ($plan->status === 'overdue' ? 'warning' : '') }}">{{ strtoupper($plan->status) }}</span>
                                <br>
                                <span class="muted">{{ $plan->installment_count }} termin, interval {{ $plan->interval_months }} bulan</span>
                            </td>
                            <td>
                                <div class="report-bar-track">
                                    <div class="report-bar" style="width: {{ $percent }}%"></div>
                                </div>
                                <span class="muted">{{ $paidCount }}/{{ $plan->installments->count() }} termin paid</span>
                                <br>
                                <span class="muted">Outstanding Rp {{ number_format($outstanding, 0, ',', '.') }}</span>
                            </td>
                            <td>
                                <div class="mini-list compact-list">
                                    @foreach($plan->installments->sortBy('installment_no') as $installment)
                                        @php
                                            $financeRecord = $installment->financeRecord;
                                            $canOpenFinance = $financeRecord && auth()->user()->hasPrivilege('modules.finance.view');
                                            $canPay = $financeRecord
                                                && \App\Support\PaymentRules::isPayable($financeRecord)
                                                && auth()->user()->hasPrivilege('payments.create')
                                                && auth()->user()->hasPrivilege('modules.finance.pay');
                                        @endphp
                                        <div class="mini-item">
                                            <div>
                                                <strong>{{ $installment->invoice_no }}</strong>
                                                <span>Termin {{ $installment->installment_no }} - {{ optional($installment->due_date)->format('d M Y') }} - Rp {{ number_format($installment->amount, 0, ',', '.') }}</span>
                                                <span>VA {{ $installment->virtual_account }} - {{ strtoupper($installment->status) }}</span>
                                            </div>
                                            <div class="row-actions">
                                                @if($canOpenFinance)
                                                    <a class="icon-link" href="{{ route('records.index', ['module' => 'finance', 'q' => $financeRecord->code]) }}"><x-icon name="landmark" />Finance</a>
                                                @endif
                                                @if($canPay)
                                                    <a class="icon-link pay-text" href="{{ route('payments.create', $financeRecord) }}"><x-icon name="credit-card" />Bayar</a>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="empty-state">Belum ada jadwal cicilan penjualan.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $plans->links() }}</div>
    </section>
@endsection
