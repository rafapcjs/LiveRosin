<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Control de edad en el SERVIDOR: sin la confirmación guardada en la cookie,
 * ninguna página pública llega a consultar ni mostrar productos.
 */
class AgeGate
{
    public const COOKIE = 'mayor_edad';

    public function handle(Request $request, Closure $next): Response
    {
        // La cookie va cifrada por Laravel: no se puede falsificar escribiéndola a mano.
        if ($request->cookie(self::COOKIE) !== '1') {
            return response()->view('catalog.age-gate', [
                'back' => $request->getRequestUri(),
            ])->header('Cache-Control', 'no-store, private');
        }

        return $next($request);
    }
}
