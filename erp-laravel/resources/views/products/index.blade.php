@extends('layouts.app')

@php
    $activeProducts = $allProducts->where('is_active', true)->count();
    $sellableProducts = $allProducts->where('is_sellable', true)->count();
    $lowStockProducts = $allProducts->filter(fn ($product) => $product->availableStock() <= (float) $product->min_stock)->count();
    $inventoryValue = $allProducts->sum(fn ($product) => (float) $product->stock_on_hand * (float) $product->standard_cost);
@endphp

@section('title', 'Product Management - '.config('app.name'))

@section('actions')
    @if(auth()->user()->hasPrivilege('modules.sales.view'))
        <a class="ghost-button" href="{{ route('records.index', 'sales') }}"><x-icon name="shopping-cart" />Penjualan</a>
    @endif
    @if(auth()->user()->hasPrivilege('modules.manufacturing.view'))
        <a class="ghost-button" href="{{ route('records.index', 'manufacturing') }}"><x-icon name="factory" />Produksi</a>
    @endif
@endsection

@section('content')
    <header class="page-header">
        <div>
            <p class="eyebrow">Sales & Production Master</p>
            <h1>Product Management</h1>
            <p>Katalog produk, harga jual, costing, BOM, serta posisi stok untuk transaksi penjualan dan produksi.</p>
        </div>
        <div class="role-card">
            <span>Nilai Inventori</span>
            <strong>Rp {{ number_format($inventoryValue, 0, ',', '.') }}</strong>
        </div>
    </header>

    <div class="metrics-grid">
        <article class="metric-card">
            <span>Produk Aktif</span>
            <strong>{{ number_format($activeProducts, 0, ',', '.') }}</strong>
            <small>Katalog siap digunakan transaksi</small>
        </article>
        <article class="metric-card">
            <span>Produk Dijual</span>
            <strong>{{ number_format($sellableProducts, 0, ',', '.') }}</strong>
            <small>Produk tersedia di form Sales</small>
        </article>
        <article class="metric-card">
            <span>Low Stock</span>
            <strong>{{ number_format($lowStockProducts, 0, ',', '.') }}</strong>
            <small>Stok di bawah batas minimum</small>
        </article>
        <article class="metric-card">
            <span>Reserved</span>
            <strong>{{ number_format($allProducts->sum('reserved_stock'), 3, ',', '.') }}</strong>
            <small>Stok dipesan sales terkonfirmasi</small>
        </article>
    </div>

    <form class="filter-row" method="get" action="{{ route('products.index') }}">
        <input type="search" name="q" value="{{ $search }}" placeholder="Cari SKU, nama produk, barcode...">
        <select name="type">
            <option value="all">Semua tipe</option>
            @foreach($types as $type => $label)
                <option value="{{ $type }}" @selected($activeType === $type)>{{ $label }}</option>
            @endforeach
        </select>
        <select name="status">
            <option value="all">Semua status</option>
            <option value="active" @selected($activeStatus === 'active')>Aktif</option>
            <option value="inactive" @selected($activeStatus === 'inactive')>Nonaktif</option>
            <option value="low_stock" @selected($activeStatus === 'low_stock')>Low stock</option>
        </select>
        <button class="ghost-button" type="submit"><x-icon name="filter" />Filter</button>
        <a class="ghost-button" href="{{ route('products.index') }}"><x-icon name="rotate-ccw" />Reset</a>
    </form>

    <div class="dashboard-grid product-management-grid">
        <section class="panel">
            <div class="panel-header">
                <h2>Daftar Produk</h2>
                <span>{{ $products->total() }} produk</span>
            </div>
            <div class="product-list">
                @forelse($products as $product)
                    <article class="product-row">
                        <div class="product-row-head">
                            <div>
                                <div class="product-heading">
                                    <x-icon name="package" />
                                    <strong>{{ $product->sku }} - {{ $product->name }}</strong>
                                    <span class="status {{ $product->is_active ? 'success' : 'warning' }}">{{ $product->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                                </div>
                                <small>{{ $types[$product->type] ?? $product->type }} - {{ $product->category ?: '-' }} - {{ $product->warehouse ?: 'Tanpa gudang' }}</small>
                            </div>
                            <div class="product-stock {{ $product->availableStock() <= (float) $product->min_stock ? 'is-low' : '' }}">
                                <span>Tersedia</span>
                                <strong>{{ number_format($product->availableStock(), 3, ',', '.') }} {{ $product->uom }}</strong>
                            </div>
                        </div>

                        <div class="product-facts">
                            <div><span>Harga jual</span><strong>Rp {{ number_format($product->sales_price, 0, ',', '.') }}</strong></div>
                            <div><span>Cost</span><strong>Rp {{ number_format($product->standard_cost, 0, ',', '.') }}</strong></div>
                            <div><span>On hand</span><strong>{{ number_format($product->stock_on_hand, 3, ',', '.') }} {{ $product->uom }}</strong></div>
                            <div><span>Reserved</span><strong>{{ number_format($product->reserved_stock, 3, ',', '.') }} {{ $product->uom }}</strong></div>
                        </div>

                        @if($product->bomItems->isNotEmpty())
                            <div class="bom-line">
                                <strong><x-icon name="workflow" />BOM</strong>
                                @foreach($product->bomItems as $item)
                                    <span>{{ $item->component?->sku }} {{ number_format($item->quantity_per_unit, 4, ',', '.') }} {{ $item->component?->uom }}{{ (float) $item->waste_percent > 0 ? ' + waste '.number_format($item->waste_percent, 2, ',', '.').'%' : '' }}</span>
                                @endforeach
                            </div>
                        @endif

                        <details class="product-tools">
                            <summary><x-icon name="settings-2" />Kelola produk, BOM, dan stok</summary>
                            @if($canManage)
                                <form class="form-grid compact product-edit-form" method="post" action="{{ route('products.update', $product) }}">
                                    @csrf
                                    @method('PATCH')
                                    <label>SKU<input name="sku" value="{{ $product->sku }}" required></label>
                                    <label>Nama<input name="name" value="{{ $product->name }}" required></label>
                                    <label>Tipe
                                        <select name="type" required>
                                            @foreach($types as $type => $label)<option value="{{ $type }}" @selected($product->type === $type)>{{ $label }}</option>@endforeach
                                        </select>
                                    </label>
                                    <label>Kategori<input name="category" value="{{ $product->category }}"></label>
                                    <label>Satuan
                                        <select name="uom">@foreach($uoms as $uom)<option value="{{ $uom }}" @selected($product->uom === $uom)>{{ $uom }}</option>@endforeach</select>
                                    </label>
                                    <label>Barcode<input name="barcode" value="{{ $product->barcode }}"></label>
                                    <label>Harga Jual<input type="number" min="0" name="sales_price" value="{{ $product->sales_price }}" required></label>
                                    <label>Standard Cost<input type="number" min="0" name="standard_cost" value="{{ $product->standard_cost }}" required></label>
                                    <label>PPN %<input type="number" min="0" max="100" step="0.01" name="tax_rate" value="{{ $product->tax_rate }}" required></label>
                                    <label>Minimum Stock<input type="number" min="0" step="0.001" name="min_stock" value="{{ $product->min_stock }}" required></label>
                                    <label>Gudang<input name="warehouse" value="{{ $product->warehouse }}"></label>
                                    <label class="checkbox-row"><input type="checkbox" name="is_sellable" value="1" @checked($product->is_sellable)>Dapat dijual</label>
                                    <label class="checkbox-row"><input type="checkbox" name="is_manufacturable" value="1" @checked($product->is_manufacturable)>Dapat diproduksi</label>
                                    <label class="checkbox-row"><input type="checkbox" name="is_active" value="1" @checked($product->is_active)>Aktif</label>
                                    <label class="full">Catatan<textarea name="notes">{{ $product->notes }}</textarea></label>
                                    <div class="form-actions full"><button class="primary-button" type="submit"><x-icon name="save" />Simpan Produk</button></div>
                                </form>

                                <div class="product-tool-grid">
                                    <form class="form-grid compact" method="post" action="{{ route('products.adjustments.store', $product) }}">
                                        @csrf
                                        <h3 class="full">Penyesuaian Stok</h3>
                                        <label>Arah
                                            <select name="direction"><option value="in">Stok masuk</option><option value="out">Stok keluar</option></select>
                                        </label>
                                        <label>Jumlah<input type="number" min="0.001" step="0.001" name="quantity" required></label>
                                        <label class="full">Alasan<textarea name="notes" required placeholder="Stock opname, koreksi gudang, atau penerimaan awal"></textarea></label>
                                        <button class="ghost-button full" type="submit"><x-icon name="arrow-left-right" />Catat Adjustment</button>
                                    </form>

                                    @if($product->is_manufacturable)
                                        <form class="form-grid compact" method="post" action="{{ route('products.bom.store', $product) }}">
                                            @csrf
                                            <h3 class="full">Bill of Materials</h3>
                                            <label class="full">Komponen
                                                <select name="component_product_id" required>
                                                    <option value="">Pilih bahan/komponen</option>
                                                    @foreach($components->where('id', '!=', $product->id) as $component)
                                                        <option value="{{ $component->id }}">{{ $component->sku }} - {{ $component->name }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                            <label>Qty / unit<input type="number" min="0.0001" step="0.0001" name="quantity_per_unit" required></label>
                                            <label>Waste %<input type="number" min="0" max="100" step="0.01" name="waste_percent" value="0"></label>
                                            <label class="full">Catatan<textarea name="notes"></textarea></label>
                                            <button class="ghost-button full" type="submit"><x-icon name="plus" />Simpan Komponen</button>
                                        </form>
                                    @endif
                                </div>
                            @endif

                            @if($product->bomItems->isNotEmpty())
                                <div class="bom-management-list">
                                    @foreach($product->bomItems as $item)
                                        <div>
                                            <span>{{ $item->component?->sku }} - {{ $item->component?->name }} / {{ number_format($item->quantity_per_unit, 4, ',', '.') }} {{ $item->component?->uom }}</span>
                                            @if($canManage)
                                                <form method="post" action="{{ route('products.bom.destroy', [$product, $item]) }}">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="icon-link danger-text" type="submit"><x-icon name="trash-2" />Hapus</button>
                                                </form>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endif

                            <div class="movement-mini-list">
                                <strong>Pergerakan Terakhir</strong>
                                @forelse($product->stockMovements->take(5) as $movement)
                                    <div>
                                        <span>{{ $movement->occurred_at?->format('d M Y H:i') }} - {{ $movement->reference ?: $movement->movement_type }}</span>
                                        <b class="{{ $movement->direction === 'in' ? 'pay-text' : '' }}">{{ $movement->direction === 'in' ? '+' : ($movement->direction === 'reserve' ? 'R ' : '-') }}{{ number_format($movement->quantity, 3, ',', '.') }} {{ $product->uom }}</b>
                                    </div>
                                @empty
                                    <span class="muted">Belum ada pergerakan stok.</span>
                                @endforelse
                            </div>
                        </details>
                    </article>
                @empty
                    <div class="empty-state">Belum ada produk yang cocok.</div>
                @endforelse
            </div>
            <div class="pagination-wrap">{{ $products->links() }}</div>
        </section>

        <section class="panel form-panel">
            <div class="panel-header no-border">
                <div>
                    <h2>Tambah Produk</h2>
                    <span>Master produk tunggal untuk Sales dan Produksi.</span>
                </div>
            </div>
            @if($canManage)
                <form class="form-grid product-create-form" method="post" action="{{ route('products.store') }}">
                    @csrf
                    <label>SKU<input name="sku" value="{{ old('sku') }}" required placeholder="FG-001"></label>
                    <label>Nama<input name="name" value="{{ old('name') }}" required></label>
                    <label>Tipe
                        <select name="type" required>@foreach($types as $type => $label)<option value="{{ $type }}" @selected(old('type') === $type)>{{ $label }}</option>@endforeach</select>
                    </label>
                    <label>Kategori<input name="category" value="{{ old('category') }}"></label>
                    <label>Satuan
                        <select name="uom">@foreach($uoms as $uom)<option value="{{ $uom }}" @selected(old('uom', 'pcs') === $uom)>{{ $uom }}</option>@endforeach</select>
                    </label>
                    <label>Barcode<input name="barcode" value="{{ old('barcode') }}"></label>
                    <label>Harga Jual<input type="number" min="0" name="sales_price" value="{{ old('sales_price', 0) }}" required></label>
                    <label>Standard Cost<input type="number" min="0" name="standard_cost" value="{{ old('standard_cost', 0) }}" required></label>
                    <label>PPN %<input type="number" min="0" max="100" step="0.01" name="tax_rate" value="{{ old('tax_rate', 11) }}" required></label>
                    <label>Minimum Stock<input type="number" min="0" step="0.001" name="min_stock" value="{{ old('min_stock', 0) }}" required></label>
                    <label>Stok Awal<input type="number" min="0" step="0.001" name="initial_stock" value="{{ old('initial_stock', 0) }}"></label>
                    <label>Gudang<input name="warehouse" value="{{ old('warehouse', 'Jakarta') }}"></label>
                    <label class="checkbox-row"><input type="checkbox" name="is_sellable" value="1" @checked(old('is_sellable'))>Dapat dijual</label>
                    <label class="checkbox-row"><input type="checkbox" name="is_manufacturable" value="1" @checked(old('is_manufacturable'))>Dapat diproduksi</label>
                    <label class="full">Catatan<textarea name="notes">{{ old('notes') }}</textarea></label>
                    <button class="primary-button full" type="submit"><x-icon name="plus" />Tambah Produk</button>
                </form>
            @else
                <div class="empty-state">Anda memiliki akses lihat katalog produk.</div>
            @endif
        </section>
    </div>
@endsection
