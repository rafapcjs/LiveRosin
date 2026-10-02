<?php

use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SecurityHeaders::class]);

        // Sin sesión => pantalla de inicio de sesión. Con sesión => panel.
        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('admin.home'));

        // Detrás de Nginx en el mismo servidor.
        $middleware->trustProxies(at: '127.0.0.1');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
