<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class FirebaseCloudMessagingService
{
    public function isConfigured(): bool
    {
        $credentials = $this->credentials();

        return (bool) ($credentials['project_id'] ?? null)
            && (bool) ($credentials['client_email'] ?? null)
            && (bool) ($credentials['private_key'] ?? null);
    }

    public function hasWebPushConfig(): bool
    {
        $config = $this->publicConfig();

        return (bool) $config['apiKey']
            && (bool) $config['projectId']
            && (bool) $config['messagingSenderId']
            && (bool) $config['appId']
            && (bool) config('services.firebase.vapid_key');
    }

    public function publicConfig(): array
    {
        return [
            'apiKey' => config('services.firebase.api_key'),
            'authDomain' => config('services.firebase.auth_domain'),
            'projectId' => config('services.firebase.project_id'),
            'messagingSenderId' => config('services.firebase.messaging_sender_id'),
            'appId' => config('services.firebase.app_id'),
        ];
    }

    public function sendToToken(string $token, array $notification, array $data = [], ?string $actionUrl = null): array
    {
        $credentials = $this->credentials();

        if (! $this->isConfigured()) {
            throw new RuntimeException('Firebase service account belum dikonfigurasi.');
        }

        $message = [
            'token' => $token,
            'notification' => [
                'title' => (string) ($notification['title'] ?? config('app.name')),
                'body' => (string) ($notification['body'] ?? ''),
            ],
            'data' => $this->stringifyData($data),
        ];

        if ($actionUrl) {
            $message['webpush'] = [
                'fcm_options' => [
                    'link' => $actionUrl,
                ],
                'notification' => [
                    'icon' => asset('favicon.ico'),
                ],
            ];
        }

        $response = Http::withToken($this->accessToken())
            ->acceptJson()
            ->post("https://fcm.googleapis.com/v1/projects/{$credentials['project_id']}/messages:send", [
                'message' => $message,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException($response->body() ?: 'Gagal mengirim FCM notification.');
        }

        return $response->json();
    }

    private function accessToken(): string
    {
        $credentials = $this->credentials();
        $cacheKey = 'firebase_fcm_access_token_'.md5(($credentials['project_id'] ?? '').($credentials['client_email'] ?? ''));

        return Cache::remember($cacheKey, 3300, function () use ($credentials) {
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $this->makeJwt($credentials),
            ]);

            if (! $response->successful()) {
                throw new RuntimeException($response->body() ?: 'Gagal mengambil OAuth token Firebase.');
            }

            return (string) $response->json('access_token');
        });
    }

    private function makeJwt(array $credentials): string
    {
        $issuedAt = time();
        $header = ['alg' => 'RS256', 'typ' => 'JWT'];
        $claims = [
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $issuedAt,
            'exp' => $issuedAt + 3600,
        ];

        $unsigned = $this->base64UrlEncode(json_encode($header)).'.'.$this->base64UrlEncode(json_encode($claims));

        if (! openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Gagal menandatangani JWT Firebase. Periksa private key service account.');
        }

        return $unsigned.'.'.$this->base64UrlEncode($signature);
    }

    private function credentials(): array
    {
        $credentials = [
            'project_id' => config('services.firebase.project_id'),
            'client_email' => config('services.firebase.client_email'),
            'private_key' => $this->normalizePrivateKey(config('services.firebase.private_key')),
        ];

        $path = config('services.firebase.credentials_path');

        if ($path) {
            $resolvedPath = $this->resolvePath($path);

            if (is_file($resolvedPath)) {
                $fileCredentials = json_decode((string) file_get_contents($resolvedPath), true) ?: [];
                $credentials = array_filter($credentials) + Arr::only($fileCredentials, ['project_id', 'client_email', 'private_key']);
                $credentials['private_key'] = $this->normalizePrivateKey($credentials['private_key'] ?? null);
            }
        }

        return $credentials;
    }

    private function normalizePrivateKey(?string $privateKey): ?string
    {
        if (! $privateKey) {
            return null;
        }

        return str_replace('\\n', "\n", $privateKey);
    }

    private function stringifyData(array $data): array
    {
        return collect($data)
            ->filter(fn ($value) => $value !== null)
            ->map(fn ($value) => is_scalar($value) ? (string) $value : json_encode($value))
            ->toArray();
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function resolvePath(string $path): string
    {
        if (Str::startsWith($path, ['/', '\\']) || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path)) {
            return $path;
        }

        return base_path($path);
    }
}
