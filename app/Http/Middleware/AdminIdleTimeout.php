<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cierra la sesión del panel si la cuenta fue deshabilitada o si pasó mucho tiempo
 * sin usarlo (por defecto 30 minutos; se cambia con ADMIN_IDLE_MINUTES en el .env).
 */
class AdminIdleTimeout
{
    public const SESSION_KEY = 'admin_last_activity';

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        // Si otro administrador deshabilitó esta cuenta, se sale de inmediato.
        if (! $request->user()->is_active) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'Tu cuenta fue deshabilitada. Habla con otro administrador.');
        }

        $limit = max(1, (int) config('tienda.admin_idle_minutes')) * 60;
        $last = (int) $request->session()->get(self::SESSION_KEY, 0);

        if ($last > 0 && now()->timestamp - $last > $limit) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            $message = 'Tu sesión se cerró por inactividad. Vuelve a entrar.';

            return $request->expectsJson()
                ? response()->json(['message' => $message], 401)
                : redirect()->route('login')->with('warning', $message);
        }

        $request->session()->put(self::SESSION_KEY, now()->timestamp);

        return $next($request);
    }
}
