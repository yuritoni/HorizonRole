@php($fcm = app(\App\Services\FirebaseCloudMessagingService::class))

@if($fcm->hasWebPushConfig())
    <script type="module">
        import { initializeApp } from 'https://www.gstatic.com/firebasejs/10.13.2/firebase-app.js';
        import { getMessaging, getToken, isSupported, onMessage } from 'https://www.gstatic.com/firebasejs/10.13.2/firebase-messaging.js';

        const pushButton = document.getElementById('enablePushButton');
        const pushButtonLabel = pushButton?.querySelector('[data-push-label]');
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
        const firebaseConfig = @json($fcm->publicConfig());
        const vapidKey = @json(config('services.firebase.vapid_key'));

        function setPushButton(text, disabled = false) {
            if (! pushButton) {
                return;
            }

            if (pushButtonLabel) {
                pushButtonLabel.textContent = text;
            } else {
                pushButton.textContent = text;
            }
            pushButton.disabled = disabled;
        }

        function incrementNotificationBadges() {
            ['notificationBadge', 'notificationNavBadge'].forEach((id) => {
                const badge = document.getElementById(id);

                if (badge) {
                    badge.textContent = String((parseInt(badge.textContent || '0', 10) || 0) + 1);
                }
            });
        }

        function showNotificationToast(payload) {
            let stack = document.getElementById('toastStack');

            if (! stack) {
                stack = document.createElement('div');
                stack.id = 'toastStack';
                stack.className = 'toast-stack';
                document.body.appendChild(stack);
            }

            const toast = document.createElement('a');
            toast.className = 'toast';
            toast.href = payload.data?.action_url || '{{ route('notifications.index') }}';
            const title = document.createElement('strong');
            const body = document.createElement('span');
            title.textContent = payload.notification?.title || 'Notifikasi ERP';
            body.textContent = payload.notification?.body || '';
            toast.append(title, body);
            stack.appendChild(toast);
            window.setTimeout(() => toast.remove(), 7000);
        }

        async function registerFirebaseMessaging() {
            if (! await isSupported()) {
                setPushButton('Push tidak didukung', true);
                return;
            }

            if (! ('Notification' in window)) {
                setPushButton('Push tidak didukung', true);
                return;
            }

            if (Notification.permission === 'denied') {
                setPushButton('Push diblokir', true);
                return;
            }

            const app = initializeApp(firebaseConfig);
            const messaging = getMessaging(app);

            onMessage(messaging, (payload) => {
                incrementNotificationBadges();
                showNotificationToast(payload);
            });

            if (Notification.permission !== 'granted') {
                setPushButton('Izinkan Push');
                return;
            }

            const registration = await navigator.serviceWorker.register('/firebase-messaging-sw.js');
            const token = await getToken(messaging, {
                vapidKey,
                serviceWorkerRegistration: registration,
            });

            if (! token) {
                setPushButton('Token belum tersedia');
                return;
            }

            await fetch('{{ route('notifications.fcm-token.store') }}', {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({
                    token,
                    platform: 'web',
                    device_name: navigator.platform || 'Browser',
                }),
            });

            setPushButton('Push Aktif', true);
        }

        pushButton?.addEventListener('click', async () => {
            try {
                setPushButton('Mengaktifkan...', true);
                await Notification.requestPermission();
                setPushButton('Aktifkan Push');
                await registerFirebaseMessaging();
            } catch (error) {
                console.error(error);
                setPushButton('Gagal Push');
            }
        });

        registerFirebaseMessaging().catch(() => setPushButton('Aktifkan Push'));
    </script>
@else
    <script>
        const pushButton = document.getElementById('enablePushButton');
        const pushButtonLabel = pushButton?.querySelector('[data-push-label]');

        if (pushButton) {
            if (pushButtonLabel) {
                pushButtonLabel.textContent = 'FCM belum diset';
            } else {
                pushButton.textContent = 'FCM belum diset';
            }
            pushButton.disabled = true;
        }
    </script>
@endif
