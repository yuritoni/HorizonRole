@extends('layouts.app')

@php
    $labels = \App\Support\ErpModules::labels();
    $attentionStatuses = ['Waiting Approval', 'Low Stock', 'Overdue', 'Delayed', 'At Risk', 'Blocked', 'Lost'];
    $closedStatuses = ['Paid', 'Received', 'Closed', 'Posted', 'Won', 'Completed'];
    $canStartPayment = fn ($record) => \App\Support\PaymentRules::isPayable($record);
    $hrisActivityGroups = collect();
    $hrisOperationalGroups = collect();
    $hrisTypeGroups = collect();
    $hrisEmployeeGroups = collect();

    if (($module ?? null) === 'hris') {
        $hrisActivityGroups = collect([
            ['label' => 'Presensi & Waktu', 'icon' => 'clock-3', 'categories' => ['Attendance', 'Manual Attendance', 'Overtime']],
            ['label' => 'Pengajuan People', 'icon' => 'clipboard-check', 'categories' => ['Leave Request', 'Permit', 'Sick Leave', 'Training', 'Business Trip', 'Data Change', 'Information']],
            ['label' => 'Payroll & Benefit', 'icon' => 'wallet-cards', 'categories' => ['Payroll Event', 'Reimbursement']],
            ['label' => 'Recruitment & Onboarding', 'icon' => 'briefcase-business', 'categories' => ['Recruitment', 'Interview', 'Test', 'Onboarding']],
        ])->map(function ($group) use ($allRecords) {
            $records = $allRecords->filter(fn ($record) => in_array($record->category, $group['categories'], true));
            return array_merge($group, [
                'count' => $records->count(),
                'amount' => $records->sum('amount'),
                'pending' => $records->whereIn('status', ['Draft', 'Scheduled', 'Pending Approval', 'Interview', 'Test', 'Onboarding'])->count(),
            ]);
        });

        $needLinkRecords = $allRecords->filter(fn ($record) => empty($record->extra['employee_profile_id'])
            && ($record->extra['source_master'] ?? null) !== 'employee_profiles_summary'
            && ! in_array($record->category, ['Recruitment', 'Interview', 'Test', 'Onboarding'], true));
        $hrisOperationalGroups = collect([
            ['label' => 'Menunggu HR', 'icon' => 'hourglass', 'records' => $allRecords->whereIn('status', ['Draft', 'Scheduled', 'Pending Approval'])],
            ['label' => 'Disetujui / Diproses', 'icon' => 'check-circle-2', 'records' => $allRecords->whereIn('status', ['Approved', 'Processed'])],
            ['label' => 'Ditolak', 'icon' => 'x-circle', 'records' => $allRecords->where('status', 'Rejected')],
            ['label' => 'Perlu Link Employee', 'icon' => 'link-2-off', 'records' => $needLinkRecords],
        ]);

        $hrisTypeGroups = $allRecords
            ->groupBy(fn ($record) => $record->category ?: 'Tanpa tipe')
            ->map(fn ($items, $type) => [
                'type' => $type,
                'count' => $items->count(),
                'amount' => $items->sum('amount'),
                'pending' => $items->whereIn('status', ['Draft', 'Scheduled', 'Pending Approval'])->count(),
            ])
            ->sortByDesc('count')
            ->values();

        $hrisEmployeeGroups = $allRecords
            ->filter(fn ($record) => ! empty($record->extra['employee_profile_id']) || ! empty($record->extra['employee_name']))
            ->groupBy(fn ($record) => $record->extra['employee_name'] ?? $record->title ?? 'Tanpa employee')
            ->map(fn ($items, $employeeName) => [
                'employee' => $employeeName,
                'count' => $items->count(),
                'amount' => $items->sum('amount'),
                'pending' => $items->whereIn('status', ['Draft', 'Scheduled', 'Pending Approval'])->count(),
                'latest' => $items->sortByDesc('created_at')->first(),
            ])
            ->sortByDesc('count')
            ->values();
    }
@endphp

@section('title', $config['label'].' - '.config('app.name'))

