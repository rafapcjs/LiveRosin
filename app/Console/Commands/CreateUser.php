<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Crea otro administrador que entra con nombre de usuario:
 *   php artisan tienda:crear-usuario dealer1
 *   php artisan tienda:crear-usuario dealer1 --correo=dealer1@gmail.com   (el correo es opcional)
 * La contraseña se pide en la terminal (no se ve mientras se escribe).
 */
class CreateUser extends Command
{
    protected $signature = 'tienda:crear-usuario {usuario : Nombre de usuario para entrar (ej: dealer1)} {--correo= : Correo opcional para recibir avisos de seguridad}';

    protected $description = 'Crea un administrador que entra con nombre de usuario';

    public function handle(): int
    {
        $data = [
            'username' => strtolower(trim((string) $this->argument('usuario'))),
            'email' => $this->option('correo') ? strtolower(trim((string) $this->option('correo'))) : null,
        ];

        $validator = Validator::make($data, [
            'username' => ['required', 'alpha_dash', 'min:3', 'max:50', 'unique:users,username'],
            'email' => ['nullable', 'email', 'unique:users,email'],
        ], [
            'username.alpha_dash' => 'El usuario solo puede tener letras, números, guiones y guiones bajos (sin espacios).',
            'username.unique' => 'Ya existe un administrador con ese usuario.',
            'username.min' => 'El usuario debe tener al menos 3 caracteres.',
            'email.email' => 'El correo no es válido.',
            'email.unique' => 'Ya existe un administrador con ese correo.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $password = (string) $this->secret('Contraseña para '.$data['username'].' (no se ve mientras escribes)');

        if (mb_strlen($password) < 10) {
            $this->error('La contraseña debe tener al menos 10 caracteres. No se creó nada.');

            return self::FAILURE;
        }

        if ($password !== (string) $this->secret('Escríbela otra vez')) {
            $this->error('Las dos contraseñas no coinciden. No se creó nada.');

            return self::FAILURE;
        }

        User::create([
            'name' => $data['username'],
            'username' => $data['username'],
            'email' => $data['email'],
            'password' => $password,
        ]);

        $this->info("Listo. «{$data['username']}» ya puede entrar en /admin con su usuario y contraseña.");

        return self::SUCCESS;
    }
}
