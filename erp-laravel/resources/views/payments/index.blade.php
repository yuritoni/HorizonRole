@extends('layouts.app')

@section('title', 'Pembayaran - '.config('app.name'))

@section('content')
    <header class="page-header">
        <div>
            <p class="eyebrow">Payment Gateway</p>
            <h1>Pembayaran</h1>
            <p>Monitoring transaksi Midtrans dan Paylabs satu pintu dari modul Keuangan untuk AR Invoice dan AP Bill yang terhubung ke modul lain.</p>
        </div>
        <div class="role-card">
            <span>Total transaksi</span>
            <strong>{{ $payments->total() }}</strong>
        </div>
    </header>

    <section class="panel">
        <div class="panel-header">
            <h2>Riwayat Payment</h2>
            <span>{{ $payments->total() }} transaksi</span>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Record</th>
                        <th>Modul</th>
                        <th>Proses</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Provider</th>
                        <th>Metode</th>
                        <th>Dibuat</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($payments as $payment)
                        @php($paymentContext = $payment->record ? \App\Support\PaymentRules::context($payment->record) : null)
                        <tr>
                            <td>{{ $payment->order_id }}</td>
                            <td>{{ $payment->record?->code }} - {{ $payment->record?->title }}</td>
                            <td>{{ $paymentContext['module_label'] ?? '-' }}</td>
                            <td>{{ $paymentContext['label'] ?? '-' }}</td>
                            <td>Rp {{ number_format($payment->amount, 0, ',', '.') }}</td>
                            <td><span class="status {{ $payment->status !== 'paid' ? 'warning' : '' }}">{{ strtoupper($payment->status) }}</span></td>
                            <td>{{ strtoupper($payment->provider) }}</td>
                            <td>{{ $payment->payment_type ?: '-' }}</td>
                            <td>{{ $payment->created_at->format('d M Y H:i') }}</td>
                            <td><a class="icon-link" href="{{ route('payments.show', $payment) }}"><x-icon name="eye" />Detail</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="empty-state">Belum ada transaksi pembayaran.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="pagination-wrap">{{ $payments->links() }}</div>
    </section>
@endsection
