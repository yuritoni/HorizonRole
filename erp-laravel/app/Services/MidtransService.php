<?php

namespace App\Services;

use App\Models\ErpRecord;
use App\Models\Payment;
use App\Models\User;
use App\Support\PaymentRules;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class MidtransService
{
    public function createSnapTransaction(Payment $payment, ErpRecord $record, User $user): array
    {
        $serverKey = $this->serverKey();
        $context = PaymentRules::context($record);

        $payload = [
            'transaction_details' => [
                'order_id' => $payment->order_id,
                'gross_amount' => (int) $payment->amount,
            ],
            'customer_details' => [
                'first_name' => Str::limit($user->name, 40, ''),
                'email' => $user->email,
            ],
            'item_details' => [
                [
                    'id' => $record->code,
                    'price' => (int) $payment->amount,
                    'quantity' => 1,
                    'name' => Str::limit($context['label'].' - '.$record->title, 48, ''),
                ],
            ],
            'callbacks' => [
                'finish' => route('payments.show', $payment),
            ],
            'custom_field1' => $record->module,
            'custom_field2' => $record->code,
            'custom_field3' => Str::limit($context['label'], 255, ''),
        ];

        $response = Http::withHeaders([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic '.base64_encode($serverKey.':'),
        ])->post($this->snapEndpoint(), $payload);

        if (! $response->successful()) {
            throw new RuntimeException($response->json('error_messages.0') ?: 'Gagal membuat transaksi Midtrans.');
        }

        return $response->json();
    }

    public function verifySignature(array $payload): bool
    {
        $serverKey = $this->serverKey();
        $signature = hash('sha512', ($payload['order_id'] ?? '').($payload['status_code'] ?? '').($payload['gross_amount'] ?? '').$serverKey);

        return hash_equals($signature, (string) ($payload['signature_key'] ?? ''));
    }

    public function isConfigured(): bool
    {
        return (bool) config('services.midtrans.server_key');
    }

    public function notificationUrl(): string
    {
        return url('/api/midtrans/notification');
    }

    private function serverKey(): string
    {
        $serverKey = (string) config('services.midtrans.server_key');

        if ($serverKey === '') {
            throw new RuntimeException('MIDTRANS_SERVER_KEY belum diisi di file .env.');
        }

        return $serverKey;
    }

    private function snapEndpoint(): string
    {
        return config('services.midtrans.is_production')
            ? 'https://app.midtrans.com/snap/v1/transactions'
            : 'https://app.sandbox.midtrans.com/snap/v1/transactions';
    }
}
