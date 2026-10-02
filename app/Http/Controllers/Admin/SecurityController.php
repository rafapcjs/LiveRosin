<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Registro de seguridad: últimos intentos de entrar al panel.
 * Al abrir esta página, el aviso rojo de intentos fallidos se marca como visto.
 */
class SecurityController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $seenBefore = $user->security_seen_at;

        $events = SecurityEvent::query()->latest('created_at')->latest('id')->paginate(50);

        $failures24h = SecurityEvent::failures()->where('created_at', '>=', now()->subDay())->count();
        $failures30d = SecurityEvent::failures()->where('created_at', '>=', now()->subDays(30))->count();

        $user->forceFill(['security_seen_at' => now()])->save();

        return view('admin.security.index', compact('events', 'failures24h', 'failures30d', 'seenBefore', 'user'));
    }
}
