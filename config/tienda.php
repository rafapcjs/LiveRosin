<?php

return [
    // Usuario administrador (se crea con: php artisan tienda:crear-admin)
    'admin_name' => env('ADMIN_NAME', 'Administrador'),
    'admin_email' => env('ADMIN_EMAIL'),
    'admin_password' => env('ADMIN_PASSWORD'),

    // Minutos sin usar el panel antes de cerrar la sesión sola.
    'admin_idle_minutes' => (int) env('ADMIN_IDLE_MINUTES', 30),

    // Visitas por minuto permitidas a cada persona (protección contra abusos y robots).
    'rate_public_per_minute' => (int) env('RATE_PUBLIC_PER_MINUTE', 120),
    'rate_admin_per_minute' => (int) env('RATE_ADMIN_PER_MINUTE', 300),

    // Pedir a los buscadores (Google, etc.) que no muestren el catálogo.
    'noindex' => (bool) env('STORE_NOINDEX', true),

    // Duración de la confirmación de mayoría de edad (días).
    'age_cookie_days' => 30,
];
