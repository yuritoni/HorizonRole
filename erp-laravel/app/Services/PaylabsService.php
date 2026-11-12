<?php

namespace App\Services;

use App\Models\ErpRecord;
use App\Models\Payment;
use App\Models\User;
use App\Support\PaymentRules;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PaylabsService
{
    private const CHECKOUT_PATH = '/payment/v2.3/h5/createLink';

    public function createCheckoutTransaction(Payment $payment, ErpRecord $record, User $user, string $phoneNumber): array
    {
        $phoneNumber = preg_replace('/[^0-9+]/', '', $phoneNumber) ?: '';

        if ($phoneNumber === '') {
            throw new RuntimeException('Nomor telepon pelanggan wajib diisi untuk pembayaran Paylabs.');
        }

        $context = PaymentRules::context($record);
        $requestId = 'REQ-'.$payment->order_id.'-'.Str::upper(Str::random(8));
        $payload = [
            'requestId' => $requestId,
            'merchantId' => $this->merchantId(),
            'merchantTradeNo' => $payment->order_id,
            'amount' => number_format((float) $payment->amount, 2, '.', ''),
            'payer' => Str::limit($user->name, 60, ''),
            'phoneNumber' => $phoneNumber,
            'productName' => Str::limit($context['label'].' - '.$record->title, 100, ''),
            'notifyUrl' => $this->notificationUrl(),
            'redirectUrl' => route('payments.show', $payment),
            'lang' => 'id',
        ];

        $response = $this->post(self::CHECKOUT_PATH, $payload);
        $data = $response->json() ?: [];

        if (! $response->successful() || (string) ($data['errCode'] ?? '') !== '0') {
            throw new RuntimeException($data['errCodeDes'] ?? 'Gagal membuat transaksi Paylabs.');
        }

        if (($data['merchantId'] ?? null) !== $this->merchantId()
            || ($data['requestId'] ?? null) !== $requestId
            || (! empty($data['merchantTradeNo']) && $data['merchantTradeNo'] !== $payment->order_id)) {
            throw new RuntimeException('Respons Paylabs tidak sesuai dengan transaksi yang diminta.');
        }

        if (empty($data['url'])) {
            throw new RuntimeException('Paylabs tidak mengembalikan URL pembayaran.');
        }

        return [
            'redirect_url' => $data['url'],
            'payment_type' => 'PAYLABS_H5',
            'transaction_id' => $data['platformTradeNo'] ?? null,
            'request' => $payload,
            'response' => $data,
        ];
    }

    public function isValidNotification(Request $request, array $payload): bool
    {
        if (($payload['merchantId'] ?? null) !== $this->merchantId()
            || $request->header('X-PARTNER-ID') !== $this->merchantId()
            || $request->header('X-REQUEST-ID') !== ($payload['requestId'] ?? null)) {
            return false;
        }

        return $this->verifyPayload(
            $payload,
            $this->notificationPath(),
            (string) $request->header('X-TIMESTAMP'),
            (string) $request->header('X-SIGNATURE')
        );
    }

    public function notificationResponse(array $notification): JsonResponse
    {
        $timestamp = $this->timestamp();
        $payload = [
            'merchantId' => $this->merchantId(),
            'requestId' => (string) ($notification['requestId'] ?? ''),
            'errCode' => '0',
        ];

        return response()->json($payload, 200, [
            'Content-Type' => 'application/json;charset=utf-8',
            'X-TIMESTAMP' => $timestamp,
            'X-SIGNATURE' => $this->signature($payload, $this->notificationPath(), $timestamp),
            'X-PARTNER-ID' => $this->merchantId(),
            'X-REQUEST-ID' => $payload['requestId'],
        ]);
    }

    public function isConfigured(): bool
    {
        return (bool) (
            config('services.paylabs.base_url')
            && config('services.paylabs.merchant_id')
            && $this->configuredKey('private')
            && $this->configuredKey('public')
        );
    }

    public function notificationUrl(): string
    {
        return url('/api/paylabs/notification');
    }

    private function post(string $path, array $payload): Response
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Konfigurasi Paylabs belum lengkap. Isi base URL, merchant ID, private key, dan public key Paylabs.');
        }

        $timestamp = $this->timestamp();
        $requestId = (string) $payload['requestId'];
        $body = $this->jsonBody($payload);

        try {
            $response = Http::withHeaders([
                'Accept' => 'application/json',
                'Content-Type' => 'application/json;charset=utf-8',
                'X-TIMESTAMP' => $timestamp,
                'X-SIGNATURE' => $this->signature($payload, $path, $timestamp),
                'X-PARTNER-ID' => $this->merchantId(),
                'X-REQUEST-ID' => $requestId,
            ])->withBody($body, 'application/json;charset=utf-8')
                ->post($this->rootUrl().$path);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Tidak dapat terhubung ke layanan Paylabs.', 0, $exception);
        }

        if ((string) $response->header('X-PARTNER-ID') !== $this->merchantId()
            || (string) $response->header('X-REQUEST-ID') !== $requestId) {
            throw new RuntimeException('Header respons Paylabs tidak sesuai dengan request.');
        }

        if (! $this->verifyPayload(
            $response->json() ?: [],
            $path,
            (string) $response->header('X-TIMESTAMP'),
            (string) $response->header('X-SIGNATURE')
        )) {
            throw new RuntimeException('Signature respons Paylabs tidak valid.');
        }

        return $response;
    }

    private function verifyPayload(array $payload, string $path, string $timestamp, string $signature): bool
    {
        if ($timestamp === '' || $signature === '') {
            return false;
        }

        $decodedSignature = base64_decode($signature, true);
        $publicKey = openssl_pkey_get_public($this->keyMaterial('public'));

        if ($decodedSignature === false || $publicKey === false) {
            return false;
        }

        return openssl_verify(
            $this->signatureContent($payload, $path, $timestamp),
            $decodedSignature,
            $publicKey,
            OPENSSL_ALGO_SHA256
        ) === 1;
    }

    private function signature(array $payload, string $path, string $timestamp): string
    {
        $privateKey = openssl_pkey_get_private($this->keyMaterial('private'));

        if ($privateKey === false) {
            throw new RuntimeException('Private key Paylabs tidak dapat dibaca.');
        }

        $signed = openssl_sign(
            $this->signatureContent($payload, $path, $timestamp),
            $signature,
            $privateKey,
            OPENSSL_ALGO_SHA256
        );

        if (! $signed) {
            throw new RuntimeException('Gagal membuat signature Paylabs.');
        }

        return base64_encode($signature);
    }

    private function signatureContent(array $payload, string $path, string $timestamp): string
    {
        return 'POST:'.$path.':'.strtolower(hash('sha256', $this->jsonBody($payload))).':'.$timestamp;
    }

    private function jsonBody(array $payload): string
    {
        return json_encode(
            array_filter($payload, fn ($value) => $value !== null),
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR
        );
    }

    private function timestamp(): string
    {
        return now()->format('Y-m-d\TH:i:s.vP');
    }

    private function notificationPath(): string
    {
        return parse_url($this->notificationUrl(), PHP_URL_PATH) ?: '/api/paylabs/notification';
    }

    private function rootUrl(): string
    {
        $url = trim((string) config('services.paylabs.base_url'));

        if ($url === '') {
            throw new RuntimeException('PAYLABS_BASE_URL belum diisi di file .env.');
        }

        return rtrim($url, '/');
    }

    private function merchantId(): string
    {
        $merchantId = trim((string) config('services.paylabs.merchant_id'));

        if ($merchantId === '') {
            throw new RuntimeException('PAYLABS_MERCHANT_ID belum diisi di file .env.');
        }

        return $merchantId;
    }

    private function configuredKey(string $type): bool
    {
        return (bool) (config("services.paylabs.{$type}_key_path") ?: config("services.paylabs.{$type}_key"));
    }

    private function keyMaterial(string $type): string
    {
        $path = trim((string) config("services.paylabs.{$type}_key_path"));

        if ($path !== '') {
            $resolvedPath = str_starts_with($path, DIRECTORY_SEPARATOR) || preg_match('/^[A-Za-z]:[\\\\\/]/', $path)
                ? $path
                : base_path($path);
            $key = @file_get_contents($resolvedPath);

            if ($key === false) {
                throw new RuntimeException('File '.strtoupper($type).' key Paylabs tidak dapat dibaca.');
            }

            return $key;
        }

        $key = (string) config("services.paylabs.{$type}_key");

        if ($key === '') {
            throw new RuntimeException(strtoupper($type).' key Paylabs belum diisi.');
        }

        return str_replace('\n', "\n", $key);
    }
}
