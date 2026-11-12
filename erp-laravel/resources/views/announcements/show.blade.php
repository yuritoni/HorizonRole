@extends('layouts.app')

@section('title', $announcement->title.' - '.config('app.name'))

@section('actions')
    <a class="ghost-button" href="{{ route('announcements.index') }}"><x-icon name="arrow-left" />Kembali</a>
    @if(auth()->user()->hasPrivilege('announcements.manage'))
        <a class="primary-button" href="{{ route('settings.announcements') }}"><x-icon name="settings" />Kelola</a>
    @endif
@endsection

@section('content')
    <header class="page-header">
        <div>
            <p class="eyebrow">{{ $announcement->kind === 'news' ? 'Berita HR' : 'Pengumuman' }}</p>
            <h1>{{ $announcement->title }}</h1>
            <p>{{ $announcement->summary }}</p>
        </div>
        <div class="role-card">
            <span>Prioritas</span>
            <strong>{{ strtoupper($announcement->priority) }}</strong>
        </div>
    </header>

    <section class="panel announcement-detail">
        <div class="announcement-detail-meta">
            <span class="status {{ $announcement->kind === 'news' ? 'success' : '' }}">{{ $announcement->kind === 'news' ? 'BERITA' : 'PENGUMUMAN' }}</span>
            <span class="status">{{ $announcement->category }}</span>
            <span class="status {{ $announcement->priority === 'urgent' ? 'warning' : '' }}">{{ strtoupper($announcement->priority) }}</span>
            <span>{{ $announcement->source_department }}</span>
            <span>{{ $announcement->audienceLabel() }}</span>
            <span>{{ $announcement->publish_at?->format('d M Y H:i') ?: $announcement->created_at->format('d M Y H:i') }}</span>
        </div>

        <article class="announcement-body">
            {!! nl2br(e($announcement->body)) !!}
        </article>

        <div class="announcement-read-box">
            <div>
                <strong>Status Anda</strong>
                <span>Dibaca: {{ $read?->read_at?->format('d M Y H:i') ?: '-' }}. Konfirmasi: {{ $read?->acknowledged_at?->format('d M Y H:i') ?: '-' }}</span>
            </div>
            @if($announcement->requires_acknowledgement && ! $read?->acknowledged_at)
                <form method="post" action="{{ route('announcements.acknowledge', $announcement) }}">
                    @csrf
                    <button class="primary-button" type="submit"><x-icon name="check-check" />Saya Sudah Membaca</button>
                </form>
            @endif
        </div>
    </section>

    @if(auth()->user()->hasPrivilege('announcements.manage'))
        <section class="panel report-table-panel">
            <div class="panel-header">
                <h2>Monitoring Baca</h2>
                <span>{{ $announcement->reads->count() }} user</span>
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Dibaca</th>
                            <th>Konfirmasi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($announcement->reads as $readRow)
                            <tr>
                                <td>{{ $readRow->user?->name }}</td>
                                <td>{{ $readRow->read_at?->format('d M Y H:i') ?: '-' }}</td>
                                <td>{{ $readRow->acknowledged_at?->format('d M Y H:i') ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="empty-state">Belum ada yang membuka.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
@endsection
