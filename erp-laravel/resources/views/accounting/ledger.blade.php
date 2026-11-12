@extends('layouts.app')

@php
    $money = fn ($value) => 'Rp '.number_format((float) $value, 0, ',', '.');
    $statusLabel = fn ($status) => \Illuminate\Support\Str::headline((string) $status);
    $statusTone = fn ($status) => match ($status) {
        'posted' => 'success',
        'reviewed', 'draft' => 'warning',
        'reversed' => 'danger',
        default => '',
    };
    $visibleTrialBalance = $trialBalance->filter(fn ($row) => $row['debit'] > 0 || $row['credit'] > 0 || $row['account']->is_active);
    $sectionQuery = request()->only('period_id', 'status');
@endphp

@section('title', 'Accounting Ledger - '.config('app.name'))

@section('actions')
    <a class="ghost-button" href="{{ route('records.index', 'accounting') }}"><x-icon name="archive" />Arsip Record</a>
@endsection

@section('content')
    <header class="page-header">
        <div>
            <p class="eyebrow">General Ledger</p>
            <h1>Advanced Accounting</h1>
            <p>Accounting dipisah per area kerja agar COA, jurnal, trial balance, periode, dan sinkronisasi tidak campur dalam satu layar.</p>
        </div>
        <div class="role-card">
            <span>Variance Trial Balance</span>
            <strong>{{ $money($summary['variance']) }}</strong>
        </div>
    </header>

    <div class="metrics-grid">
        <article class="metric-card">
            <span>Chart of Account</span>
            <strong>{{ number_format($summary['account_count'], 0, ',', '.') }}</strong>
            <small>Akun aktif dan struktur buku besar.</small>
        </article>
        <article class="metric-card">
            <span>Posted Journal</span>
            <strong>{{ number_format($summary['posted_count'], 0, ',', '.') }}</strong>
            <small>Jurnal yang sudah masuk ledger.</small>
        </article>
        <article class="metric-card">
            <span>Draft / Reviewed</span>
            <strong>{{ number_format($summary['draft_count'], 0, ',', '.') }}</strong>
            <small>Jurnal menunggu posting.</small>
        </article>
        <article class="metric-card">
            <span>Periode</span>
            <strong>{{ $selectedPeriod?->period_code ?? '-' }}</strong>
            <small>Status {{ $statusLabel($summary['period_status']) }}.</small>
        </article>
    </div>

    <nav class="accounting-section-tabs" aria-label="Accounting workspace">
        @foreach($accountingSections as $key => $section)
            <a class="{{ $activeSection === $key ? 'is-active' : '' }}" href="{{ route('accounting.ledger', array_merge($sectionQuery, ['section' => $key])) }}">
                <x-icon :name="$section['icon']" />
                <span>{{ $section['label'] }}</span>
            </a>
        @endforeach
    </nav>

    @if(in_array($activeSection, ['trial', 'journal'], true))
        <form class="filter-row accounting-filter" method="get" action="{{ route('accounting.ledger') }}">
            <input type="hidden" name="section" value="{{ $activeSection }}">
            <select name="period_id">
                <option value="">Semua / periode open</option>
                @foreach($periods as $period)
                    <option value="{{ $period->id }}" @selected(optional($selectedPeriod)->id === $period->id)>
                        {{ $period->period_code }} - {{ $period->name }} ({{ $statusLabel($period->status) }})
                    </option>
                @endforeach
            </select>
            <select name="status">
                @foreach($journalStatuses as $status)
                    <option value="{{ $status }}" @selected($activeStatus === $status)>{{ $status === 'all' ? 'Semua status jurnal' : $statusLabel($status) }}</option>
                @endforeach
            </select>
            <button class="ghost-button" type="submit"><x-icon name="filter" />Filter</button>
            <a class="ghost-button" href="{{ route('accounting.ledger', ['section' => $activeSection]) }}"><x-icon name="rotate-ccw" />Reset</a>
        </form>
    @endif

    @if($activeSection === 'overview')
        <div class="accounting-overview-grid">
            @foreach($accountingSections as $key => $section)
                @continue($key === 'overview')
                <a class="master-link-card accounting-section-card" href="{{ route('accounting.ledger', ['section' => $key]) }}">
                    <div class="master-link-title">
                        <x-icon :name="$section['icon']" />
                        <strong>{{ $section['label'] }}</strong>
                    </div>
                    <span>
                        @if($key === 'trial')
                            Lihat posisi debit, kredit, dan saldo normal per akun.
                        @elseif($key === 'journal')
                            Input, review, dan posting jurnal double-entry.
                        @elseif($key === 'coa')
                            Kelola akun, type akun, normal balance, dan parent account.
                        @elseif($key === 'period')
                            Kelola periode open, locked, dan closed.
                        @else
                            Tarik transaksi ERP menjadi jurnal detail accounting.
                        @endif
                    </span>
                </a>
            @endforeach
        </div>

        <div class="accounting-grid">
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Ringkasan Balance</h2>
                        <span>{{ $selectedPeriod?->name ?? 'Semua periode' }}</span>
                    </div>
                    <span class="status {{ abs($summary['variance']) <= 0.01 ? 'success' : 'danger' }}">
                        {{ abs($summary['variance']) <= 0.01 ? 'Balance' : 'Selisih' }}
                    </span>
                </div>
                <div class="accounting-balance-strip">
                    <div>
                        <span>Total Debit</span>
                        <strong>{{ $money($summary['total_debit']) }}</strong>
                    </div>
                    <div>
                        <span>Total Kredit</span>
                        <strong>{{ $money($summary['total_credit']) }}</strong>
                    </div>
                    <div>
                        <span>Selisih</span>
                        <strong>{{ $money($summary['variance']) }}</strong>
                    </div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Jurnal Terbaru</h2>
                        <span>Ringkasan aktivitas ledger</span>
                    </div>
                </div>
                <div class="mini-list">
                    @forelse($entries->take(5) as $entry)
                        <a class="mini-item" href="{{ route('accounting.ledger', ['section' => 'journal', 'period_id' => $entry->period_id]) }}">
                            <div>
                                <strong>{{ $entry->journal_no }}</strong>
                                <span>{{ optional($entry->entry_date)->format('d M Y') }} - {{ $entry->description }}</span>
                            </div>
                            <b>{{ $money($entry->total_debit) }}</b>
                        </a>
                    @empty
                        <div class="empty-state">Belum ada jurnal.</div>
                    @endforelse
                </div>
            </section>
        </div>
    @endif

    @if($activeSection === 'trial')
        <section class="panel accounting-workspace-panel">
            <div class="panel-header">
                <div>
                    <h2>Trial Balance</h2>
                    <span>{{ $selectedPeriod?->name ?? 'Semua periode' }}</span>
                </div>
                <span class="status {{ abs($summary['variance']) <= 0.01 ? 'success' : 'danger' }}">
                    {{ abs($summary['variance']) <= 0.01 ? 'Balance' : 'Selisih' }}
                </span>
            </div>
            <div class="accounting-balance-strip">
                <div>
                    <span>Total Debit</span>
                    <strong>{{ $money($summary['total_debit']) }}</strong>
                </div>
                <div>
                    <span>Total Kredit</span>
                    <strong>{{ $money($summary['total_credit']) }}</strong>
                </div>
                <div>
                    <span>Selisih</span>
                    <strong>{{ $money($summary['variance']) }}</strong>
                </div>
            </div>
            <div class="table-wrap accounting-table">
                <table>
                    <thead>
                        <tr>
                            <th>Akun</th>
                            <th>Type</th>
                            <th>Debit</th>
                            <th>Kredit</th>
                            <th>Saldo Normal</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($visibleTrialBalance as $row)
                            <tr>
                                <td>
                                    <strong>{{ $row['account']->code }}</strong>
                                    <span>{{ $row['account']->name }}</span>
                                </td>
                                <td>{{ $statusLabel($row['account']->type) }}</td>
                                <td>{{ $money($row['debit']) }}</td>
                                <td>{{ $money($row['credit']) }}</td>
                                <td class="{{ $row['display_balance'] < 0 ? 'danger-text' : '' }}">{{ $money($row['display_balance']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="empty-state">Belum ada akun atau saldo.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    @if($activeSection === 'journal')
        <div class="accounting-journal-stack">
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Input Jurnal</h2>
                        <span>Minimal dua baris dan harus balance</span>
                    </div>
                </div>
                <form class="record-form accounting-journal-form" method="post" action="{{ route('accounting.journals.store') }}">
                    @csrf
                    <div class="form-grid compact">
                        <label>
                            <span>Tanggal Jurnal</span>
                            <input type="date" name="entry_date" value="{{ old('entry_date', now()->toDateString()) }}" required>
                        </label>
                        <label>
                            <span>Periode</span>
                            <select name="period_id">
                                <option value="">Otomatis dari tanggal</option>
                                @foreach($periods->where('status', 'open') as $period)
                                    <option value="{{ $period->id }}" @selected((string) old('period_id', optional($selectedPeriod)->id) === (string) $period->id)>
                                        {{ $period->period_code }} - {{ $period->name }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span>Referensi</span>
                            <input type="text" name="reference_no" value="{{ old('reference_no') }}" placeholder="Invoice / payroll / payment no">
                        </label>
                        <label>
                            <span>Keterangan</span>
                            <input type="text" name="description" value="{{ old('description') }}" placeholder="Deskripsi jurnal" required>
                        </label>
                    </div>

                    <div class="accounting-lines">
                        <div class="accounting-journal-line-grid accounting-journal-line-head">
                            <span>Akun</span>
                            <span>Debit</span>
                            <span>Kredit</span>
                            <span>Partner</span>
                            <span>Cost Center</span>
                            <span>Memo</span>
                        </div>
                        @for($index = 0; $index < 6; $index++)
                            <div class="accounting-journal-line-grid">
                                <select name="lines[{{ $index }}][account_id]">
                                    <option value="">Pilih akun</option>
                                    @foreach($accounts->where('is_active', true) as $account)
                                        <option value="{{ $account->id }}" @selected((string) old("lines.$index.account_id") === (string) $account->id)>
                                            {{ $account->code }} - {{ $account->name }}
                                        </option>
                                    @endforeach
                                </select>
                                <input type="number" min="0" step="0.01" name="lines[{{ $index }}][debit]" value="{{ old("lines.$index.debit") }}" placeholder="0">
                                <input type="number" min="0" step="0.01" name="lines[{{ $index }}][credit]" value="{{ old("lines.$index.credit") }}" placeholder="0">
                                <input type="text" name="lines[{{ $index }}][partner]" value="{{ old("lines.$index.partner") }}" placeholder="Partner">
                                <input type="text" name="lines[{{ $index }}][cost_center]" value="{{ old("lines.$index.cost_center") }}" placeholder="Dept">
                                <input type="text" name="lines[{{ $index }}][description]" value="{{ old("lines.$index.description") }}" placeholder="Memo baris">
                            </div>
                        @endfor
                        @if(isset($errors) && $errors->has('lines'))
                            <small class="field-error">{{ $errors->first('lines') }}</small>
                        @endif
                    </div>

                    <div class="form-actions">
                        <button class="primary-button" type="submit"><x-icon name="save" />Simpan Jurnal</button>
                    </div>
                </form>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Daftar Jurnal</h2>
                        <span>{{ $entries->total() }} jurnal sesuai filter</span>
                    </div>
                </div>
                <div class="accounting-entry-list">
                    @forelse($entries as $entry)
                        <article class="accounting-entry">
                            <div class="accounting-entry-head">
                                <div>
                                    <div class="accounting-entry-title">
                                        <x-icon name="book-open-check" />
                                        <strong>{{ $entry->journal_no }}</strong>
                                        <span class="status {{ $statusTone($entry->status) }}">{{ $statusLabel($entry->status) }}</span>
                                    </div>
                                    <span>{{ optional($entry->entry_date)->format('d M Y') }} - {{ $entry->description }}</span>
                                    <small>
                                        Ref: {{ $entry->reference_no ?: '-' }}
                                        @if($entry->source instanceof \App\Models\ErpRecord)
                                            - Sumber:
                                            <a class="pay-text" href="{{ route('records.index', ['module' => $entry->source->module, 'q' => $entry->source->code]) }}">{{ $entry->source->code }}</a>
                                        @elseif($entry->source instanceof \App\Models\Payment)
                                            - Payment: {{ $entry->source->order_id }}
                                        @endif
                                    </small>
                                </div>
                                <div class="accounting-entry-total">
                                    <span>Debit / Kredit</span>
                                    <strong>{{ $money($entry->total_debit) }}</strong>
                                    @if($entry->status !== 'posted' && $entry->isBalanced() && $entry->period?->isOpen() && auth()->user()->hasPrivilege('modules.accounting.advance'))
                                        <form method="post" action="{{ route('accounting.journals.post', $entry) }}">
                                            @csrf
                                            @method('PATCH')
                                            <button class="ghost-button" type="submit"><x-icon name="check-check" />Post</button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                            <div class="accounting-line-list">
                                @foreach($entry->lines as $line)
                                    <div>
                                        <span>{{ $line->account?->code }} - {{ $line->account?->name }}</span>
                                        <span>{{ $line->partner ?: '-' }}</span>
                                        <strong>{{ $line->debit > 0 ? 'D '.$money($line->debit) : 'K '.$money($line->credit) }}</strong>
                                    </div>
                                @endforeach
                            </div>
                        </article>
                    @empty
                        <div class="empty-state">Belum ada jurnal accounting untuk filter ini.</div>
                    @endforelse
                </div>
                <div class="pagination-wrap">{{ $entries->links() }}</div>
            </section>
        </div>
    @endif

    @if($activeSection === 'coa')
        <div class="accounting-management-grid">
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Tambah Chart of Account</h2>
                        <span>Akun, type, normal balance, dan parent</span>
                    </div>
                </div>
                <form class="form-grid compact accounting-side-form" method="post" action="{{ route('accounting.accounts.store') }}">
                    @csrf
                    <label>
                        <span>Kode</span>
                        <input type="text" name="code" value="{{ old('code') }}" placeholder="1201" required>
                    </label>
                    <label>
                        <span>Nama Akun</span>
                        <input type="text" name="name" value="{{ old('name') }}" placeholder="Bank BCA" required>
                    </label>
                    <label>
                        <span>Type</span>
                        <select name="type" required>
                            @foreach($accountTypes as $type)
                                <option value="{{ $type }}" @selected(old('type') === $type)>{{ $statusLabel($type) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        <span>Normal</span>
                        <select name="normal_balance" required>
                            <option value="debit" @selected(old('normal_balance') === 'debit')>Debit</option>
                            <option value="credit" @selected(old('normal_balance') === 'credit')>Credit</option>
                        </select>
                    </label>
                    <label class="full">
                        <span>Parent</span>
                        <select name="parent_id">
                            <option value="">Tanpa parent</option>
                            @foreach($accounts as $account)
                                <option value="{{ $account->id }}" @selected((string) old('parent_id') === (string) $account->id)>{{ $account->code }} - {{ $account->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="full">
                        <span>Deskripsi</span>
                        <input type="text" name="description" value="{{ old('description') }}" placeholder="Keterangan akun">
                    </label>
                    <input type="hidden" name="is_active" value="0">
                    <label class="checkbox-row"><input type="checkbox" name="is_active" value="1" checked> Aktif</label>
                    <label class="checkbox-row"><input type="checkbox" name="is_cash" value="1"> Kas / Bank</label>
                    <div class="form-actions full">
                        <button class="primary-button" type="submit"><x-icon name="plus" />Tambah Akun</button>
                    </div>
                </form>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Daftar Chart of Account</h2>
                        <span>{{ number_format($accounts->count(), 0, ',', '.') }} akun tersedia</span>
                    </div>
                </div>
                <div class="account-list account-list-separated">
                    @foreach($accounts as $account)
                        <div>
                            <strong>{{ $account->code }} - {{ $account->name }}</strong>
                            <span>{{ $statusLabel($account->type) }} / {{ $statusLabel($account->normal_balance) }}{{ $account->is_cash ? ' / Cash' : '' }}{{ $account->parent ? ' / Parent '.$account->parent->code : '' }}</span>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>
    @endif

    @if($activeSection === 'period')
        <div class="accounting-management-grid">
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Buat Periode Akuntansi</h2>
                        <span>Periode open dipakai untuk posting jurnal</span>
                    </div>
                </div>
                <form class="form-grid compact accounting-side-form" method="post" action="{{ route('accounting.periods.store') }}">
                    @csrf
                    <label>
                        <span>Kode</span>
                        <input type="text" name="period_code" value="{{ old('period_code', now()->format('Y-m')) }}" required>
                    </label>
                    <label>
                        <span>Nama</span>
                        <input type="text" name="name" value="{{ old('name', now()->translatedFormat('F Y')) }}" required>
                    </label>
                    <label>
                        <span>Mulai</span>
                        <input type="date" name="start_date" value="{{ old('start_date', now()->startOfMonth()->toDateString()) }}" required>
                    </label>
                    <label>
                        <span>Selesai</span>
                        <input type="date" name="end_date" value="{{ old('end_date', now()->endOfMonth()->toDateString()) }}" required>
                    </label>
                    <label class="full">
                        <span>Status</span>
                        <select name="status">
                            <option value="open">Open</option>
                            <option value="locked">Locked</option>
                            <option value="closed">Closed</option>
                        </select>
                    </label>
                    <div class="form-actions full">
                        <button class="primary-button" type="submit"><x-icon name="calendar-plus" />Buat Periode</button>
                    </div>
                </form>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Daftar Periode</h2>
                        <span>{{ number_format($periods->count(), 0, ',', '.') }} periode accounting</span>
                    </div>
                </div>
                <div class="account-list account-list-separated">
                    @forelse($periods as $period)
                        <div>
                            <strong>{{ $period->period_code }} - {{ $period->name }}</strong>
                            <span>{{ optional($period->start_date)->format('d M Y') }} - {{ optional($period->end_date)->format('d M Y') }} / {{ $statusLabel($period->status) }}</span>
                        </div>
                    @empty
                        <div class="empty-state">Belum ada periode.</div>
                    @endforelse
                </div>
            </section>
        </div>
    @endif

    @if($activeSection === 'sync')
        <section class="panel accounting-workspace-panel">
            <div class="panel-header">
                <div>
                    <h2>Sinkronisasi ERP ke Accounting</h2>
                    <span>Finance, payment, sales, procurement, HRIS, payroll, reimbursement, training, dinas, dan produksi.</span>
                </div>
            </div>
            <div class="accounting-sync-grid">
                <div class="mini-item">
                    <div class="module-card-title">
                        <x-icon name="receipt" />
                        <div>
                            <strong>Finance & Payment</strong>
                            <span>AR/AP menjadi jurnal piutang/utang, payment paid menjadi jurnal kas.</span>
                        </div>
                    </div>
                </div>
                <div class="mini-item">
                    <div class="module-card-title">
                        <x-icon name="users" />
                        <div>
                            <strong>HRIS & Payroll</strong>
                            <span>Payroll, reimburse, training, dan dinas masuk ke expense dan payable.</span>
                        </div>
                    </div>
                </div>
                <div class="mini-item">
                    <div class="module-card-title">
                        <x-icon name="package-check" />
                        <div>
                            <strong>Sales, Procurement, Produksi</strong>
                            <span>Revenue, PPN, inventory, asset, COGS/clearing tersambung ke ledger.</span>
                        </div>
                    </div>
                </div>
            </div>
            @if(auth()->user()->hasPrivilege('modules.accounting.advance'))
                <form class="accounting-sync-action" method="post" action="{{ route('accounting.sync-erp') }}">
                    @csrf
                    <button class="primary-button" type="submit"><x-icon name="refresh-cw" />Sync Jurnal Detail</button>
                </form>
            @endif
        </section>
    @endif
@endsection
