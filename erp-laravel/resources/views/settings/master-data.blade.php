@extends('layouts.app')

@php($masterLinkIcons = [
    'User' => 'user-cog',
    'Role & Privilege' => 'shield-check',
    'Report Master' => 'bar-chart-3',
])

@section('title', 'Master Data - '.config('app.name'))

@section('content')
    <header class="page-header">
        <div>
            <p class="eyebrow">Settings</p>
            <h1>Management Master Data</h1>
            <p>Kelola referensi sistem, menu, role, type, employer, rekening, account, product, partner, lokasi, dan opsi semua modul.</p>
        </div>
        <div class="role-card">
            <span>Total group</span>
            <strong>{{ $groups->count() }}</strong>
        </div>
    </header>

    <div class="metrics-grid">
        <article class="metric-card">
            <span>Group Aktif</span>
            <strong>{{ $groups->where('is_active', true)->count() }}</strong>
            <small>Referensi yang digunakan sistem</small>
        </article>
        <article class="metric-card">
            <span>Item Aktif</span>
            <strong>{{ number_format($groups->sum('items_count'), 0, ',', '.') }}</strong>
            <small>Total item master data</small>
        </article>
        <article class="metric-card">
            <span>Module Option</span>
            <strong>{{ $groups->where('type', 'module')->count() }}</strong>
            <small>Status dan select option modul ERP</small>
        </article>
        <article class="metric-card">
            <span>System Master</span>
            <strong>{{ $groups->where('is_system', true)->count() }}</strong>
            <small>Default dari konfigurasi aplikasi</small>
        </article>
    </div>

    <div class="master-link-grid">
        @foreach($systemLinks as $link)
            <a class="master-link-card {{ $link['route'] ? '' : 'is-disabled' }}" href="{{ $link['route'] ?: '#' }}">
                <span class="master-link-title"><x-icon :name="$masterLinkIcons[$link['label']] ?? 'database'" /><strong>{{ $link['label'] }}</strong></span>
                <span>{{ $link['description'] }}</span>
            </a>
        @endforeach
    </div>

    <section class="panel master-panel">
        <div class="panel-header">
            <h2>Filter Group</h2>
            <span>{{ count($types) }} tipe</span>
        </div>
        <form class="filter-row master-filter" method="get" action="{{ route('settings.master-data') }}">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari group master...">
            <select name="type">
                <option value="">Semua tipe</option>
                @foreach($types as $key => $label)
                    <option value="{{ $key }}" @selected(request('type') === $key)>{{ $label }}</option>
                @endforeach
            </select>
            <button class="ghost-button" type="submit"><x-icon name="filter" />Filter</button>
            <a class="ghost-button" href="{{ route('settings.master-data') }}"><x-icon name="rotate-ccw" />Reset</a>
        </form>
    </section>

    <div class="master-data-grid">
        <section class="panel master-group-list">
            <div class="panel-header">
                <h2>Master Group</h2>
                <span>{{ $groups->count() }} group</span>
            </div>
            <div class="master-list">
                @forelse($groups as $group)
                    <a class="master-group-item {{ $activeGroup?->id === $group->id ? 'is-active' : '' }}" href="{{ route('settings.master-data', ['group' => $group->key, 'type' => request('type'), 'q' => request('q')]) }}">
                        <div>
                            <strong>{{ $group->name }}</strong>
                            <span>{{ $group->key }}</span>
                        </div>
                        <b>{{ $group->items_count }}</b>
                    </a>
                @empty
                    <div class="empty-state">Belum ada group master data.</div>
                @endforelse
            </div>
        </section>

        <section class="panel master-workspace">
            <div class="panel-header">
                <h2>{{ $activeGroup?->name ?: 'Group Baru' }}</h2>
                <span>{{ $activeGroup?->items->count() ?? 0 }} item</span>
            </div>

            <div class="master-forms">
                <form class="master-form" method="post" action="{{ $activeGroup ? route('settings.master-data.groups.update', $activeGroup) : route('settings.master-data.groups.store') }}">
                    @csrf
                    @if($activeGroup)
                        @method('PATCH')
                    @endif
                    <h3>{{ $activeGroup ? 'Detail Group' : 'Buat Group' }}</h3>
                    <div class="form-grid compact">
                        <label>
                            <span>Key</span>
                            <input type="text" name="key" value="{{ old('key', $activeGroup?->key) }}" placeholder="contoh: master.products">
                        </label>
                        <label>
                            <span>Nama</span>
                            <input type="text" name="name" value="{{ old('name', $activeGroup?->name) }}" required>
                        </label>
                        <label>
                            <span>Tipe</span>
                            <select name="type" required>
                                @foreach($types as $key => $label)
                                    <option value="{{ $key }}" @selected(old('type', $activeGroup?->type) === $key)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span>Modul</span>
                            <select name="module">
                                <option value="">Global</option>
                                @foreach($modules as $moduleKey => $moduleConfig)
                                    <option value="{{ $moduleKey }}" @selected(old('module', $activeGroup?->module) === $moduleKey)>{{ $moduleConfig['label'] }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label>
                            <span>Urutan</span>
                            <input type="number" name="sort_order" value="{{ old('sort_order', $activeGroup?->sort_order ?? 0) }}">
                        </label>
                        <label class="checkbox-row master-checkbox">
                            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $activeGroup?->is_active ?? true))>
                            Aktif
                        </label>
                        <label class="full">
                            <span>Deskripsi</span>
                            <textarea name="description">{{ old('description', $activeGroup?->description) }}</textarea>
                        </label>
                    </div>
                    <div class="form-actions">
                        <button class="primary-button" type="submit"><x-icon :name="$activeGroup ? 'save' : 'plus'" />{{ $activeGroup ? 'Simpan Group' : 'Buat Group' }}</button>
                    </div>
                </form>

                @if($activeGroup)
                    <form class="master-form" method="post" action="{{ route('settings.master-data.items.store', $activeGroup) }}">
                        @csrf
                        <h3>Tambah Item</h3>
                        <div class="form-grid compact">
                            <label>
                                <span>Code</span>
                                <input type="text" name="code" placeholder="auto jika kosong">
                            </label>
                            <label>
                                <span>Nama</span>
                                <input type="text" name="name" required>
                            </label>
                            <label>
                                <span>Value</span>
                                <input type="text" name="value" placeholder="dipakai di form modul">
                            </label>
                            <label>
                                <span>Parent</span>
                                <select name="parent_id">
                                    <option value="">Tidak ada</option>
                                    @foreach($activeGroup->items as $parent)
                                        <option value="{{ $parent->id }}">{{ $parent->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label>
                                <span>Urutan</span>
                                <input type="number" name="sort_order" value="{{ ($activeGroup->items->max('sort_order') ?? 0) + 1 }}">
                            </label>
                            <label class="checkbox-row master-checkbox">
                                <input type="checkbox" name="is_active" value="1" checked>
                                Aktif
                            </label>
                            <label class="full">
                                <span>Deskripsi</span>
                                <textarea name="description"></textarea>
                            </label>
                        </div>
                        <div class="form-actions">
                            <button class="primary-button" type="submit"><x-icon name="plus" />Tambah Item</button>
                        </div>
                    </form>
                @endif
            </div>

            @if($activeGroup)
                <div class="table-wrap master-table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Nama</th>
                                <th>Value</th>
                                <th>Parent</th>
                                <th>Status</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($activeGroup->items as $item)
                                <tr>
                                    <td>
                                        <input class="table-input" form="master-item-{{ $item->id }}" type="text" name="code" value="{{ $item->code }}">
                                    </td>
                                    <td>
                                        <input class="table-input" form="master-item-{{ $item->id }}" type="text" name="name" value="{{ $item->name }}" required>
                                    </td>
                                    <td>
                                        <input class="table-input" form="master-item-{{ $item->id }}" type="text" name="value" value="{{ $item->value }}">
                                    </td>
                                    <td>
                                        <select class="table-input" form="master-item-{{ $item->id }}" name="parent_id">
                                            <option value="">-</option>
                                            @foreach($activeGroup->items->where('id', '!=', $item->id) as $parent)
                                                <option value="{{ $parent->id }}" @selected($item->parent_id === $parent->id)>{{ $parent->name }}</option>
                                            @endforeach
                                        </select>
                                    </td>
                                    <td>
                                        <label class="checkbox-row">
                                            <input form="master-item-{{ $item->id }}" type="checkbox" name="is_active" value="1" @checked($item->is_active)>
                                            {{ $item->is_active ? 'Aktif' : 'Off' }}
                                        </label>
                                        <input form="master-item-{{ $item->id }}" type="hidden" name="sort_order" value="{{ $item->sort_order }}">
                                        <textarea class="sr-only" form="master-item-{{ $item->id }}" name="description">{{ $item->description }}</textarea>
                                    </td>
                                    <td>
                                        <div class="row-actions">
                                            <form id="master-item-{{ $item->id }}" method="post" action="{{ route('settings.master-data.items.update', $item) }}">
                                                @csrf
                                                @method('PATCH')
                                            </form>
                                            <button class="icon-link" type="submit" form="master-item-{{ $item->id }}"><x-icon name="save" />Simpan</button>
                                            <form method="post" action="{{ route('settings.master-data.items.destroy', $item) }}" onsubmit="return confirm('Hapus item {{ $item->name }}?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="icon-link danger-text" type="submit"><x-icon name="trash-2" />Hapus</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="empty-state">Belum ada item untuk group ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
@endsection
