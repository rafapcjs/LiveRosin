<!DOCTYPE html>
<html lang="es-CO">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b0704">
    <link rel="preload" href="{{ asset('fonts/anton-400.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ asset('fonts/barlow-condensed-500.woff2') }}" as="font" type="font/woff2" crossorigin>
    @if (config('tienda.noindex'))
        <meta name="robots" content="noindex, nofollow">
    @endif
    <title>@yield('title', \App\Models\Setting::current()->store_name)</title>
    <link rel="stylesheet" href="{{ asset_v('css/app.css') }}">
</head>
<body class="theme-barrio">
    @yield('body')
    <script src="{{ asset_v('js/app.js') }}" defer></script>
</body>
</html>
