<?php

namespace App\Providers;

use App\Models\Product;
use App\Models\SecurityEvent;
use Carbon\Carbon;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Direcciones en español: /admin/productos/crear y /admin/productos/{id}/editar
        Route::resourceVerbs(['create' => 'crear', 'edit' => 'editar']);

        // Límites de visitas por minuto (protección contra abusos y robots).
        RateLimiter::for('publico', fn (Request $request) => Limit::perMinute(config('tienda.rate_public_per_minute'))->by($request->ip()));
        RateLimiter::for('panel', fn (Request $request) => Limit::perMinute(config('tienda.rate_admin_per_minute'))
            ->by($request->user()?->id ?: $request->ip()));

        // Números rojos del menú del panel: productos agotados o con pocas unidades…
        // Y aviso de seguridad: intentos fallidos de entrar desde la última vez que se revisaron.
        // Se calcula una sola vez por visita, aunque la página tenga varias vistas.
        View::composer(['layouts.admin', 'admin.*'], function ($view) {
            $attrs = request()->attributes;
            if (! $attrs->has('panel_counts')) {
                $user = Auth::user();
                $attrs->set('panel_counts', [
                    'stockAlertCount' => $user ? Product::lowStock()->count() : 0,
                    'securityAlertCount' => $user
                        ? SecurityEvent::failures()->when($user->security_seen_at, fn ($q, $seen) => $q->where('created_at', '>', $seen))->count()
                        : 0,
                ]);
            }
            $view->with($attrs->get('panel_counts'));
        });

        // Fechas en español ("1 de octubre de 2026").
        Carbon::setLocale('es');

        // Si la página se sirve con https, todos los enlaces también.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
