<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Avisa por correo cuando alguien queda bloqueado por muchos intentos de entrar al panel.
 * Máximo un correo por hora por cada dirección IP, para no llenar la bandeja.
 */
class SecurityAlertService
{
    public function loginLocked(string $email, ?string $ip): void
    {
        if (! Cache::add('alerta-seguridad:'.($ip ?? 'sin-ip'), true, now()->addHour())) {
            return;
        }

        $to = User::query()->whereNotNull('email')->pluck('email')->filter()->all();
        if ($to === []) {
            return;
        }

        $store = config('app.name');
        $when = now()->translatedFormat('j \d\e F \d\e Y, h:i a');
        $body = implode("\n", [
            "Hola,",
            '',
            "Alguien intentó entrar varias veces al panel de {$store} con una contraseña equivocada y fue bloqueado por unos minutos.",
            '',
            "Fecha: {$when} (hora de Colombia)",
            "Correo usado: {$email}",
            'Dirección IP: '.($ip ?? 'desconocida'),
            '',
            'Si fuiste tú, no tienes que hacer nada.',
            'Si NO fuiste tú, te recomendamos cambiar tu contraseña con: php artisan tienda:cambiar-clave',
            '',
            'Puedes ver el detalle en el panel, en Ajustes → Seguridad.',
        ]);

        try {
            Mail::raw($body, function ($message) use ($to, $store) {
                $message->to($to)->subject("⚠ Intentos fallidos de entrar al panel de {$store}");
            });
        } catch (Throwable $e) {
            // Un problema con el correo nunca debe impedir que el panel funcione.
            Log::warning('No se pudo enviar el aviso de seguridad por correo', ['error' => $e->getMessage()]);
        }
    }
}
