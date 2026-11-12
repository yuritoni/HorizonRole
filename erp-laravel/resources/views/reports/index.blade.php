@extends('layouts.app')

@section('title', 'Report Center - '.config('app.name'))

@section('actions')
    <a class="ghost-button" href="{{ route('reports.export', request()->query()) }}"><x-icon name="download" />Export CSV</a>
    <button class="ghost-button" type="button" onclick="window.print()"><x-icon name="printer" />Print</button>
@endsection

@section('content')
    @php($maxChartValue = max(1, $chartRows->max(fn($row) => (float) (($dataset['amount_column'] ?? null) ? ($row->amount ?? 0) : ($row->total ?? 0))) ?: 1))
    @php($currencyColumns = ['amount', 'allowance', 'deduction', 'reimbursement', 'customer_shipping_charge', 'courier_cost', 'insurance_cost', 'sales_price', 'standard_cost', 'unit_price', 'unit_cost', 'line_amount'])

    <header class="page-header report-hero">
        <div>
            <p class="eyebrow">Reportable ERP</p>
            <h1>Report Center</h1>
            <p>Analisa semua modul, sistem, dan tabel yang tersedia sesuai privilege akun.</p>
        </div>
        <div class="role-card">
            <span>Dataset aktif</span>
            <strong>{{ $dataset['label'] }}</strong>
        </div>
    </header>

    <section class="panel report-controls">
        <form method="get" action="{{ route('reports.index') }}" class="report-filter-grid">
            <label>
                <span>Dataset</span>
                <select name="dataset" onchange="this.form.submit()">
                    @foreach(collect($catalog)->groupBy('group') as $group => $items)
                        <optgroup label="{{ $group }}">
                            @foreach($items as $item)
                                <option value="{{ $item['key'] }}" @selected($item['key'] === $dataset['key'])>{{ $item['label'] }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </label>

            @if(($dataset['table'] ?? null) === 'erp_records' && count($dataset['allowed_modules'] ?? []) > 1)
                <label>
                    <span>Modul</span>
                    <select name="module">
                        <option value="">Semua modul</option>
                        @foreach($dataset['allowed_modules'] as $module)
                            <option value="{{ $module }}" @selected(request('module') === $module)>{{ $allModules[$module]['label'] ?? strtoupper($module) }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <label>
                <span>Pencarian</span>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari di dataset...">
            </label>

            @if(isset($dataset['status_column']))
                <label>
                    <span>Status</span>
                    <input type="text" name="status" value="{{ request('status') }}" placeholder="Contoh: Paid">
                </label>
            @endif

            @if(! empty($dataset['date_columns']))
                <label>
                    <span>Kolom Tanggal</span>
                    <select name="date_column">
                        @foreach($dataset['date_columns'] as $column => $label)
                            <option value="{{ $column }}" @selected(request('date_column', array_key_first($dataset['date_columns'])) === $column)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span>Dari</span>
                    <input type="date" name="date_from" value="{{ request('date_from') }}">
                </label>
                <label>
                    <span>Sampai</span>
                    <input type="date" name="date_to" value="{{ request('date_to') }}">
                </label>
            @endif

            @if(isset($dataset['amount_column']))
                <label>
                    <span>Min Amount</span>
                    <input type="number" name="min_amount" value="{{ request('min_amount') }}" placeholder="0">
                </label>
                <label>
                    <span>Max Amount</span>
                    <input type="number" name="max_amount" value="{{ request('max_amount') }}" placeholder="999999999">
                </label>
            @endif

            @if(! empty($dataset['groupable']))
                <label>
                    <span>Group Chart</span>
                    <select name="group_by">
                        @foreach($dataset['groupable'] as $column => $label)
                            <option value="{{ $column }}" @selected($groupBy === $column)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            @endif

            <div class="report-filter-actions">
                <button class="primary-button" type="submit"><x-icon name="check" />Apply</button>
                <a class="ghost-button" href="{{ route('reports.index', ['dataset' => $dataset['key']]) }}"><x-icon name="rotate-ccw" />Reset</a>
            </div>
        </form>
    </section>

    <div class="metrics-grid">
        <article class="metric-card">
            <span>Total Baris</span>
            <strong>{{ number_format($summary['total_rows'], 0, ',', '.') }}</strong>
            <small>Hasil setelah filter aktif</small>
        </article>
        <article class="metric-card">
            <span>Total Amount</span>
            <strong>{{ $summary['total_amount'] !== null ? 'Rp '.number_format($summary['total_amount'], 0, ',', '.') : '-' }}</strong>
            <small>Akumulasi nilai dataset</small>
        </article>
        <article class="metric-card">
            <span>Rata-rata</span>
            <strong>{{ $summary['avg_amount'] !== null ? 'Rp '.number_format($summary['avg_amount'], 0, ',', '.') : '-' }}</strong>
            <small>Average amount</small>
        </article>
        <article class="metric-card">
            <span>Max Amount</span>
            <strong>{{ $summary['max_amount'] !== null ? 'Rp '.number_format($summary['max_amount'], 0, ',', '.') : '-' }}</strong>
            <small>Nilai terbesar</small>
        </article>
    </div>

    <div class="dashboard-grid report-grid">
        <section class="panel">
            <div class="panel-header">
                <h2>Chart {{ $dataset['groupable'][$groupBy] ?? 'Group' }}</h2>
                <span>{{ $chartRows->count() }} group</span>
            </div>
            <div class="report-chart">
                @forelse($chartRows as $row)
                    @php($chartValue = (float) (($dataset['amount_column'] ?? null) ? ($row->amount ?? 0) : ($row->total ?? 0)))
                    <div class="report-bar-row">
                        <span>{{ $row->label }}</span>
                        <div class="report-bar-track">
                            <div class="report-bar" style="width: {{ max(4, ($chartValue / $maxChartValue) * 100) }}%"></div>
                        </div>
                        <strong>{{ isset($dataset['amount_column']) ? 'Rp '.number_format($chartValue, 0, ',', '.') : number_format($chartValue, 0, ',', '.') }}</strong>
                    </div>
                @empty
                    <div class="empty-state">Belum ada data chart.</div>
                @endforelse
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2>Dataset</h2>
                <span>{{ count($catalog) }} report</span>
            </div>
            <div class="mini-list">
                @foreach($catalog as $item)
                    <a class="mini-item {{ $item['key'] === $dataset['key'] ? 'is-active-report' : '' }}" href="{{ route('reports.index', ['dataset' => $item['key']]) }}">
                        <div>
                            <strong>{{ $item['label'] }}</strong>
                            <span>{{ $item['group'] }}</span>
                        </div>
                        <span class="status">{{ count($item['columns']) }}</span>
                    </a>
                @endforeach
            </div>
        </section>
    </div>

    <section class="panel report-table-panel">
        <div class="panel-header">
            <h2>Tabel Report</h2>
            <input id="reportTableSearch" class="report-table-search" type="search" placeholder="Quick filter di halaman ini...">
        </div>
        <div class="table-wrap">
            <table id="reportTable">
                <thead>
                    <tr>
                        @foreach($dataset['columns'] as $column => $label)
                            @php($direction = request('sort') === $column && request('direction') !== 'asc' ? 'asc' : 'desc')
                            <th>
                                <a href="{{ route('reports.index', array_merge(request()->query(), ['sort' => $column, 'direction' => $direction])) }}">
                                    {{ $label }}
                                </a>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            @foreach($dataset['columns'] as $column => $label)
                                @php($value = $row->{$column} ?? null)
                                <td>
                                    @if(in_array($column, $currencyColumns, true) || str_contains($column, 'salary'))
                                        Rp {{ number_format((float) $value, 0, ',', '.') }}
                                    @elseif($column === 'progress' && $value !== null)
                                        {{ (int) $value }}%
                                    @elseif(str_ends_with($column, '_at') || $column === 'due_date')
                                        {{ $value ? \Illuminate\Support\Carbon::parse($value)->format('d M Y H:i') : '-' }}
                                    @elseif(in_array($column, ['status', 'role', 'account_status', 'severity', 'fcm_status', 'provider', 'platform'], true))
                                        <span class="status {{ in_array($value, ['paid', 'success', 'active', 'admin'], true) ? 'success' : '' }}">{{ strtoupper($value ?: '-') }}</span>
                                    @else
                                        {{ $value ?: '-' }}
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($dataset['columns']) }}" class="empty-state">Tidak ada data report.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $rows->links() }}</div>
    </section>

    <script>
        const reportSearch = document.getElementById('reportTableSearch');
        const reportRows = [...document.querySelectorAll('#reportTable tbody tr')];

        reportSearch?.addEventListener('input', function () {
            const needle = this.value.toLowerCase();

            reportRows.forEach((row) => {
                row.hidden = ! row.innerText.toLowerCase().includes(needle);
            });
        });
    </script>
@endsection
