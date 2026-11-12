@extends('layouts.app')

@section('title', 'Checkout Pembayaran - '.config('app.name'))

@section('content')
    @php
        $selectedProvider = old('provider', $gateways['midtrans']['configured'] ? 'midtrans' : ($gateways['paylabs']['configured'] ? 'paylabs' : 'midtrans'));
        $hasConfiguredGateway = $gateways['midtrans']['configured'] || $gateways['paylabs']['configured'];
    @endphp
    <header class="page-header">
        <div>
            <p class="eyebrow">Payment Gateway</p>
            <h1>Checkout Pembayaran</h1>
            <p>Buat transaksi dari dokumen Keuangan {{ $record->code }} melalui gateway pembayaran yang dipilih.</p>
        </div>
        <div class="role-card">
            <span>Total bayar</span>
            <strong>Rp {{ number_format($record->amount, 0, ',', '.') }}</strong>
        </div>
    </header>

    @unless($hasConfiguredGateway)
        <div class="alert danger">
            Belum ada gateway siap digunakan. Isi credential Midtrans atau konfigurasi Paylabs pada file .env.
        </div>
    @endunless

    <div class="alert success">
        Pembayaran dipusatkan di modul Keuangan. Status paid yang terverifikasi akan memperbarui modul terkait.
    </div>

    <div class="dashboard-grid">
        <section class="panel form-panel">
            <h2>Detail Tagihan</h2>
            <div class="payment-summary">
                <div><span>Record</span><strong>{{ $record->code }}</strong></div>
                <div><span>Nama</span><strong>{{ $record->title }}</strong></div>
                <div><span>Modul</span><strong>{{ $paymentContext['module_label'] }}</strong></div>
                <div><span>Proses</span><strong>{{ $paymentContext['label'] }}</strong></div>
                <div><span>Arah Transaksi</span><strong>{{ $paymentContext['direction'] }}</strong></div>
                <div><span>Status Setelah Paid</span><strong>{{ $paymentContext['paid_status'] }}</strong></div>
                <div><span>Status</span><strong>{{ $record->status }}</strong></div>
                <div><span>Amount</span><strong>Rp {{ number_format($record->amount, 0, ',', '.') }}</strong></div>
            </div>

            <form method="post" action="{{ route('payments.store', $record) }}" class="payment-checkout-form" data-payment-checkout>
                @csrf
                <div>
                    <h2 class="gateway-heading">Pilih Gateway</h2>
                    <div class="gateway-picker">
                        <label class="gateway-option">
                            <input type="radio" name="provider" value="midtrans" data-configured="{{ $gateways['midtrans']['configured'] ? '1' : '0' }}" @checked($selectedProvider === 'midtrans')>
                            <span class="gateway-card">
                                <span class="gateway-name"><x-icon name="credit-card" />Midtrans Snap</span>
                                <span class="status {{ $gateways['midtrans']['configured'] ? 'success' : 'warning' }}">{{ $gateways['midtrans']['configured'] ? 'SIAP' : 'BELUM SETUP' }}</span>
                                <small>{{ $gateways['midtrans']['notification_url'] }}</small>
                            </span>
                        </label>
                        <label class="gateway-option">
                            <input type="radio" name="provider" value="paylabs" data-configured="{{ $gateways['paylabs']['configured'] ? '1' : '0' }}" @checked($selectedProvider === 'paylabs')>
                            <span class="gateway-card">
                                <span class="gateway-name"><x-icon name="wallet-cards" />Paylabs Checkout</span>
                                <span class="status {{ $gateways['paylabs']['configured'] ? 'success' : 'warning' }}">{{ $gateways['paylabs']['configured'] ? 'SIAP' : 'BELUM SETUP' }}</span>
                                <small>{{ $gateways['paylabs']['notification_url'] }}</small>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="form-grid gateway-fields" data-paylabs-fields @if($selectedProvider !== 'paylabs') hidden @endif>
                    <label class="full">Nomor telepon pelanggan untuk Paylabs
                        <input name="paylabs_phone_number" value="{{ old('paylabs_phone_number', $paylabsPhoneNumber) }}" placeholder="081234567890" data-paylabs-phone @required($selectedProvider === 'paylabs')>
                        @error('paylabs_phone_number')<small class="field-error">{{ $message }}</small>@enderror
                    </label>
                </div>

                <div class="form-actions payment-actions">
                    <a class="ghost-button" href="{{ route('records.index', $record->module) }}"><x-icon name="arrow-left" />Kembali</a>
                    <button class="primary-button" type="submit" data-payment-submit>
                        <x-icon name="credit-card" /><span data-payment-label>Bayar via {{ $selectedProvider === 'paylabs' ? 'Paylabs' : 'Midtrans' }}</span>
                    </button>
                </div>
            </form>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2>Riwayat Record</h2>
                <span>{{ $payments->count() }} payment</span>
            </div>
            <div class="mini-list">
                @forelse($payments as $payment)
                    <div class="mini-item">
                        <div>
                            <strong>{{ $payment->order_id }}</strong>
                            <span>{{ strtoupper($payment->provider) }} - Rp {{ number_format($payment->amount, 0, ',', '.') }} - {{ strtoupper($payment->status) }}</span>
                        </div>
                        <a class="icon-link" href="{{ route('payments.show', $payment) }}"><x-icon name="eye" />Detail</a>
                    </div>
                @empty
                    <div class="empty-state">Belum ada payment untuk record ini.</div>
                @endforelse
            </div>
        </section>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.querySelector('[data-payment-checkout]');
            const details = form?.querySelector('[data-paylabs-fields]');
            const phone = form?.querySelector('[data-paylabs-phone]');
            const submit = form?.querySelector('[data-payment-submit]');
            const label = form?.querySelector('[data-payment-label]');

            function updateGateway() {
                const chosen = form?.querySelector('input[name="provider"]:checked');

                if (!chosen) {
                    return;
                }

                const paylabs = chosen.value === 'paylabs';
                details.hidden = !paylabs;
                phone.required = paylabs;
                submit.disabled = chosen.dataset.configured !== '1';
                label.textContent = 'Bayar via ' + (paylabs ? 'Paylabs' : 'Midtrans');
            }

            form?.querySelectorAll('input[name="provider"]').forEach(function (input) {
                input.addEventListener('change', updateGateway);
            });
            updateGateway();
        });
    </script>
@endsection
