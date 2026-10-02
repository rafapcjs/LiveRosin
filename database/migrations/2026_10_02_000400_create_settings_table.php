<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ajustes de la tienda: una sola fila.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('store_name', 80)->default('Mi tienda');
            $table->string('tagline', 140)->nullable();
            // Guardado como 57 + 10 dígitos, por ejemplo 573001234567.
            $table->string('whatsapp', 12)->nullable();
            // Desde cuántas unidades (o menos) un producto se considera con "pocas unidades".
            $table->unsignedInteger('low_stock_threshold')->default(3);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE settings ADD CONSTRAINT settings_threshold_check CHECK (low_stock_threshold >= 1)');

        DB::table('settings')->insert([
            'store_name' => 'Mi tienda',
            'low_stock_threshold' => 3,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
