<?php

namespace Tests\Unit;

use App\Models\ErpRecord;
use App\Models\Payment;
use App\Models\User;
use App\Services\PaylabsService;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaylabsServiceTest extends TestCase
{
    private string $merchantPrivateKey;

    private string $merchantPublicKey;

    private string $paylabsPrivateKey;

    private string $paylabsPublicKey;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->merchantPrivateKey, $this->merchantPublicKey] = $this->keyPair();
        [$this->paylabsPrivateKey, $this->paylabsPublicKey] = $this->keyPair();

        config([
            'services.paylabs.base_url' => 'https://sandbox.paylabs.test',
            'services.paylabs.merchant_id' => 'MRC001',
            'services.paylabs.private_key' => $this->merchantPrivateKey,
            'services.paylabs.private_key_path' => null,
            'services.paylabs.public_key' => $this->paylabsPublicKey,
            'services.paylabs.public_key_path' => null,
        ]);
    }

    public function test_it_creates_a_signed_paylabs_hosted_checkout_transaction(): void
    {
        Http::fake(function (ClientRequest $request) {
            $payload = $request->data();
            $timestamp = $request->header('X-TIMESTAMP')[0];
            $signature = $request->header('X-SIGNATURE')[0];

            $this->assertSame('/payment/v2.3/h5/createLink', $request->toPsrRequest()->getUri()->getPath());
            $this->assertSame('MRC001', $request->header('X-PARTNER-ID')[0]);
            $this->assertTrue($this->verify($payload, '/payment/v2.3/h5/createLink', $timestamp, $signature, $this->merchantPublicKey));

            $responsePayload = [
                'merchantId' => 'MRC001',
                'requestId' => $payload['requestId'],
                'merchantTradeNo' => $payload['merchantTradeNo'],
                'errCode' => '0',
                'url' => 'https://paylabs.test/checkout/payment',
            ];
            $responseTimestamp = '2026-05-25T15:00:00.000+07:00';

            return Http::response($responsePayload, 200, [
                'X-TIMESTAMP' => $responseTimestamp,
                'X-SIGNATURE' => $this->sign($responsePayload, '/payment/v2.3/h5/createLink', $responseTimestamp, $this->paylabsPrivateKey),
                'X-PARTNER-ID' => 'MRC001',
                'X-REQUEST-ID' => $payload['requestId'],
            ]);
        });

        $payment = new Payment(['order_id' => 'HZP-1-260525120000-TEST', 'amount' => 10000]);
        $payment->id = 1;
        $record = new ErpRecord(['module' => 'finance', 'category' => 'AR Invoice', 'title' => 'Invoice Demo']);
        $user = new User(['name' => 'Finance User', 'email' => 'finance@example.test']);

        $result = app(PaylabsService::class)->createCheckoutTransaction($payment, $record, $user, '0812 3456 7890');

        $this->assertSame('https://paylabs.test/checkout/payment', $result['redirect_url']);
        $this->assertSame('PAYLABS_H5', $result['payment_type']);
        $this->assertSame('081234567890', $result['request']['phoneNumber']);
    }

    public function test_it_verifies_notification_and_returns_a_signed_acknowledgement(): void
    {
        $payload = [
            'merchantId' => 'MRC001',
            'requestId' => 'NOTIFY-001',
            'paymentType' => 'QRIS',
            'amount' => '10000.00',
            'merchantTradeNo' => 'HZP-1-260525120000-TEST',
            'platformTradeNo' => 'PLATFORM-001',
            'status' => '02',
        ];
        $timestamp = '2026-05-25T15:10:00.000+07:00';
        $request = Request::create('/api/paylabs/notification', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json;charset=utf-8',
            'HTTP_X_TIMESTAMP' => $timestamp,
            'HTTP_X_SIGNATURE' => $this->sign($payload, '/api/paylabs/notification', $timestamp, $this->paylabsPrivateKey),
            'HTTP_X_PARTNER_ID' => 'MRC001',
            'HTTP_X_REQUEST_ID' => 'NOTIFY-001',
        ], json_encode($payload));

        $service = app(PaylabsService::class);

        $this->assertTrue($service->isValidNotification($request, $payload));

        $response = $service->notificationResponse($payload);
        $responsePayload = json_decode($response->getContent(), true);

        $this->assertSame('0', $responsePayload['errCode']);
        $this->assertTrue($this->verify(
            $responsePayload,
            '/api/paylabs/notification',
            $response->headers->get('X-TIMESTAMP'),
            $response->headers->get('X-SIGNATURE'),
            $this->merchantPublicKey
        ));
    }

    private function keyPair(): array
    {
        $options = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $xamppConfig = dirname(PHP_BINARY).DIRECTORY_SEPARATOR.'extras'.DIRECTORY_SEPARATOR.'ssl'.DIRECTORY_SEPARATOR.'openssl.cnf';

        if (is_file($xamppConfig)) {
            $options['config'] = $xamppConfig;
        }

        $key = openssl_pkey_new($options);
        $this->assertNotFalse($key, 'RSA test key could not be generated by OpenSSL.');
        $this->assertTrue(openssl_pkey_export($key, $privateKey, null, $options), 'RSA test key could not be exported by OpenSSL.');
        $publicKey = openssl_pkey_get_details($key)['key'];

        return [$privateKey, $publicKey];
    }

    private function sign(array $payload, string $path, string $timestamp, string $privateKey): string
    {
        openssl_sign($this->content($payload, $path, $timestamp), $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return base64_encode($signature);
    }

    private function verify(array $payload, string $path, string $timestamp, string $signature, string $publicKey): bool
    {
        return openssl_verify(
            $this->content($payload, $path, $timestamp),
            base64_decode($signature, true),
            $publicKey,
            OPENSSL_ALGO_SHA256
        ) === 1;
    }

    private function content(array $payload, string $path, string $timestamp): string
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);

        return 'POST:'.$path.':'.strtolower(hash('sha256', $body)).':'.$timestamp;
    }
}
