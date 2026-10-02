<?php

namespace App\Http\Controllers;

use App\Http\Middleware\AgeGate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\View\View;

class AgeController extends Controller
{
    public function confirm(Request $request): RedirectResponse
    {
        if ($request->input('respuesta') !== 'si') {
            return redirect()->route('age.denied');
        }

        // Solo se vuelve a una dirección de esta misma página web.
        $back = (string) $request->input('volver', '/');
        if (! str_starts_with($back, '/') || str_starts_with($back, '//')) {
            $back = '/';
        }

        $minutes = config('tienda.age_cookie_days') * 24 * 60;

        return redirect($back)->withCookie(Cookie::make(AgeGate::COOKIE, '1', $minutes, httpOnly: true, sameSite: 'lax'));
    }

    public function denied(): View
    {
        return view('catalog.age-denied');
    }
}
