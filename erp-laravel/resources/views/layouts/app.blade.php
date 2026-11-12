<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name'))</title>
    <link rel="stylesheet" href="{{ asset('css/erp.css') }}">
    <script src="https://unpkg.com/lucide@latest" defer></script>
</head>
<body>
    @php($homeRoute = auth()->user()->hasPrivilege('dashboard.view') ? route('dashboard') : (auth()->user()->hasPrivilege('employee.portal') ? route('employee.portal') : route('notifications.index')))
    @php($moduleIcons = [
        'sales' => 'shopping-cart',
        'procurement' => 'package-check',
        'inventory' => 'boxes',
        'finance' => 'wallet',
        'accounting' => 'calculator',
        'crm' => 'handshake',
        'marketing' => 'radio-tower',
        'manufacturing' => 'factory',
        'projects' => 'clipboard-list',
        'hr' => 'users',
        'hris' => 'id-card',
        'assets' => 'warehouse',
    ])
    <div class="app-shell">
        <aside class="sidebar" id="sidebar">
            <a class="brand" href="{{ $homeRoute }}">
                <div class="brand-mark">H</div>
                <div>
                    <strong>Horizon ERP</strong>
                    <span>Laravel + MySQL</span>
                </div>
            </a>

            <nav class="module-nav">
                @if(auth()->user()->hasPrivilege('dashboard.view'))
                    <a class="nav-item {{ request()->routeIs('dashboard') ? 'is-active' : '' }}" href="{{ route('dashboard') }}">
                        <span class="nav-label"><x-icon name="layout-dashboard" /><span>Dashboard</span></span>
                        <b>{{ array_sum($moduleCounts ?? []) }}</b>
                    </a>
                @endif
                @foreach($accessibleModules as $moduleKey => $moduleConfig)
                    @php($moduleHref = $moduleKey === 'accounting' && \Illuminate\Support\Facades\Route::has('accounting.ledger') ? route('accounting.ledger') : route('records.index', $moduleKey))
                    @php($moduleIsActive = request()->route('module') === $moduleKey || ($moduleKey === 'accounting' && request()->routeIs('accounting.*')))
                    <a class="nav-item {{ $moduleIsActive ? 'is-active' : '' }}" href="{{ $moduleHref }}">
                        <span class="nav-label"><x-icon :name="$moduleIcons[$moduleKey] ?? 'boxes'" /><span>{{ $moduleConfig['label'] }}</span></span>
                        <b>{{ $moduleCounts[$moduleKey] ?? 0 }}</b>
                    </a>
                @endforeach
                @if(auth()->user()->hasPrivilege('employee.portal'))
                    <a class="nav-item {{ request()->routeIs('employee.*') ? 'is-active' : '' }}" href="{{ route('employee.portal') }}">
                        <span class="nav-label"><x-icon name="user-check" /><span>Employee Portal</span></span>
                        <b>ESS</b>
                    </a>
                @endif
                @if(auth()->user()->hasPrivilege('employee.manage'))
                    <a class="nav-item {{ request()->routeIs('settings.employees*') ? 'is-active' : '' }}" href="{{ route('settings.employees') }}">
                        <span class="nav-label"><x-icon name="users" /><span>SDM / Employee</span></span>
                        <b>{{ \App\Models\EmployeeProfile::count() }}</b>
                    </a>
                @endif
                @if(auth()->user()->hasPrivilege('marketing.manage'))
                    <a class="nav-item {{ request()->routeIs('marketing.*') ? 'is-active' : '' }}" href="{{ route('marketing.index') }}">
                        <span class="nav-label"><x-icon name="map" /><span>Marketing Mgmt</span></span>
                        <b>{{ \App\Models\MarketingTracking::count() }}</b>
                    </a>
                @endif
                @if(auth()->user()->hasPrivilege('modules.sales.view'))
                    <a class="nav-item {{ request()->routeIs('sales.shipments.*') ? 'is-active' : '' }}" href="{{ route('sales.shipments.index') }}">
                        <span class="nav-label"><x-icon name="truck" /><span>Pengiriman Sales</span></span>
                        <b>{{ \Illuminate\Support\Facades\Schema::hasTable('sales_shipments') ? \App\Models\SalesShipment::count() : 0 }}</b>
                    </a>
                @endif
                @if(auth()->user()->hasPrivilege('modules.sales.view') || auth()->user()->hasPrivilege('modules.manufacturing.view') || auth()->user()->hasPrivilege('master-data.manage'))
                    <a class="nav-item {{ request()->routeIs('products.*') ? 'is-active' : '' }}" href="{{ route('products.index') }}">
                        <span class="nav-label"><x-icon name="package" /><span>Product Mgmt</span></span>
                        <b>{{ \Illuminate\Support\Facades\Schema::hasTable('products') ? \App\Models\Product::count() : 0 }}</b>
                    </a>
                @endif
                @if(auth()->user()->hasPrivilege('payments.view'))
                    <a class="nav-item {{ request()->routeIs('payments.*') ? 'is-active' : '' }}" href="{{ route('payments.index') }}">
                        <span class="nav-label"><x-icon name="credit-card" /><span>Pembayaran</span></span>
                        <b>{{ \App\Models\Payment::count() }}</b>
                    </a>
                @endif
                @if(auth()->user()->hasPrivilege('reports.view'))
                    <a class="nav-item {{ request()->routeIs('reports.*') ? 'is-active' : '' }}" href="{{ route('reports.index') }}">
                        <span class="nav-label"><x-icon name="bar-chart-3" /><span>Report Center</span></span>
                        <b>{{ count($accessibleModules ?? []) }}</b>
                    </a>
                @endif
                @if(auth()->user()->hasPrivilege('calendar.view'))
                    <a class="nav-item {{ request()->routeIs('calendar.*') ? 'is-active' : '' }}" href="{{ route('calendar.index') }}">
                        <span class="nav-label"><x-icon name="calendar-days" /><span>Kalender ERP</span></span>
                        <b>{{ \Illuminate\Support\Facades\Schema::hasTable('calendar_events') ? \App\Models\CalendarEvent::count() : 0 }}</b>
                    </a>
                @endif
                @if(auth()->user()->hasPrivilege('notifications.view'))
                    <a class="nav-item {{ request()->routeIs('notifications.*') ? 'is-active' : '' }}" href="{{ route('notifications.index') }}">
                        <span class="nav-label"><x-icon name="bell" /><span>Notifikasi</span></span>
                        <b id="notificationNavBadge">{{ $unreadNotificationsCount ?? 0 }}</b>
                    </a>
                @endif
                @if(auth()->user()->hasPrivilege('announcements.view'))
                    <a class="nav-item {{ request()->routeIs('announcements.*') ? 'is-active' : '' }}" href="{{ route('announcements.index') }}">
                        <span class="nav-label"><x-icon name="newspaper" /><span>Pengumuman & Berita</span></span>
                        <b>{{ $announcementUnreadCount ?? 0 }}</b>
                    </a>
                @endif
                @if(auth()->user()->hasPrivilege('announcements.manage'))
                    <a class="nav-item {{ request()->routeIs('settings.announcements') ? 'is-active' : '' }}" href="{{ route('settings.announcements') }}">
                        <span class="nav-label"><x-icon name="megaphone" /><span>Kelola Berita</span></span>
                        <b>{{ $announcementPublishedCount ?? 0 }}</b>
                    </a>
                @endif
                @if(auth()->user()->hasPrivilege('users.manage'))
                    <a class="nav-item {{ request()->routeIs('settings.users') ? 'is-active' : '' }}" href="{{ route('settings.users') }}">
                        <span class="nav-label"><x-icon name="user-cog" /><span>Pengaturan User</span></span>
                        <b>{{ \App\Models\User::count() }}</b>
                    </a>
                @endif
                @if(auth()->user()->hasPrivilege('privileges.manage'))
                    <a class="nav-item {{ request()->routeIs('settings.privileges') ? 'is-active' : '' }}" href="{{ route('settings.privileges') }}">
                        <span class="nav-label"><x-icon name="shield-check" /><span>Privilege</span></span>
                        <b>{{ \App\Models\Privilege::count() }}</b>
                    </a>
                @endif
                @if(auth()->user()->hasPrivilege('master-data.manage'))
                    <a class="nav-item {{ request()->routeIs('settings.master-data') ? 'is-active' : '' }}" href="{{ route('settings.master-data') }}">
                        <span class="nav-label"><x-icon name="database" /><span>Master Data</span></span>
                        <b>{{ \App\Models\MasterDataGroup::count() }}</b>
                    </a>
                @endif
            </nav>

            <div class="sidebar-footer">
                <span>Login sebagai</span>
                <strong>{{ auth()->user()->name }}</strong>
                <small>{{ strtoupper(auth()->user()->role) }}</small>
            </div>
        </aside>

        <main class="workspace">
            <header class="topbar">
                <button class="icon-button mobile-only" type="button" id="sidebarToggle" aria-label="Menu"><x-icon name="menu" /></button>
                @php($searchModule = request()->route('module') ?: array_key_first($accessibleModules ?? []))
                <form class="global-search" method="get" action="{{ $searchModule ? route('records.index', $searchModule) : $homeRoute }}">
                    <x-icon name="search" class="search-icon" />
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Cari kode, pelanggan, SKU, pegawai...">
                    @if(request('status'))
                        <input type="hidden" name="status" value="{{ request('status') }}">
                    @endif
                </form>
                <div class="topbar-actions">
                    @yield('actions')
                    <a class="ghost-button" href="{{ route('account.edit') }}">
                        @if(auth()->user()->photo_path)
                            <img class="topbar-avatar" src="{{ asset(auth()->user()->photo_path) }}" alt="{{ auth()->user()->name }}">
                        @else
                            <x-icon name="user-circle" />
                        @endif
                        <span>Profil</span>
                    </a>
                    @if(auth()->user()->hasPrivilege('notifications.view'))
                        <a class="ghost-button notification-button" href="{{ route('notifications.index') }}">
                            <x-icon name="bell" />
                            <span>Notifikasi</span>
                            <b id="notificationBadge">{{ $unreadNotificationsCount ?? 0 }}</b>
                        </a>
                        <button class="ghost-button push-button" type="button" id="enablePushButton"><x-icon name="radio-tower" /><span data-push-label>Aktifkan Push</span></button>
                    @endif
                    <form method="post" action="{{ route('logout') }}">
                        @csrf
                        <button class="ghost-button" type="submit"><x-icon name="log-out" />Logout</button>
                    </form>
                </div>
            </header>

            <section class="content">
                @if(session('success'))
                    <div class="alert success">{{ session('success') }}</div>
                @endif
                @if(isset($errors) && $errors->any())
                    <div class="alert danger">{{ $errors->first() }}</div>
                @endif
                @yield('content')
            </section>
        </main>
    </div>

    <script>
        function renderErpIcons() {
            if (window.lucide) {
                window.lucide.createIcons({
                    attrs: {
                        'stroke-width': 2.2,
                    },
                });
            }
        }

        document.addEventListener('DOMContentLoaded', renderErpIcons);
        window.addEventListener('load', renderErpIcons);

        document.getElementById('sidebarToggle')?.addEventListener('click', function () {
            document.getElementById('sidebar').classList.toggle('is-open');
        });
    </script>
    @if(auth()->user()->hasPrivilege('notifications.view'))
        @include('notifications.fcm-script')
    @endif
</body>
</html>
