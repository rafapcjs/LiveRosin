<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Cambia la contraseña de un administrador desde la terminal:
 *   php artisan tienda:cambiar-clave dealer1
 */
class ChangePassword extends Command
{
    protected $signature = 'tienda:cambiar-clave {usuario? : Usuario o correo (si hay solo un administrador, no hace falta)}';

    protected $description = 'Cambia la contraseña de un administrador';

    public function handle(): int
    {
        $login = strtolower(trim((string) $this->argument('usuario')));
        $user = $login !== ''
            ? User::where(str_contains($login, '@') ? 'email' : 'username', $login)->first()
            : (User::count() === 1 ? User::first() : null);

        if (! $user) {
            $this->error($login !== ''
                ? "No existe un administrador «{$login}»."
                : 'Hay más de un administrador. Escribe cuál: php artisan tienda:cambiar-clave dealer1');

            return self::FAILURE;
        }

        $this->info('Cambiando la contraseña de '.$user->displayName());
        $password = (string) $this->secret('Nueva contraseña (no se ve mientras escribes)');

        if (mb_strlen($password) < 10) {
            $this->error('La contraseña debe tener al menos 10 caracteres. No se cambió nada.');

            return self::FAILURE;
        }

        if ($password !== (string) $this->secret('Escríbela otra vez')) {
            $this->error('Las dos contraseñas no coinciden. No se cambió nada.');

            return self::FAILURE;
        }

        $user->forceFill(['password' => $password, 'remember_token' => null])->save();

        $this->info('Listo. La contraseña fue cambiada.');

        return self::SUCCESS;
    }
}
