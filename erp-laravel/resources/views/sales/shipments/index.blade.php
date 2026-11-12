@extends('layouts.app')

@php
    $totalCharge = $allShipments->sum(fn ($shipment) => (float) $shipment->customer_shipping_charge + (float) $shipment->insurance_cost);
    $totalCourierCost = $allShipments->sum(fn ($shipment) => (float) $shipment->courier_cost + (float) $shipment->insurance_cost);
    $activeDelivery = $allShipments->whereIn('status', ['ready_to_ship', 'pickup_requested', 'picked_up', 'in_transit', 'out_for_delivery'])->count();
    $pendingSettlement = $allShipments->whereIn('settlement_status', ['pending', 'partial'])->count();
@endphp

@section('title', 'Pengiriman Penjualan - '.config('app.name'))

@section('actions')
    <a class="ghost-button" href="{{ route('sales.installments.index') }}"><x-icon name="calendar-clock" />Cicilan</a>
    <a class="ghost-button" href="{{ route('records.index', 'sales') }}"><x-icon name="arrow-left" />Penjualan</a>
@endsection

@section('content')
    <header class="page-header">
        <div>
            <p class="eyebrow">Order to Cash</p>
            <h1>Pengiriman & Tracking</h1>
            <p>Kontrol fulfillment dan biaya distribusi per sales order.</p>
        </div>
        <div class="role-card">
            <span>Pengiriman Aktif</span>
            <strong>{{ number_format($activeDelivery, 0, ',', '.') }}</strong>
        </div>
    </header>

    <div class="metrics-grid">
        <article class="metric-card">
            <span>Total Shipment</span>
            <strong>{{ number_format($allShipments->count(), 0, ',', '.') }}</strong>
            <small>Shipment terkait sales order</small>
        </article>
        <article class="metric-card">
            <span>Tagihan Ongkir</span>
            <strong>Rp {{ number_format($totalCharge, 0, ',', '.') }}</strong>
            <small>AR customer di Finance</small>
        </article>
        <article class="metric-card">
            <span>Biaya Kurir</span>
            <strong>Rp {{ number_format($totalCourierCost, 0, ',', '.') }}</strong>
            <small>AP jasa kurir di Finance</small>
        </article>
        <article class="metric-card">
            <span>Belum Settlement</span>
            <strong>{{ number_format($pendingSettlement, 0, ',', '.') }}</strong>
            <small>Tagihan atau biaya masih terbuka</small>
        </article>
    </div>

    <div class="dashboard-grid shipping-management-grid">
        <section class="panel form-panel">
            <div class="panel-header no-border">
                <div>
                    <h2>Buat Shipment</h2>
                    <span>Data pengiriman dari sales order terkonfirmasi.</span>
                </div>
            </div>

            @if($canManage)
                <form class="record-form" method="post" action="{{ route('sales.shipments.store') }}">
                    @csrf
                    <div class="form-grid">
                        <label class="full">
                            Sales Order
                            <select name="sales_record_id" required>
                                <option value="">Pilih sales order</option>
                                @foreach($salesRecords as $sales)
                                    <option value="{{ $sales->id }}" @selected((int) old('sales_record_id', $selectedSalesId) === $sales->id)>
                                        {{ $sales->code }} - {{ $sales->title }} - {{ $sales->status }}
                                    </option>
                                @endforeach
                            </select>
                            @error('sales_record_id') <small class="field-error">{{ $message }}</small> @enderror
                        </label>

                        <label>
                            Jasa Kurir
                            <select name="courier_provider_id" id="shipmentCourier" required>
                                <option value="">Pilih kurir</option>
                                @foreach($providers->where('is_active', true) as $provider)
                                    <option value="{{ $provider->id }}"
                                        data-services='@json($provider->services ?? [])'
                                        data-cod="{{ $provider->supports_cod ? '1' : '0' }}"
                                        @selected((int) old('courier_provider_id') === $provider->id)>
                                        {{ $provider->name }}
                                    </option>
                                @endforeach
                            </select>
                            @error('courier_provider_id') <small class="field-error">{{ $message }}</small> @enderror
                        </label>

                        <label>
                            Layanan
                            <select name="service_code" id="shipmentService">
                                <option value="">Pilih layanan</option>
                            </select>
                            @error('service_code') <small class="field-error">{{ $message }}</small> @enderror
                        </label>

                        <label>
                            Nomor Resi
                            <input type="text" name="tracking_number" value="{{ old('tracking_number') }}">
                            @error('tracking_number') <small class="field-error">{{ $message }}</small> @enderror
                        </label>

                        <label>
                            Kebijakan Pembayaran
                            <select name="payment_policy" required>
                                <option value="prepaid" @selected(old('payment_policy', 'prepaid') === 'prepaid')>Prepaid</option>
                                <option value="credit" @selected(old('payment_policy') === 'credit')>Credit</option>
                                <option value="cod" @selected(old('payment_policy') === 'cod')>COD</option>
                            </select>
                            @error('payment_policy') <small class="field-error">{{ $message }}</small> @enderror
                        </label>

                        <label>
                            Penerima
                            <input type="text" name="recipient_name" value="{{ old('recipient_name') }}" required>
                            @error('recipient_name') <small class="field-error">{{ $message }}</small> @enderror
                        </label>

                        <label>
                            Telepon Penerima
                            <input type="text" name="recipient_phone" value="{{ old('recipient_phone') }}">
                            @error('recipient_phone') <small class="field-error">{{ $message }}</small> @enderror
                        </label>

                        <label class="full">
                            Alamat Penerima
                            <textarea name="recipient_address" required>{{ old('recipient_address') }}</textarea>
                            @error('recipient_address') <small class="field-error">{{ $message }}</small> @enderror
                        </label>

                        <label>
                            Asal
                            <input type="text" name="origin" value="{{ old('origin') }}">
                        </label>

                        <label>
                            Tujuan
                            <input type="text" name="destination" value="{{ old('destination') }}">
                        </label>

                        <label>
                            Berat (kg)
                            <input type="number" name="weight_kg" min="0" step="0.01" value="{{ old('weight_kg', 1) }}">
                        </label>

                        <label>
                            Jumlah Paket
                            <input type="number" name="package_count" min="1" value="{{ old('package_count', 1) }}">
                        </label>

                        <label>
                            Tagihan Ongkir Customer
                            <input type="number" name="customer_shipping_charge" min="0" step="0.01" value="{{ old('customer_shipping_charge', 0) }}">
                        </label>

                        <label>
                            Biaya Jasa Kurir
                            <input type="number" name="courier_cost" min="0" step="0.01" value="{{ old('courier_cost', 0) }}">
                        </label>

                        <label>
                            Asuransi
                            <input type="number" name="insurance_cost" min="0" step="0.01" value="{{ old('insurance_cost', 0) }}">
                        </label>

                        <label>
                            Jadwal Pickup
                            <input type="datetime-local" name="pickup_scheduled_at" value="{{ old('pickup_scheduled_at') }}">
                        </label>

                        <label>
                            Estimasi Sampai
                            <input type="datetime-local" name="estimated_delivery_at" value="{{ old('estimated_delivery_at') }}">
                        </label>

                        <label class="full">
                            Catatan
                            <textarea name="notes">{{ old('notes') }}</textarea>
                        </label>
                    </div>
                    <div class="form-actions">
                        <button class="primary-button" type="submit"><x-icon name="truck" />Buat Shipment</button>
                    </div>
                </form>
            @else
                <div class="empty-state">Akun ini hanya dapat melihat shipment.</div>
            @endif
        </section>

        <section class="panel courier-management-panel">
            <div class="panel-header">
                <div>
                    <h2>Jasa Kurir</h2>
                    <span>{{ $providers->count() }} provider terdaftar</span>
                </div>
            </div>
            <div class="courier-provider-list">
                @foreach($providers as $provider)
                    <details class="courier-config">
                        <summary>
                            <span class="module-card-title">
                                <x-icon name="truck" />
                                <span>
                                    <strong>{{ $provider->name }}</strong>
                                    <small>{{ strtoupper($provider->integration_mode) }} - {{ $provider->supports_cod ? 'COD' : 'Non COD' }}</small>
                                </span>
                            </span>
                            <span class="status {{ $provider->is_active ? 'success' : 'warning' }}">{{ $provider->is_active ? 'Aktif' : 'Nonaktif' }}</span>
                        </summary>
                        @if($canManage)
                            <form class="form-grid compact courier-form" method="post" action="{{ route('sales.couriers.update', $provider) }}">
                                @csrf
                                @method('PATCH')
                                <label>Kode <input type="text" name="code" value="{{ $provider->code }}" required></label>
                                <label>Nama <input type="text" name="name" value="{{ $provider->name }}" required></label>
                                <label>Integrasi
                                    <select name="integration_mode">
                                        @foreach(['manual', 'link', 'webhook', 'api'] as $mode)
                                            <option value="{{ $mode }}" @selected($provider->integration_mode === $mode)>{{ strtoupper($mode) }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>Layanan <input type="text" name="services_text" value="{{ implode(', ', $provider->services ?? []) }}"></label>
                                <label class="full">Template URL Tracking <input type="text" name="tracking_url_template" value="{{ $provider->tracking_url_template }}" placeholder="https://provider.test/track/{tracking_number}"></label>
                                <label class="full">API Base URL <input type="text" name="api_base_url" value="{{ $provider->api_base_url }}"></label>
                                <label class="full">Webhook Secret Baru <input type="password" name="webhook_secret"></label>
                                <label class="checkbox-row"><input type="checkbox" name="supports_cod" value="1" @checked($provider->supports_cod)> Mendukung COD</label>
                                <label class="checkbox-row"><input type="checkbox" name="is_active" value="1" @checked($provider->is_active)> Aktif</label>
                                <div class="full form-actions">
                                    <button class="ghost-button" type="submit"><x-icon name="save" />Simpan Kurir</button>
                                </div>
                            </form>
                        @endif
                    </details>
                @endforeach
            </div>

            @if($canManage)
                <div class="panel-header">
                    <h2>Tambah Kurir</h2>
                </div>
                <form class="form-grid compact provider-create-form" method="post" action="{{ route('sales.couriers.store') }}">
                    @csrf
                    <label>Kode <input type="text" name="code" required></label>
                    <label>Nama <input type="text" name="name" required></label>
                    <label>Integrasi
                        <select name="integration_mode">
                            <option value="manual">MANUAL</option>
                            <option value="link">LINK</option>
                            <option value="webhook">WEBHOOK</option>
                            <option value="api">API</option>
                        </select>
                    </label>
                    <label>Layanan <input type="text" name="services_text"></label>
                    <label class="full">Template URL Tracking <input type="text" name="tracking_url_template" placeholder="https://provider.test/track/{tracking_number}"></label>
                    <label class="full">API Base URL <input type="text" name="api_base_url"></label>
                    <label class="full">Webhook Secret <input type="password" name="webhook_secret"></label>
                    <label class="checkbox-row"><input type="checkbox" name="supports_cod" value="1"> Mendukung COD</label>
                    <div class="full form-actions">
                        <button class="primary-button" type="submit"><x-icon name="plus" />Tambah Kurir</button>
                    </div>
                </form>
            @endif
        </section>
    </div>

    <form class="filter-row shipping-filter" method="get" action="{{ route('sales.shipments.index') }}">
        <input type="search" name="q" value="{{ $search }}" placeholder="Cari shipment, resi, sales, penerima...">
        <select name="status">
            <option value="all">Semua status</option>
            @foreach($statuses as $status)
                <option value="{{ $status }}" @selected($activeStatus === $status)>{{ str($status)->replace('_', ' ')->headline() }}</option>
            @endforeach
        </select>
        <button class="ghost-button" type="submit"><x-icon name="filter" />Filter</button>
        <a class="ghost-button" href="{{ route('sales.shipments.index') }}"><x-icon name="rotate-ccw" />Reset</a>
    </form>

    <section class="panel">
        <div class="panel-header">
            <div>
                <h2>Daftar Pengiriman</h2>
                <span>{{ $shipments->total() }} shipment</span>
            </div>
        </div>
        <div class="shipment-list">
            @forelse($shipments as $shipment)
                @php
                    $chargeRecord = $shipment->customerChargeFinanceRecord;
                    $costRecord = $shipment->courierCostFinanceRecord;
                    $canPayFinance = auth()->user()->hasPrivilege('payments.create') && auth()->user()->hasPrivilege('modules.finance.pay');
                @endphp
                <article class="shipment-item">
                    <header class="shipment-heading">
                        <div>
                            <div class="shipment-title">
                                <x-icon name="package" />
                                <strong>{{ $shipment->shipment_no }}</strong>
                                <span class="status {{ $shipment->status === 'delivered' ? 'success' : ($shipment->status === 'waiting_payment' || $shipment->status === 'exception' ? 'warning' : '') }}">{{ str($shipment->status)->replace('_', ' ')->headline() }}</span>
                            </div>
                            <span>{{ $shipment->courierProvider?->name ?: '-' }} / {{ $shipment->service_code ?: '-' }} - Resi {{ $shipment->tracking_number ?: '-' }}</span>
                        </div>
                        <div class="row-actions">
                            @if($shipment->salesRecord)
                                <a class="icon-link" href="{{ route('records.index', ['module' => 'sales', 'q' => $shipment->salesRecord->code]) }}"><x-icon name="shopping-cart" />{{ $shipment->salesRecord->code }}</a>
                            @endif
                            @if($shipment->externalTrackingUrl())
                                <a class="icon-link" href="{{ $shipment->externalTrackingUrl() }}" target="_blank" rel="noopener"><x-icon name="external-link" />Tracking</a>
                            @endif
                        </div>
                    </header>

                    <div class="shipment-summary-grid">
                        <div><span>Penerima</span><strong>{{ $shipment->recipient_name }}</strong><small>{{ $shipment->destination ?: $shipment->recipient_address }}</small></div>
                        <div><span>Policy</span><strong>{{ strtoupper($shipment->payment_policy) }}</strong><small>Settlement {{ strtoupper($shipment->settlement_status) }}</small></div>
                        <div><span>Ongkir Customer</span><strong>Rp {{ number_format((float) $shipment->customer_shipping_charge + (float) $shipment->insurance_cost, 0, ',', '.') }}</strong><small>{{ strtoupper($shipment->customer_charge_payment_status) }}</small></div>
                        <div><span>Biaya Kurir</span><strong>Rp {{ number_format((float) $shipment->courier_cost + (float) $shipment->insurance_cost, 0, ',', '.') }}</strong><small>{{ strtoupper($shipment->courier_cost_payment_status) }}</small></div>
                    </div>

                    <div class="shipment-finance-actions">
                        @if($chargeRecord && auth()->user()->hasPrivilege('modules.finance.view'))
                            <a class="relation-chip" href="{{ route('records.index', ['module' => 'finance', 'q' => $chargeRecord->code]) }}" title="Tagihan ongkir pelanggan"><x-icon name="landmark" /><span>{{ $chargeRecord->code }} AR</span></a>
                            @if($canPayFinance && \App\Support\PaymentRules::isPayable($chargeRecord))
                                <a class="icon-link pay-text" href="{{ route('payments.create', $chargeRecord) }}"><x-icon name="credit-card" />Bayar AR</a>
                            @endif
                        @endif
                        @if($costRecord && auth()->user()->hasPrivilege('modules.finance.view'))
                            <a class="relation-chip" href="{{ route('records.index', ['module' => 'finance', 'q' => $costRecord->code]) }}" title="Tagihan jasa kurir"><x-icon name="landmark" /><span>{{ $costRecord->code }} AP</span></a>
                            @if($canPayFinance && \App\Support\PaymentRules::isPayable($costRecord))
                                <a class="icon-link pay-text" href="{{ route('payments.create', $costRecord) }}"><x-icon name="credit-card" />Bayar AP</a>
                            @endif
                        @endif
                    </div>

                    <div class="shipment-body">
                        <div class="tracking-timeline">
                            @forelse($shipment->trackingEvents->take(6) as $event)
                                <div class="tracking-event">
                                    <x-icon name="{{ $event->status === 'delivered' ? 'check-circle-2' : 'map-pin' }}" />
                                    <div>
                                        <strong>{{ str($event->status)->replace('_', ' ')->headline() }}</strong>
                                        <span>{{ optional($event->event_at)->format('d M Y H:i') }}{{ $event->location ? ' - '.$event->location : '' }}</span>
                                        @if($event->description)
                                            <small>{{ $event->description }}</small>
                                        @endif
                                    </div>
                                </div>
                            @empty
                                <div class="empty-state">Belum ada event tracking.</div>
                            @endforelse
                        </div>

                        @if($canManage)
                            <form class="form-grid compact shipment-event-form" method="post" action="{{ route('sales.shipments.tracking.store', $shipment) }}">
                                @csrf
                                <label>Status
                                    <select name="status" required>
                                        @foreach($statuses as $status)
                                            <option value="{{ $status }}" @selected($shipment->status === $status)>{{ str($status)->replace('_', ' ')->headline() }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>Resi <input type="text" name="tracking_number" value="{{ $shipment->tracking_number }}"></label>
                                <label>Lokasi <input type="text" name="location"></label>
                                <label>Waktu <input type="datetime-local" name="event_at" value="{{ now()->format('Y-m-d\TH:i') }}"></label>
                                <label class="full">Keterangan <input type="text" name="description"></label>
                                <div class="full form-actions">
                                    <button class="ghost-button" type="submit"><x-icon name="map-pinned" />Update Tracking</button>
                                </div>
                            </form>
                        @endif
                    </div>
                </article>
            @empty
                <div class="empty-state">Belum ada pengiriman penjualan.</div>
            @endforelse
        </div>
        <div class="pagination-wrap">{{ $shipments->links() }}</div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const provider = document.getElementById('shipmentCourier');
            const service = document.getElementById('shipmentService');
            const selectedService = @json(old('service_code'));

            function renderServices() {
                const option = provider?.selectedOptions[0];
                let services = [];

                try {
                    services = option?.dataset.services ? JSON.parse(option.dataset.services) : [];
                } catch (error) {
                    services = [];
                }

                service.innerHTML = '<option value="">Pilih layanan</option>';
                services.forEach(function (name) {
                    const item = document.createElement('option');
                    item.value = name;
                    item.textContent = name;
                    item.selected = selectedService === name;
                    service.appendChild(item);
                });
            }

            provider?.addEventListener('change', renderServices);
            renderServices();
        });
    </script>
@endsection
