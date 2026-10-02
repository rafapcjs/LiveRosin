<!DOCTYPE html>
<html lang="es-CO">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>@yield('title')</title>
    <link rel="stylesheet" href="{{ asset_v('css/app.css') }}">
</head>
<body class="theme-store">
<main class="age-gate">
    <div class="card age-card">
        <h1>@yield('title')</h1>
        <p class="muted">@yield('message')</p>
        <div class="actions"><a href="{{ url('/') }}" class="btn btn-primary">Ir al inicio</a></div>
    </div>
</main>
</body>
</html>
