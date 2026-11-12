@extends('layouts.app')

@php($dashboardModuleIcons = [
    'sales' => 'shopping-cart',
    'procurement' => 'package-check',
    'inventory' => 'boxes',
    'finance' => 'wallet',
    'accounting' => 'calculator',
    'crm' => 'handshake',
    'marketing' => 'radio-tower',
    'manufacturing' => 'factory',
    'projects' => 'clipboard-list',
    'hr' => 'users',
    'hris' => 'id-card',
    'assets' => 'warehouse',
])

@section('title', 'Dashboard - '.config('app.name'))

@section('actions')
    @if(auth()->user()->canManageRecords())
        <a class="primary-button" href="{{ route('records.create', 'sales') }}"><x-icon name="plus" />Order Baru</a>
    @endif
@endsection

@section('content')
    <header class="page-header">
        <div>
            <p class="eyebrow">Executive Cockpit</p>
            <h1>Dashboard Operasional</h1>
            <p>Ringkasan lintas departemen untuk revenue, piutang, stok, approval, payroll, dan aktivitas ERP.</p>
        </div>
        <div class="role-card">
            <span>Role aktif</span>
            <strong>{{ strtoupper(auth()->user()->role) }}</strong>
        </div>
    </header>

    <div class="metrics-grid">
        @foreach($metrics as $metric)
            <article class="metric-card">
                <span>{{ $metric['label'] }}</span>
                <strong>Rp {{ number_format($metric['value'], 0, ',', '.') }}</strong>
                <small>{{ $metric['note'] }}</small>
            </article>
        @endforeach
    </div>

    <div class="dashboard-grid">
        <section class="panel">
            <div class="panel-header">
                <h2>Modul ERP</h2>
                <span>{{ $moduleCards->count() }} proses aktif</span>
            </div>
            <div class="module-cards">
                @foreach($moduleCards as $card)
                    <a class="module-card" href="{{ route('records.index', $card['module']) }}">
                        <div class="module-card-title">
                            <x-icon :name="$dashboardModuleIcons[$card['module']] ?? 'boxes'" />
                            <div>
                                <strong>{{ $card['label'] }}</strong>
                                <span>{{ $card['group'] }}</span>
                            </div>
                        </div>
                        <b>{{ $card['count'] }}</b>
                    </a>
                @endforeach
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2>Approval Pembelian</h2>
                <span>{{ $pendingApprovals->count() }} pending</span>
            </div>
            <div class="mini-list">
                @forelse($pendingApprovals as $approval)
                    <div class="mini-item">
                        <div>
                            <strong>{{ $approval->code }} - {{ $approval->title }}</strong>
                            <span>Rp {{ number_format($approval->amount, 0, ',', '.') }} oleh {{ $approval->owner ?: '-' }}</span>
                        </div>
                        <span class="status warning">{{ $approval->status }}</span>
                    </div>
                @empty
                    <div class="empty-state">Tidak ada approval pending.</div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
