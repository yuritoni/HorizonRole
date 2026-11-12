<?php

namespace App\Http\Controllers;

use App\Services\FirebaseCloudMessagingService;

class FirebaseMessagingServiceWorkerController extends Controller
{
    public function __invoke(FirebaseCloudMessagingService $fcm)
    {
        if (! $fcm->hasWebPushConfig()) {
            return $this->javascript('self.addEventListener("install", function () { self.skipWaiting(); });');
        }

        $config = json_encode($fcm->publicConfig(), JSON_UNESCAPED_SLASHES);

        return $this->javascript(<<<JS
importScripts('https://www.gstatic.com/firebasejs/10.13.2/firebase-app-compat.js');
importScripts('https://www.gstatic.com/firebasejs/10.13.2/firebase-messaging-compat.js');

firebase.initializeApp({$config});

const messaging = firebase.messaging();

messaging.onBackgroundMessage(function (payload) {
    const title = payload.notification?.title || payload.data?.title || 'Horizon ERP';
    const options = {
        body: payload.notification?.body || payload.data?.body || '',
        icon: '/favicon.ico',
        badge: '/favicon.ico',
        data: {
            url: payload.fcmOptions?.link || payload.data?.action_url || '/notifications'
        }
    };

    self.registration.showNotification(title, options);
});

self.addEventListener('notificationclick', function (event) {
    event.notification.close();
    const targetUrl = event.notification.data?.url || '/notifications';

    event.waitUntil(clients.matchAll({ type: 'window', includeUncontrolled: true }).then(function (clientList) {
        for (const client of clientList) {
            if ('focus' in client && client.url === targetUrl) {
                return client.focus();
            }
        }

        if (clients.openWindow) {
            return clients.openWindow(targetUrl);
        }
    }));
});
JS);
    }

    private function javascript(string $content)
    {
        return response($content, 200)
            ->header('Content-Type', 'application/javascript')
            ->header('Service-Worker-Allowed', '/')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
