<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Las categorías se muestran en orden alfabético; ya no se usa el campo "orden".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['deleted_at', 'position']);
            $table->dropColumn('position');
            $table->index(['deleted_at', 'name']);
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['deleted_at', 'name']);
            $table->unsignedInteger('position')->default(0);
            $table->index(['deleted_at', 'position']);
        });
    }
};
