@extends('layouts.app')

@section('title', 'Kelola Pengumuman & Berita - '.config('app.name'))

@section('actions')
    <a class="ghost-button" href="{{ route('announcements.index') }}"><x-icon name="newspaper" />Lihat Portal</a>
@endsection

@section('content')
    <header class="page-header">
        <div>
            <p class="eyebrow">HR Publishing</p>
            <h1>Kelola Pengumuman & Berita</h1>
            <p>HR menerbitkan berita dan pengumuman untuk seluruh pegawai, departemen tertentu, atau seluruh user sistem.</p>
        </div>
        <div class="role-card">
            <span>Published</span>
            <strong>{{ $publishedCount }}</strong>
        </div>
    </header>

    <div class="metrics-grid">
        <article class="metric-card">
            <span>Published</span>
            <strong>{{ $publishedCount }}</strong>
            <small>Sudah tampil di portal pegawai</small>
        </article>
        <article class="metric-card">
            <span>Draft</span>
            <strong>{{ $draftCount }}</strong>
            <small>Belum dipublikasikan</small>
        </article>
        <article class="metric-card">
            <span>Berita HR</span>
            <strong>{{ $newsCount }}</strong>
            <small>Konten berita dari HR</small>
        </article>
        <article class="metric-card">
            <span>Butuh konfirmasi</span>
            <strong>{{ $ackRequiredCount }}</strong>
            <small>Harus di-acknowledge pegawai</small>
        </article>
    </div>

    <section class="panel">
        <div class="panel-header">
            <h2>Buat Publikasi</h2>
            <span>HR sebagai sumber berita</span>
        </div>
        <div class="form-panel">
            <form class="record-form" method="post" action="{{ route('settings.announcements.store') }}">
                @csrf
                <div class="form-grid">
                    <label>
                        Jenis
                        <select name="kind" required>
                            <option value="news" @selected(old('kind', 'news') === 'news')>Berita</option>
                            <option value="announcement" @selected(old('kind') === 'announcement')>Pengumuman</option>
                        </select>
                    </label>
                    <label>
                        Kategori
                        <select name="category" required>
                            @foreach($categories as $category)
                                <option value="{{ $category }}" @selected(old('category', 'HR') === $category)>{{ $category }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Judul
                        <input name="title" value="{{ old('title') }}" required>
                    </label>
                    <label>
                        Sumber
                        <select name="source_department" required>
                            <option value="HR">HR</option>
                            @foreach($departments as $department)
                                <option value="{{ $department }}" @selected(old('source_department') === $department)>{{ $department }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Audience
                        <select name="audience" required>
                            <option value="all" @selected(old('audience', 'all') === 'all')>Semua Pegawai</option>
                            <option value="all_systems" @selected(old('audience') === 'all_systems')>Semua User Sistem</option>
                            <option value="employee" @selected(old('audience') === 'employee')>Role Employee</option>
                            @foreach($departments as $department)
                                <option value="{{ $department }}" @selected(old('audience') === $department)>{{ $department }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Prioritas
                        <select name="priority" required>
                            @foreach($priorities as $priority)
                                <option value="{{ $priority }}" @selected(old('priority', 'normal') === $priority)>{{ strtoupper($priority) }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Terintegrasi modul
                        <select name="scope_module" required>
                            @foreach($modules as $module => $label)
                                <option value="{{ $module }}" @selected(old('scope_module', 'all') === $module)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label>
                        Publish
                        <input type="datetime-local" name="publish_at" value="{{ old('publish_at') }}">
                    </label>
                    <label class="full">
                        Ringkasan
                        <input name="summary" value="{{ old('summary') }}" maxlength="220">
                    </label>
                    <label class="full">
                        Isi berita / pengumuman
                        <textarea name="body" required>{{ old('body') }}</textarea>
                    </label>
                    <label class="checkbox-row"><input type="checkbox" name="is_published" value="1" checked> Published</label>
                    <label class="checkbox-row"><input type="checkbox" name="is_pinned" value="1"> Pin</label>
                    <label class="checkbox-row"><input type="checkbox" name="requires_acknowledgement" value="1"> Wajib konfirmasi baca</label>
                </div>
                <div class="form-actions">
                    <button class="primary-button" type="submit"><x-icon name="send" />Publikasikan</button>
                </div>
            </form>
        </div>
    </section>

    <section class="panel report-table-panel">
        <div class="panel-header">
            <h2>Daftar Publikasi</h2>
            <span>{{ $announcements->total() }} data</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Judul</th>
                        <th>Jenis</th>
                        <th>Audience</th>
                        <th>Prioritas</th>
                        <th>Status</th>
                        <th>Dibaca</th>
                        <th>Konfirmasi</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($announcements as $announcement)
                        <tr>
                            <td>
                                <strong>{{ $announcement->title }}</strong><br>
                                <span class="muted">{{ $announcement->source_department }} - {{ $announcement->category }}</span>
                            </td>
                            <td><span class="status {{ $announcement->kind === 'news' ? 'success' : '' }}">{{ strtoupper($announcement->kind) }}</span></td>
                            <td>{{ $announcement->audienceLabel() }}</td>
                            <td><span class="status {{ $announcement->priority === 'urgent' ? 'warning' : '' }}">{{ strtoupper($announcement->priority) }}</span></td>
                            <td><span class="status {{ $announcement->is_published ? 'success' : 'warning' }}">{{ $announcement->is_published ? 'LIVE' : 'DRAFT' }}</span></td>
                            <td>{{ $announcement->read_count }}</td>
                            <td>{{ $announcement->acknowledged_count }}</td>
                            <td>
                                <a class="ghost-button" href="{{ route('announcements.show', $announcement) }}"><x-icon name="eye" />Detail</a>
                                <form class="inline-form" method="post" action="{{ route('settings.announcements.status', $announcement) }}">
                                    @csrf
                                    @method('PATCH')
                                    @if(! $announcement->is_published)
                                        <input type="hidden" name="is_published" value="1">
                                        <input type="hidden" name="notify_now" value="1">
                                        <button class="ghost-button" type="submit"><x-icon name="cloud-upload" />Publish</button>
                                    @else
                                        <input type="hidden" name="is_published" value="0">
                                        <button class="ghost-button" type="submit"><x-icon name="file-text" />Jadikan Draft</button>
                                    @endif
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="empty-state">Belum ada publikasi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">
            {{ $announcements->links() }}
        </div>
    </section>
@endsection
