<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registro de seguridad: intentos de entrar al panel (fallidos, bloqueados y correctos).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);            // login_failed, login_locked, login_ok
            $table->string('email', 255)->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['type', 'created_at']);
            $table->index('created_at');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable();
            $table->string('last_login_ip', 45)->nullable();
            // Hasta cuándo ya revisó los avisos de seguridad (para no repetir el aviso).
            $table->timestamp('security_seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['last_login_at', 'last_login_ip', 'security_seen_at']);
        });

        Schema::dropIfExists('security_events');
    }
};
