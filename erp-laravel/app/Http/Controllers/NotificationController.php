<?php

namespace App\Http\Controllers;

use App\Models\ErpNotification;
use App\Models\FcmDeviceToken;
use App\Services\FirebaseCloudMessagingService;
use App\Support\ErpViewData;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request, FirebaseCloudMessagingService $fcm)
    {
        $query = $request->user()
            ->erpNotifications()
            ->with(['actor', 'record', 'payment'])
            ->latest();

        if ($request->get('status') === 'unread') {
            $query->whereNull('read_at');
        }

        return view('notifications.index', array_merge(ErpViewData::layout(), [
            'notifications' => $query->paginate(14)->withQueryString(),
            'unreadCount' => $request->user()->unreadErpNotifications()->count(),
            'fcmTokens' => $request->user()->fcmDeviceTokens()->latest('last_seen_at')->get(),
            'isFcmConfigured' => $fcm->isConfigured(),
            'hasWebPushConfig' => $fcm->hasWebPushConfig(),
        ]));
    }

    public function open(Request $request, ErpNotification $notification)
    {
        $this->authorizeOwner($request, $notification);
        $notification->markAsRead();

        return redirect()->to($notification->action_url ?: route('notifications.index'));
    }

    public function markAsRead(Request $request, ErpNotification $notification)
    {
        $this->authorizeOwner($request, $notification);
        $notification->markAsRead();

        return back()->with('success', 'Notifikasi sudah ditandai dibaca.');
    }

    public function markAllAsRead(Request $request)
    {
        $request->user()
            ->erpNotifications()
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return back()->with('success', 'Semua notifikasi ditandai dibaca.');
    }

    public function storeFcmToken(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['nullable', 'string', 'max:40'],
            'device_name' => ['nullable', 'string', 'max:120'],
        ]);

        $tokenHash = hash('sha256', $data['token']);

        FcmDeviceToken::updateOrCreate(
            ['token_hash' => $tokenHash],
            [
                'user_id' => $request->user()->id,
                'token' => $data['token'],
                'platform' => $data['platform'] ?? 'web',
                'device_name' => $data['device_name'] ?? 'Browser',
                'user_agent' => $request->userAgent(),
                'last_seen_at' => now(),
                'revoked_at' => null,
            ]
        );

        return response()->json(['message' => 'FCM token tersimpan.']);
    }

    public function destroyFcmToken(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
        ]);

        $request->user()
            ->fcmDeviceTokens()
            ->where('token_hash', hash('sha256', $data['token']))
            ->update(['revoked_at' => now()]);

        return response()->json(['message' => 'FCM token dinonaktifkan.']);
    }

    private function authorizeOwner(Request $request, ErpNotification $notification): void
    {
        abort_unless($notification->user_id === $request->user()->id, 404);
    }
}
