@extends('layouts.app')

@section('title', 'Notifikasi - '.config('app.name'))

@section('actions')
    <form method="post" action="{{ route('notifications.read-all') }}">
        @csrf
        <button class="ghost-button" type="submit"><x-icon name="check-check" />Tandai Semua Dibaca</button>
    </form>
@endsection

@section('content')
    <header class="page-header">
        <div>
            <p class="eyebrow">Notification Center</p>
            <h1>Notifikasi ERP</h1>
            <p>Inbox aktivitas modul, user, workflow, payment Midtrans, dan push Firebase Cloud Messaging.</p>
        </div>
        <div class="role-card">
            <span>Belum dibaca</span>
            <strong>{{ $unreadCount }}</strong>
        </div>
    </header>

    <div class="metrics-grid">
        <article class="metric-card">
            <span>Unread</span>
            <strong>{{ $unreadCount }}</strong>
            <small>Notifikasi yang belum dibaca user login</small>
        </article>
        <article class="metric-card">
            <span>FCM Server</span>
            <strong>{{ $isFcmConfigured ? 'Ready' : 'Setup' }}</strong>
            <small>Service account untuk kirim push server-side</small>
        </article>
        <article class="metric-card">
            <span>Web Push</span>
            <strong>{{ $hasWebPushConfig ? 'Ready' : 'Setup' }}</strong>
            <small>Konfigurasi browser token dan VAPID key</small>
        </article>
        <article class="metric-card">
            <span>Device</span>
            <strong>{{ $fcmTokens->whereNull('revoked_at')->count() }}</strong>
            <small>Browser aktif yang terdaftar untuk akun ini</small>
        </article>
    </div>

    <div class="filter-row">
        <a class="ghost-button" href="{{ route('notifications.index') }}"><x-icon name="inbox" />Semua</a>
        <a class="ghost-button" href="{{ route('notifications.index', ['status' => 'unread']) }}"><x-icon name="mail" />Unread</a>
    </div>

    <div class="dashboard-grid">
        <section class="panel">
            <div class="panel-header">
                <h2>Inbox</h2>
                <span>{{ $notifications->total() }} notifikasi</span>
            </div>
            <div class="notification-list">
                @forelse($notifications as $notification)
                    <article class="notification-item {{ $notification->isUnread() ? 'is-unread' : '' }}">
                        <div class="notification-dot {{ $notification->severity }}"></div>
                        <div>
                            <div class="notification-title">
                                <strong>{{ $notification->title }}</strong>
                                <span>{{ $notification->created_at->format('d M Y H:i') }}</span>
                            </div>
                            <p>{{ $notification->body }}</p>
                            <div class="notification-meta">
                                <span>{{ strtoupper($notification->type) }}</span>
                                @if($notification->module)
                                    <span>{{ strtoupper($notification->module) }}</span>
                                @endif
                                <span>FCM: {{ strtoupper($notification->fcm_status ?: '-') }}</span>
                                @if($notification->actor)
                                    <span>Oleh {{ $notification->actor->name }}</span>
                                @endif
                            </div>
                        </div>
                        <div class="notification-actions">
                            <a class="icon-link" href="{{ route('notifications.open', $notification) }}"><x-icon name="external-link" />Buka</a>
                            @if($notification->isUnread())
                                <form method="post" action="{{ route('notifications.read', $notification) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button class="icon-link" type="submit"><x-icon name="check" />Dibaca</button>
                                </form>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="empty-state">Belum ada notifikasi.</div>
                @endforelse
            </div>
            <div class="pagination-wrap">{{ $notifications->links() }}</div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <h2>Perangkat Push</h2>
                <span>{{ $fcmTokens->count() }} token</span>
            </div>
            <div class="mini-list">
                @forelse($fcmTokens as $token)
                    <div class="mini-item">
                        <div>
                            <strong>{{ $token->device_name ?: 'Browser' }}</strong>
                            <span>{{ strtoupper($token->platform) }} - {{ optional($token->last_seen_at)->format('d M Y H:i') ?: '-' }}</span>
                        </div>
                        <span class="status {{ $token->revoked_at ? 'warning' : 'success' }}">
                            {{ $token->revoked_at ? 'OFF' : 'ACTIVE' }}
                        </span>
                    </div>
                @empty
                    <div class="empty-state">Belum ada perangkat FCM aktif.</div>
                @endforelse
            </div>
        </section>
    </div>
@endsection
