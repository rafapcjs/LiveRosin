<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/**
 * Crea (o actualiza) el único usuario administrador con los datos del archivo .env:
 * ADMIN_NAME, ADMIN_EMAIL y ADMIN_PASSWORD.
 */
class CreateAdmin extends Command
{
    protected $signature = 'tienda:crear-admin';

    protected $description = 'Crea el usuario administrador con los datos del archivo .env';

    public function handle(): int
    {
        $data = [
            'name' => config('tienda.admin_name'),
            'email' => config('tienda.admin_email'),
            'password' => config('tienda.admin_password'),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:10'],
        ], [
            'email.required' => 'Falta ADMIN_EMAIL en el archivo .env.',
            'email.email' => 'ADMIN_EMAIL no es un correo válido.',
            'password.required' => 'Falta ADMIN_PASSWORD en el archivo .env.',
            'password.min' => 'ADMIN_PASSWORD debe tener al menos 10 caracteres.',
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => strtolower($data['email'])],
            ['name' => $data['name'], 'password' => $data['password']],
        );

        $this->info("Listo. El administrador {$user->email} puede entrar en /admin.");
        $this->line('Consejo: borra ADMIN_PASSWORD del archivo .env cuando ya hayas entrado.');

        return self::SUCCESS;
    }
}
