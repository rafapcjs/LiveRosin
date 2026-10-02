<!DOCTYPE html>
<html lang="es-CO">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Entrar · Panel de la tienda</title>
    <link rel="stylesheet" href="{{ asset_v('css/app.css') }}">
</head>
<body class="theme-admin">
<main class="login-wrap">
    <div class="card login-card">
        <h1>Entrar al panel</h1>
        <p class="muted">Solo para la administración de la tienda.</p>

        @include('partials.flash')

        <form method="POST" action="{{ route('login.attempt') }}" novalidate>
            @csrf
            <div class="field">
                <label for="email">Usuario o correo</label>
                <input id="email" name="email" type="text" class="input @error('email') is-invalid @enderror"
                       value="{{ old('email') }}" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
                @error('email') <div class="error">{{ $message }}</div> @enderror
            </div>
            <div class="field">
                <label for="password">Contraseña</label>
                <input id="password" name="password" type="password" class="input @error('password') is-invalid @enderror"
                       autocomplete="current-password" required>
                @error('password') <div class="error">{{ $message }}</div> @enderror
            </div>
            <button type="submit" class="btn btn-primary btn-block">Entrar</button>
            <p class="hint">Por seguridad, la sesión se cierra sola después de {{ config('tienda.admin_idle_minutes') }} minutos sin usar el panel.</p>
        </form>
    </div>
</main>
<script src="{{ asset_v('js/app.js') }}" defer></script>
</body>
</html>
