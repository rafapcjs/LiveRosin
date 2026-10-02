<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Usuario administrador con los datos del .env (ADMIN_EMAIL y ADMIN_PASSWORD).
        Artisan::call('tienda:crear-admin');
        $this->command?->line(trim(Artisan::output()));

        $this->call(ProductSeeder::class);
    }
}
