<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ config('app.name') }}</title>
    <link rel="stylesheet" href="{{ asset('css/erp.css') }}">
    <script src="https://unpkg.com/lucide@latest" defer></script>
</head>
<body class="auth-page">
    <main class="auth-shell">
        <section class="auth-brand">
            <div class="brand-mark">H</div>
            <p class="eyebrow">Laravel ERP</p>
            <h1>Horizon ERP</h1>
            <p>Portal operasional dengan database, login, register, role admin, member, dan user.</p>
        </section>

        <section class="auth-card">
            @if(session('success'))
                <div class="alert success">{{ session('success') }}</div>
            @endif
            @if($errors->any())
                <div class="alert danger">{{ $errors->first() }}</div>
            @endif
            @yield('content')
        </section>
    </main>
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
    </script>
</body>
</html>
