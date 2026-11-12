@extends('layouts.app')

@section('title', 'Pengumuman & Berita - '.config('app.name'))

@section('actions')
    @if(auth()->user()->hasPrivilege('announcements.manage'))
        <a class="primary-button" href="{{ route('settings.announcements') }}"><x-icon name="megaphone" />Kelola Berita</a>
    @endif
@endsection

@section('content')
    <header class="page-header">
        <div>
            <p class="eyebrow">HR Communication</p>
            <h1>Pengumuman & Berita</h1>
            <p>Berita HR, pengumuman perusahaan, update payroll, benefit, kebijakan, dan informasi lintas sistem.</p>
        </div>
        <div class="role-card">
            <span>Belum dibaca</span>
            <strong>{{ $unreadCount }}</strong>
        </div>
    </header>

    <div class="metrics-grid">
        <article class="metric-card">
            <span>Publikasi aktif</span>
            <strong>{{ $totalPublished }}</strong>
            <small>Pengumuman dan berita yang masih berlaku</small>
        </article>
        <article class="metric-card">
            <span>Berita HR</span>
            <strong>{{ $newsCount }}</strong>
            <small>Konten berita yang diterbitkan HR</small>
        </article>
        <article class="metric-card">
            <span>Pin penting</span>
            <strong>{{ $pinnedCount }}</strong>
            <small>Muncul di urutan paling atas</small>
        </article>
        <article class="metric-card">
            <span>Status baca</span>
            <strong>{{ max($totalPublished - $unreadCount, 0) }}</strong>
            <small>Sudah dibuka oleh akun Anda</small>
        </article>
    </div>

    <section class="panel report-controls">
        <form class="report-filter-grid" method="get" action="{{ route('announcements.index') }}">
            <label>
                Jenis
                <select name="kind">
                    <option value="">Semua</option>
                    <option value="announcement" @selected(($filters['kind'] ?? '') === 'announcement')>Pengumuman</option>
                    <option value="news" @selected(($filters['kind'] ?? '') === 'news')>Berita</option>
                </select>
            </label>
            <label>
                Kategori
                <select name="category">
                    <option value="">Semua</option>
                    @foreach($categories as $category)
                        <option value="{{ $category }}" @selected(($filters['category'] ?? '') === $category)>{{ $category }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Status
                <select name="status">
                    <option value="">Semua</option>
                    <option value="unread" @selected(($filters['status'] ?? '') === 'unread')>Belum dibaca</option>
                </select>
            </label>
            <div class="report-filter-actions">
                <button class="primary-button" type="submit"><x-icon name="filter" />Filter</button>
                <a class="ghost-button" href="{{ route('announcements.index') }}"><x-icon name="rotate-ccw" />Reset</a>
            </div>
        </form>
    </section>

    <section class="announcement-grid">
        @forelse($announcements as $announcement)
            @php($read = $announcement->readFor(auth()->user()))
            <a class="announcement-card {{ $read ? '' : 'is-unread' }}" href="{{ route('announcements.show', $announcement) }}">
                <div class="announcement-card-head">
                    <span class="status {{ $announcement->kind === 'news' ? 'success' : '' }}">{{ $announcement->kind === 'news' ? 'BERITA' : 'PENGUMUMAN' }}</span>
                    @if($announcement->is_pinned)
                        <span class="status warning">PIN</span>
                    @endif
                </div>
                <strong>{{ $announcement->title }}</strong>
                <p>{{ $announcement->summary ?: \Illuminate\Support\Str::limit($announcement->body, 180) }}</p>
                <div class="announcement-meta">
                    <span>{{ $announcement->source_department }}</span>
                    <span>{{ $announcement->category }}</span>
                    <span>{{ strtoupper($announcement->priority) }}</span>
                    <span>{{ $announcement->publish_at?->format('d M Y') ?: $announcement->created_at->format('d M Y') }}</span>
                </div>
            </a>
        @empty
            <div class="panel empty-state">Belum ada pengumuman atau berita.</div>
        @endforelse
    </section>

    <div class="pagination-wrap">
        {{ $announcements->links() }}
    </div>
@endsection
