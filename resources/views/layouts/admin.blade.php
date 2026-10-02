<!DOCTYPE html>
<html lang="es-CO">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Panel') · Panel de la tienda</title>
    <link rel="stylesheet" href="{{ asset_v('css/app.css') }}">
</head>
<body class="theme-admin"
      data-idle-minutes="{{ config('tienda.admin_idle_minutes') }}"
      data-keepalive-url="{{ route('admin.keepalive') }}"
      data-login-url="{{ route('login') }}">
    <header class="admin-top">
        <a href="{{ route('admin.home') }}" class="admin-brand">Panel</a>
        <div class="admin-top-actions">
            <a href="{{ route('home') }}" target="_blank" rel="noopener" class="btn btn-sm btn-ghost">🛍️ Catálogo</a>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="btn btn-sm">Cerrar sesión</button>
            </form>
        </div>
    </header>

    <div class="admin-shell">
        <nav class="admin-nav" aria-label="Menú del panel">
            <a href="{{ route('admin.ventas.index') }}" @class(['active' => request()->routeIs('admin.ventas.*')])>
                <span class="ico" aria-hidden="true">💰</span> Ventas
            </a>
            <a href="{{ route('admin.compras.index') }}" @class(['active' => request()->routeIs('admin.compras.*')])>
                <span class="ico" aria-hidden="true">🛒</span> Compras
            </a>
            <a href="{{ route('admin.productos.index') }}" @class(['active' => request()->routeIs('admin.productos.*')])>
                <span class="ico" aria-hidden="true">📦</span> Productos
                @if ($stockAlertCount > 0)
                    <span class="nav-badge" title="Productos agotados o con pocas unidades">{{ $stockAlertCount }}</span>
                @endif
            </a>
            <a href="{{ route('admin.categorias.index') }}" @class(['active' => request()->routeIs('admin.categorias.*')])>
                <span class="ico" aria-hidden="true">🏷️</span> Categorías
            </a>
            <a href="{{ route('admin.ajustes.edit') }}" @class(['active' => request()->routeIs('admin.ajustes.*', 'admin.seguridad')])>
                <span class="ico" aria-hidden="true">⚙️</span> Ajustes
                @if ($securityAlertCount > 0)
                    <span class="nav-badge" title="Intentos fallidos de entrar al panel">!</span>
                @endif
            </a>
        </nav>

        <main class="admin-main">
            @if ($securityAlertCount > 0 && ! request()->routeIs('admin.seguridad'))
                <div class="alert alert-danger security-alert" role="alert">
                    <span>⚠ Hubo <strong>{{ $securityAlertCount === 1 ? '1 intento fallido' : $securityAlertCount.' intentos fallidos' }}</strong> de entrar al panel.</span>
                    <a href="{{ route('admin.seguridad') }}" class="btn btn-sm">Ver detalles</a>
                </div>
            @endif
            @include('partials.flash')
            @yield('content')
        </main>
    </div>

    @include('partials.confirm-dialog')

    {{-- Aviso antes de cerrar la sesión por inactividad --}}
    <div class="idle-warning" data-idle-warning hidden role="alertdialog" aria-live="assertive" aria-labelledby="idle-text">
        <p id="idle-text">Por seguridad, tu sesión se cerrará en <strong data-idle-countdown>2:00</strong> por inactividad.</p>
        <button type="button" class="btn btn-primary" data-idle-keep>Seguir conectado</button>
    </div>
    <script src="{{ asset_v('js/app.js') }}" defer></script>
    @stack('scripts')
</body>
</html>