@section('actions')
    @if($module === 'sales' && auth()->user()->hasPrivilege('modules.sales.view'))
        <a class="ghost-button" href="{{ route('products.index') }}"><x-icon name="package" />Produk</a>
        <a class="ghost-button" href="{{ route('sales.shipments.index') }}"><x-icon name="truck" />Pengiriman</a>
        <a class="ghost-button" href="{{ route('sales.installments.index') }}"><x-icon name="calendar-clock" />Cicilan</a>
    @endif
    @if($module === 'manufacturing' && auth()->user()->hasPrivilege('modules.manufacturing.view'))
        <a class="ghost-button" href="{{ route('products.index') }}"><x-icon name="package" />Produk & BOM</a>
    @endif
    @if($module === 'accounting' && auth()->user()->hasPrivilege('modules.accounting.view'))
        <a class="ghost-button" href="{{ route('accounting.ledger') }}"><x-icon name="book-open-check" />General Ledger</a>
    @endif
    @if($privileges['create'])
        <a class="primary-button" href="{{ route('records.create', $module) }}"><x-icon name="plus" />Tambah</a>
    @endif
@endsection

@section('content')
    <header class="page-header">
        <div>
            <p class="eyebrow">{{ $config['group'] }}</p>
            <h1>{{ $config['label'] }}</h1>
            <p>{{ $config['description'] }}</p>
        </div>
        <div class="role-card">
            <span>Total nilai</span>
            <strong>Rp {{ number_format($allRecords->sum('amount'), 0, ',', '.') }}</strong>
        </div>
    </header>

    <div class="metrics-grid">
        <article class="metric-card">
            <span>Total Record</span>
            <strong>{{ number_format($allRecords->count(), 0, ',', '.') }}</strong>
            <small>Data pada modul {{ strtolower($config['label']) }}</small>
        </article>
        <article class="metric-card">
            <span>Nilai</span>
            <strong>Rp {{ number_format($allRecords->sum('amount'), 0, ',', '.') }}</strong>
            <small>Akumulasi transaksi atau valuasi</small>
        </article>
        <article class="metric-card">
            <span>Atensi</span>
            <strong>{{ $allRecords->whereIn('status', $attentionStatuses)->count() }}</strong>
            <small>Status perlu follow up</small>
        </article>
        <article class="metric-card">
            <span>Closed</span>
            <strong>{{ $allRecords->whereIn('status', $closedStatuses)->count() }}</strong>
            <small>Selesai, paid, atau posted</small>
        </article>
    </div>

    <form class="filter-row" method="get" action="{{ route('records.index', $module) }}">
        <input type="search" name="q" value="{{ $search }}" placeholder="Cari {{ strtolower($config['label']) }}...">
        <select name="status">
            <option value="all">Semua status</option>
            @foreach($config['statuses'] as $status)
                <option value="{{ $status }}" @selected($activeStatus === $status)>{{ $status }}</option>
            @endforeach
        </select>
        <button class="ghost-button" type="submit"><x-icon name="filter" />Filter</button>
        <a class="ghost-button" href="{{ route('records.index', $module) }}"><x-icon name="rotate-ccw" />Reset</a>
    </form>

    @if($module === 'hris')
        <div class="employee-section-grid">
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Per Kegiatan</h2>
                        <span>Cluster aktivitas utama HRIS</span>
                    </div>
                </div>
                <div class="mini-list">
                    @foreach($hrisActivityGroups as $group)
                        <a class="mini-item" href="{{ route('records.index', ['module' => $module, 'q' => $group['categories'][0]]) }}">
                            <div class="module-card-title">
                                <x-icon :name="$group['icon']" />
                                <div>
                                    <strong>{{ $group['label'] }}</strong>
                                    <span>{{ $group['count'] }} record - {{ $group['pending'] }} perlu tindak lanjut</span>
                                </div>
                            </div>
                            <b>Rp {{ number_format($group['amount'], 0, ',', '.') }}</b>
                        </a>
                    @endforeach
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Per Operasional</h2>
                        <span>Status kerja HR, payroll, dan link employee</span>
                    </div>
                </div>
                <div class="mini-list">
                    @foreach($hrisOperationalGroups as $group)
                        <div class="mini-item">
                            <div class="module-card-title">
                                <x-icon :name="$group['icon']" />
                                <div>
                                    <strong>{{ $group['label'] }}</strong>
                                    <span>{{ $group['records']->count() }} record</span>
                                </div>
                            </div>
                            <span class="status {{ $group['records']->count() > 0 ? 'warning' : 'success' }}">{{ $group['records']->count() }}</span>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Per Type</h2>
                        <span>Kategori HRIS terbanyak</span>
                    </div>
                </div>
                <div class="mini-list compact-list">
                    @forelse($hrisTypeGroups->take(8) as $group)
                        <a class="mini-item" href="{{ route('records.index', ['module' => $module, 'q' => $group['type']]) }}">
                            <div>
                                <strong>{{ $group['type'] }}</strong>
                                <span>{{ $group['count'] }} record - {{ $group['pending'] }} pending</span>
                            </div>
                            <b>Rp {{ number_format($group['amount'], 0, ',', '.') }}</b>
                        </a>
                    @empty
                        <div class="empty-state">Belum ada type HRIS.</div>
                    @endforelse
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Per Employee</h2>
                        <span>Record HRIS berdasarkan pegawai</span>
                    </div>
                </div>
                <div class="mini-list compact-list">
                    @forelse($hrisEmployeeGroups->take(8) as $group)
                        <a class="mini-item" href="{{ route('records.index', ['module' => $module, 'q' => $group['employee']]) }}">
                            <div>
                                <strong>{{ $group['employee'] }}</strong>
                                <span>{{ $group['count'] }} record - {{ $group['pending'] }} pending{{ $group['latest'] ? ' - terakhir '.$group['latest']->code : '' }}</span>
                            </div>
                            <b>Rp {{ number_format($group['amount'], 0, ',', '.') }}</b>
                        </a>
                    @empty
                        <div class="empty-state">Belum ada record yang tersambung employee.</div>
                    @endforelse
                </div>
            </section>
        </div>
    @endif

    <section class="panel">
        <div class="panel-header">
            <h2>Data {{ $config['label'] }}</h2>
            <span>{{ $records->total() }} record</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        @foreach($config['columns'] as $column)
                            <th>{{ $labels[$column] ?? $column }}</th>
                        @endforeach
                        <th>Relasi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($records as $record)
                        @php
                            $relatedLinks = $record->relationLoaded('displayRelatedLinks')
                                ? $record->getRelation('displayRelatedLinks')
                                : collect();
                            $financePaymentRecord = $record->relationLoaded('displayFinancePaymentRecord')
                                ? $record->getRelation('displayFinancePaymentRecord')
                                : null;
                            $canPayViaFinance = $record->module === 'finance'
                                && $privileges['pay']
                                && auth()->user()->hasPrivilege('payments.create')
                                && auth()->user()->hasPrivilege('modules.finance.pay')
                                && $canStartPayment($record);
                            $canOpenFinanceRecord = $record->module !== 'finance'
                                && $financePaymentRecord
                                && auth()->user()->hasPrivilege('modules.finance.view');
                        @endphp
                        <tr>
                            @foreach($config['columns'] as $column)
                                <td>
                                    @if($column === 'amount')
                                        Rp {{ number_format($record->{$column}, 0, ',', '.') }}
                                    @elseif($column === 'progress')
                                        {{ $record->{$column} }}%
                                    @elseif($column === 'status')
                                        <span class="status">{{ $record->status }}</span>
                                    @elseif($column === 'due_date')
                                        {{ optional($record->due_date)->format('d M Y') ?: '-' }}
                                    @else
                                        {{ $record->{$column} ?? '-' }}
                                    @endif
                                </td>
                            @endforeach
                            <td>
                                <div class="relation-list">
                                    @if(in_array($module, ['sales', 'manufacturing'], true) && $record->relationLoaded('productLines'))
                                        @foreach($record->productLines->whereIn('line_type', ['sales_item', 'production_output'])->take(2) as $productLine)
                                            @if($productLine->product)
                                                <a class="relation-chip" href="{{ route('products.index', ['q' => $productLine->product->sku]) }}" title="Produk terhubung">
                                                    <x-icon name="package" />
                                                    <span>{{ $productLine->product->sku }}</span>
                                                </a>
                                            @endif
                                        @endforeach
                                    @endif
                                    @if($relatedLinks->isEmpty())
                                        @if(! $record->relationLoaded('productLines') || $record->productLines->isEmpty())
                                            <span class="muted">-</span>
                                        @endif
                                    @else
                                        @foreach($relatedLinks->take(4) as $related)
                                            @php
                                                $linkedRecord = $related['record'];
                                                $relationLabel = \App\Services\ErpModuleIntegrationService::relationLabel($related['link']->relation_type);
                                            @endphp
                                            @if(auth()->user()->hasPrivilege("modules.{$linkedRecord->module}.view"))
                                                <a class="relation-chip" href="{{ route('records.index', ['module' => $linkedRecord->module, 'q' => $linkedRecord->code]) }}" title="{{ $relationLabel }}">
                                                    <x-icon :name="$related['direction'] === 'out' ? 'arrow-up-right' : 'arrow-down-left'" />
                                                    <span>{{ $linkedRecord->code }}</span>
                                                </a>
                                            @else
                                                <span class="relation-chip" title="{{ $relationLabel }}">
                                                    <x-icon name="lock" />
                                                    <span>{{ strtoupper($linkedRecord->module) }}</span>
                                                </span>
                                            @endif
                                        @endforeach
                                    @endif
                                    @if($relatedLinks->count() > 4)
                                        <span class="relation-chip">+{{ $relatedLinks->count() - 4 }}</span>
                                    @endif
                                </div>
                            </td>
                            <td>
                                <div class="row-actions">
                                    @if($canPayViaFinance)
                                        <a class="icon-link pay-text" href="{{ route('payments.create', $record) }}"><x-icon name="credit-card" />Bayar</a>
                                    @elseif($canOpenFinanceRecord)
                                        <a class="icon-link pay-text" href="{{ route('records.index', ['module' => 'finance', 'q' => $financePaymentRecord->code]) }}"><x-icon name="landmark" />Keuangan</a>
                                    @endif
                                    @if($module === 'sales' && auth()->user()->hasPrivilege('modules.sales.view'))
                                        <a class="icon-link" href="{{ route('sales.shipments.index', ['sales_id' => $record->id, 'q' => $record->code]) }}"><x-icon name="truck" />Kirim</a>
                                        <a class="icon-link" href="{{ route('sales.installments.index', ['sales_id' => $record->id, 'q' => $record->code]) }}"><x-icon name="calendar-clock" />Cicilan</a>
                                    @endif
                                    @if($privileges['edit'])
                                        <a class="icon-link" href="{{ route('records.edit', [$module, $record]) }}"><x-icon name="pencil" />Edit</a>
                                    @endif
                                    @if($privileges['advance'])
                                        <form method="post" action="{{ route('records.advance', [$module, $record]) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="icon-link" type="submit"><x-icon name="arrow-right" />Next</button>
                                        </form>
                                    @endif
                                    @if($privileges['delete'])
                                        <form method="post" action="{{ route('records.destroy', [$module, $record]) }}" onsubmit="return confirm('Hapus record {{ $record->code }}?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="icon-link danger-text" type="submit"><x-icon name="trash-2" />Hapus</button>
                                        </form>
                                    @endif
                                    @if(! $privileges['edit'] && ! $privileges['advance'] && ! $privileges['delete'] && ! $canPayViaFinance && ! $canOpenFinanceRecord)
                                        <span class="muted">Read only</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($config['columns']) + 2 }}" class="empty-state">Tidak ada data yang cocok.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $records->links() }}</div>
    </section>
@endsection
