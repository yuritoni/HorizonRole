@extends('layouts.app')

@section('title', 'Payment '.$payment->order_id.' - '.config('app.name'))

@section('content')
    @php($paymentContext = $payment->record ? \App\Support\PaymentRules::context($payment->record) : null)
    <header class="page-header">
        <div>
            <p class="eyebrow">{{ strtoupper($payment->provider) }} Payment</p>
            <h1>{{ $payment->order_id }}</h1>
            <p>Status pembayaran untuk {{ $payment->record?->code }} - {{ $payment->record?->title }}.</p>
        </div>
        <div class="role-card">
            <span>Status</span>
            <strong>{{ strtoupper($payment->status) }}</strong>
        </div>
    </header>

    <section class="panel form-panel">
        <div class="payment-summary">
            <div><span>Provider</span><strong>{{ strtoupper($payment->provider) }}</strong></div>
            <div><span>Modul</span><strong>{{ $paymentContext['module_label'] ?? '-' }}</strong></div>
            <div><span>Proses</span><strong>{{ $paymentContext['label'] ?? '-' }}</strong></div>
            <div><span>Status Setelah Paid</span><strong>{{ $paymentContext['paid_status'] ?? '-' }}</strong></div>
            <div><span>Amount</span><strong>Rp {{ number_format($payment->amount, 0, ',', '.') }}</strong></div>
            <div><span>Payment Type</span><strong>{{ $payment->payment_type ?: '-' }}</strong></div>
            <div><span>Transaction ID</span><strong>{{ $payment->transaction_id ?: '-' }}</strong></div>
            <div><span>Fraud Status</span><strong>{{ $payment->fraud_status ?: '-' }}</strong></div>
            <div><span>Paid At</span><strong>{{ optional($payment->paid_at)->format('d M Y H:i') ?: '-' }}</strong></div>
        </div>

        <div class="form-actions payment-actions">
            <a class="ghost-button" href="{{ route('payments.index') }}"><x-icon name="history" />Riwayat Payment</a>
            @if($payment->redirect_url && ! $payment->isPaid())
                <a class="primary-button" href="{{ $payment->redirect_url }}"><x-icon name="external-link" />Lanjutkan Pembayaran</a>
            @endif
        </div>
    </section>
@endsection
