<?php

namespace App\Services;

use App\Models\ErpNotification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class ErpNotificationService
{
    public function __construct(private FirebaseCloudMessagingService $fcm)
    {
    }

    public function notifyAdmins(array $payload, ?User $actor = null): Collection
    {
        return $this->notifyRoles(['admin'], $payload, $actor);
    }

    public function notifyRoles(array $roles, array $payload, ?User $actor = null): Collection
    {
        $users = User::whereIn('role', $roles)
            ->where('account_status', 'active')
            ->get();

        return $this->notifyUsers($users, $payload, $actor);
    }

    public function notifyPrivilege(string $privilegeKey, array $payload, ?User $actor = null): Collection
    {
        $users = User::where('account_status', 'active')
            ->get()
            ->filter(fn (User $user) => $user->hasPrivilege($privilegeKey));

        return $this->notifyUsers($users, $payload, $actor);
    }

    public function notifyUsers(iterable $users, array $payload, ?User $actor = null): Collection
    {
        return collect($users)
            ->filter(fn ($user) => $user instanceof User)
            ->unique('id')
            ->values()
            ->map(function (User $user) use ($payload, $actor) {
                $notification = ErpNotification::create([
                    'user_id' => $user->id,
                    'actor_id' => $actor?->id,
                    'subject_user_id' => $payload['subject_user_id'] ?? null,
                    'erp_record_id' => $payload['erp_record_id'] ?? null,
                    'payment_id' => $payload['payment_id'] ?? null,
                    'type' => $payload['type'] ?? 'system.info',
                    'severity' => $payload['severity'] ?? 'info',
                    'module' => $payload['module'] ?? null,
                    'title' => Str::limit((string) ($payload['title'] ?? config('app.name')), 180, ''),
                    'body' => Str::limit((string) ($payload['body'] ?? ''), 1200, ''),
                    'action_url' => $payload['action_url'] ?? null,
                    'data' => $payload['data'] ?? [],
                ]);

                $this->sendPush($notification);

                return $notification;
            });
    }

    private function sendPush(ErpNotification $notification): void
    {
        if (! $this->fcm->isConfigured()) {
            $notification->update(['fcm_status' => 'not_configured']);

            return;
        }

        $tokens = $notification->user
            ->fcmDeviceTokens()
            ->active()
            ->get();

        if ($tokens->isEmpty()) {
            $notification->update(['fcm_status' => 'no_token']);

            return;
        }

        $responses = [];
        $hasSuccess = false;

        foreach ($tokens as $token) {
            try {
                $responses[] = [
                    'token_id' => $token->id,
                    'response' => $this->fcm->sendToToken(
                        $token->token,
                        [
                            'title' => $notification->title,
                            'body' => $notification->body,
                        ],
                        $this->payloadData($notification),
                        $this->absoluteActionUrl($notification->action_url)
                    ),
                ];
                $hasSuccess = true;
            } catch (RuntimeException $exception) {
                $message = $exception->getMessage();
                $responses[] = [
                    'token_id' => $token->id,
                    'error' => Str::limit($message, 500, ''),
                ];

                if (Str::contains($message, ['UNREGISTERED', 'registration-token-not-registered'])) {
                    $token->update(['revoked_at' => now()]);
                }

                Log::warning('FCM notification failed', [
                    'notification_id' => $notification->id,
                    'token_id' => $token->id,
                    'error' => $message,
                ]);
            }
        }

        $notification->update([
            'fcm_sent_at' => $hasSuccess ? now() : null,
            'fcm_status' => $hasSuccess ? 'sent' : 'failed',
            'fcm_response' => $responses,
        ]);
    }

    private function payloadData(ErpNotification $notification): array
    {
        return [
            'notification_id' => $notification->id,
            'type' => $notification->type,
            'severity' => $notification->severity,
            'module' => $notification->module,
            'record_id' => $notification->erp_record_id,
            'payment_id' => $notification->payment_id,
            'action_url' => $this->absoluteActionUrl($notification->action_url),
        ];
    }

    private function absoluteActionUrl(?string $actionUrl): ?string
    {
        if (! $actionUrl) {
            return null;
        }

        if (Str::startsWith($actionUrl, ['http://', 'https://'])) {
            return $actionUrl;
        }

        return url($actionUrl);
    }
}
