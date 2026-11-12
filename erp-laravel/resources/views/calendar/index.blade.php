@extends('layouts.app')

@section('title', 'Kalender ERP - '.config('app.name'))

@section('actions')
    @if(auth()->user()->hasPrivilege('calendar.manage'))
        <a class="primary-button" href="#calendar-event-form"><x-icon name="plus" />Tambah Agenda</a>
    @endif
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
        $weekdayLabels = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
        $manualTypes = [
            'event' => ['label' => 'Event', 'icon' => 'calendar-days'],
            'meeting' => ['label' => 'Meeting', 'icon' => 'video'],
            'deadline' => ['label' => 'Deadline', 'icon' => 'alarm-clock'],
            'holiday' => ['label' => 'Libur', 'icon' => 'calendar-check'],
            'finance' => ['label' => 'Finance', 'icon' => 'wallet'],
            'hr' => ['label' => 'HR', 'icon' => 'users'],
            'sales' => ['label' => 'Sales', 'icon' => 'shopping-cart'],
            'production' => ['label' => 'Produksi', 'icon' => 'factory'],
            'reminder' => ['label' => 'Reminder', 'icon' => 'bell-ring'],
        ];
    @endphp

    <header class="page-header calendar-hero">
        <div>
            <p class="eyebrow">Kalender Terintegrasi</p>
            <h1>{{ $monthNames[$month] }} {{ $year }}</h1>
            <p>Agenda ERP, pengumuman, berita HR, cuti, izin, sakit, lembur, perjalanan dinas, pelatihan, dan ulang tahun pegawai dalam satu kalender.</p>
        </div>
        <div class="role-card">
            <span>Total agenda</span>
            <strong>{{ $summary['total'] }}</strong>
        </div>
    </header>

    <div class="metrics-grid">
        <article class="metric-card calendar-metric">
            <span>Agenda Manual</span>
            <strong>{{ $summary['manual'] }}</strong>
            <small>Event dibuat langsung dari kalender</small>
        </article>
        <article class="metric-card calendar-metric">
            <span>Pengumuman & Berita</span>
            <strong>{{ $summary['announcements'] }}</strong>
            <small>Publikasi HR dan komunikasi perusahaan</small>
        </article>
        <article class="metric-card calendar-metric">
            <span>Pengajuan HRIS</span>
            <strong>{{ $summary['requests'] }}</strong>
            <small>Cuti, izin, presensi manual, lembur, training, dinas</small>
        </article>
        <article class="metric-card calendar-metric">
            <span>Ulang Tahun</span>
            <strong>{{ $summary['birthdays'] }}</strong>
            <small>Berulang otomatis dari data employee</small>
        </article>
    </div>

    <section class="panel report-controls calendar-controls">
        <form class="report-filter-grid" method="get" action="{{ route('calendar.index') }}">
            <label>
                Bulan
                <select name="month">
                    @foreach($monthNames as $monthNumber => $monthLabel)
                        <option value="{{ $monthNumber }}" @selected((int) $month === (int) $monthNumber)>{{ $monthLabel }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Tahun
                <select name="year">
                    @foreach($years as $yearOption)
                        <option value="{{ $yearOption }}" @selected((int) $year === (int) $yearOption)>{{ $yearOption }}</option>
                    @endforeach
                </select>
            </label>
            <label>
                Tipe
                <select name="type">
                    <option value="">Semua agenda</option>
                    @foreach($typeOptions as $typeKey => $typeMeta)
                        <option value="{{ $typeKey }}" @selected($activeType === $typeKey)>{{ $typeMeta['label'] }} ({{ $typeMeta['count'] }})</option>
                    @endforeach
                </select>
            </label>
            <div class="report-filter-actions">
                <button class="primary-button" type="submit"><x-icon name="filter" />Filter</button>
                <a class="ghost-button" href="{{ route('calendar.index') }}"><x-icon name="rotate-ccw" />Reset</a>
            </div>
        </form>
    </section>

    <section class="calendar-shell">
        <div class="panel calendar-board">
            <div class="calendar-toolbar">
                <a class="ghost-button" href="{{ route('calendar.index', ['month' => $prevMonth->month, 'year' => $prevMonth->year, 'type' => $activeType]) }}"><x-icon name="chevron-left" />Bulan Sebelumnya</a>
                <div>
                    <strong>{{ $periodStart->format('d M Y') }} - {{ $periodEnd->format('d M Y') }}</strong>
                    <span>{{ $agendaEvents->count() }} agenda tampil</span>
                </div>
                <a class="ghost-button" href="{{ route('calendar.index', ['month' => $nextMonth->month, 'year' => $nextMonth->year, 'type' => $activeType]) }}">Bulan Berikutnya<x-icon name="chevron-right" /></a>
            </div>

            <div class="calendar-weekdays">
                @foreach($weekdayLabels as $weekday)
                    <span>{{ $weekday }}</span>
                @endforeach
            </div>

            <div class="calendar-grid">
                @foreach($weeks as $week)
                    @foreach($week as $day)
                        <article class="calendar-day {{ $day['is_current_month'] ? '' : 'is-muted' }} {{ $day['is_today'] ? 'is-today' : '' }}">
                            <div class="calendar-day-head">
                                <span>{{ $day['date']->format('j') }}</span>
                                @if($day['events']->isNotEmpty())
                                    <b>{{ $day['events']->count() }}</b>
                                @endif
                            </div>

                            <div class="calendar-event-stack">
                                @foreach($day['events']->take(3) as $event)
                                    @if($event['url'])
                                        <a class="calendar-event tone-{{ $event['tone'] }}" href="{{ $event['url'] }}" title="{{ $event['title'] }}">
                                            <x-icon :name="$event['icon']" />
                                            <span>{{ $event['title'] }}</span>
                                        </a>
                                    @else
                                        <div class="calendar-event tone-{{ $event['tone'] }}" title="{{ $event['title'] }}">
                                            <x-icon :name="$event['icon']" />
                                            <span>{{ $event['title'] }}</span>
                                        </div>
                                    @endif
                                @endforeach

                                @if($day['events']->count() > 3)
                                    <span class="calendar-more">+{{ $day['events']->count() - 3 }} agenda lain</span>
                                @endif
                            </div>
                        </article>
                    @endforeach
                @endforeach
            </div>
        </div>

        <aside class="calendar-side">
            <section class="panel">
                <div class="panel-header">
                    <div>
                        <h2>Agenda Bulan Ini</h2>
                        <span>Urut dari tanggal paling dekat</span>
                    </div>
                    <x-icon name="list-checks" />
                </div>
                <div class="calendar-agenda-list">
                    @forelse($agendaEvents->filter(fn ($event) => $event['date']->betweenIncluded($periodStart, $periodEnd)) as $event)
                        <article class="calendar-agenda-item tone-{{ $event['tone'] }}">
                            <div class="calendar-agenda-date">
                                <strong>{{ $event['date']->format('d') }}</strong>
                                <span>{{ $event['date']->format('M') }}</span>
                            </div>
                            <div class="calendar-agenda-content">
                                <div class="calendar-agenda-title">
                                    <span class="calendar-type-dot tone-{{ $event['tone'] }}"><x-icon :name="$event['icon']" /></span>
                                    @if($event['url'])
                                        <a href="{{ $event['url'] }}">{{ $event['title'] }}</a>
                                    @else
                                        <strong>{{ $event['title'] }}</strong>
                                    @endif
                                </div>
                                <p>{{ $event['description'] ?: $event['label'] }}</p>
                                <div class="calendar-agenda-meta">
                                    <span>{{ $event['label'] }}</span>
                                    <span>{{ $event['time'] }}</span>
                                    <span>{{ $event['status'] }}</span>
                                    @if($event['location'])
                                        <span>{{ $event['location'] }}</span>
                                    @endif
                                </div>
                                @if($event['can_delete'] && auth()->user()->hasPrivilege('calendar.manage'))
                                    <form class="inline-form" method="post" action="{{ route('calendar.events.destroy', $event['source_id']) }}">
                                        @csrf
                                        @method('delete')
                                        <button class="ghost-button" type="submit"><x-icon name="trash-2" />Hapus</button>
                                    </form>
                                @endif
                            </div>
                        </article>
                    @empty
                        <div class="empty-state">Belum ada agenda pada filter ini.</div>
                    @endforelse
                </div>
            </section>

            @if(auth()->user()->hasPrivilege('calendar.manage'))
                <section class="panel calendar-event-form" id="calendar-event-form">
                    <div class="panel-header">
                        <div>
                            <h2>Tambah Agenda</h2>
                            <span>Agenda manual tetap tampil bersama data HRIS</span>
                        </div>
                        <x-icon name="calendar-plus" />
                    </div>
                    <form class="record-form form-panel" method="post" action="{{ route('calendar.events.store') }}">
                        @csrf
                        <div class="form-grid">
                            <label class="full">
                                Judul
                                <input name="title" value="{{ old('title') }}" required placeholder="Contoh: Townhall bulanan">
                            </label>
                            <label>
                                Tipe
                                <select name="type" required>
                                    @foreach($manualTypes as $typeKey => $typeMeta)
                                        <option value="{{ $typeKey }}" @selected(old('type', 'event') === $typeKey)>{{ $typeMeta['label'] }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                Warna
                                <select name="color">
                                    <option value="blue" @selected(old('color', 'blue') === 'blue')>Biru</option>
                                    <option value="green" @selected(old('color') === 'green')>Hijau</option>
                                    <option value="amber" @selected(old('color') === 'amber')>Amber</option>
                                    <option value="red" @selected(old('color') === 'red')>Merah</option>
                                    <option value="violet" @selected(old('color') === 'violet')>Violet</option>
                                    <option value="slate" @selected(old('color') === 'slate')>Slate</option>
                                </select>
                            </label>
                            <label>
                                Mulai
                                <input type="datetime-local" name="start_at" value="{{ old('start_at', now()->format('Y-m-d\\TH:i')) }}" required>
                            </label>
                            <label>
                                Selesai
                                <input type="datetime-local" name="end_at" value="{{ old('end_at') }}">
                            </label>
                            <label>
                                Lokasi
                                <input name="location" value="{{ old('location') }}" placeholder="Ruang meeting / cabang / online">
                            </label>
                            <label>
                                Audience
                                <select name="audience" required>
                                    <option value="all_systems" @selected(old('audience', 'all_systems') === 'all_systems')>Semua User Sistem</option>
                                    <option value="all" @selected(old('audience') === 'all')>Semua Pegawai</option>
                                    <option value="employee" @selected(old('audience') === 'employee')>Employee</option>
                                    <option value="admin" @selected(old('audience') === 'admin')>Admin</option>
                                    <option value="member" @selected(old('audience') === 'member')>Member</option>
                                    <option value="user" @selected(old('audience') === 'user')>User Viewer</option>
                                    @foreach($departments as $department)
                                        <option value="{{ $department }}" @selected(old('audience') === $department)>Departemen: {{ $department }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="checkbox-row full">
                                <input type="checkbox" name="all_day" value="1" @checked(old('all_day'))>
                                Sepanjang hari
                            </label>
                            <label class="full">
                                Deskripsi
                                <textarea name="description" placeholder="Catatan agenda, peserta, atau konteks kegiatan">{{ old('description') }}</textarea>
                            </label>
                        </div>
                        <div class="form-actions">
                            <button class="primary-button" type="submit"><x-icon name="save" />Simpan Agenda</button>
                        </div>
                    </form>
                </section>
            @endif
        </aside>
    </section>
@endsection
