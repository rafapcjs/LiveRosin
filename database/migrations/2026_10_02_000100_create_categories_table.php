<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las categorías pasan de ser una lista fija a una tabla que se administra desde el panel.
 * Los productos que ya existen se pasan a la categoría equivalente, sin perder nada.
 */
return new class extends Migration
{
    private array $initial = [
        'destilado' => ['Destilados', 'destilados', 1],
        'vaper' => ['Vapers', 'vapers', 2],
        'otras' => ['Otras', 'otras', 3],
    ];

    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 60);
            $table->string('slug', 80)->unique();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['deleted_at', 'position']);
        });

        $now = now();
        $ids = [];
        foreach ($this->initial as $old => [$name, $slug, $position]) {
            $ids[$old] = DB::table('categories')->insertGetId([
                'name' => $name, 'slug' => $slug, 'position' => $position,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('name')
                ->constrained()->restrictOnDelete();
        });

        foreach ($ids as $old => $id) {
            DB::table('products')->where('category', $old)->update(['category_id' => $id]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['is_visible', 'deleted_at', 'category']);
            $table->dropColumn('category');
        });

        DB::statement('ALTER TABLE products ALTER COLUMN category_id SET NOT NULL');

        Schema::table('products', function (Blueprint $table) {
            $table->index(['is_visible', 'deleted_at', 'category_id']);
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('category', 20)->default('otras');
        });

        foreach ($this->initial as $old => [, $slug]) {
            DB::table('products')
                ->whereIn('category_id', DB::table('categories')->where('slug', $slug)->select('id'))
                ->update(['category' => $old]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->index(['is_visible', 'deleted_at', 'category']);
        });

        Schema::dropIfExists('categories');
    }
};
