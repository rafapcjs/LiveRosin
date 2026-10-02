<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->enum('category', ['destilado', 'vaper', 'otras']);
            $table->text('description')->nullable();
            $table->string('photo_path')->nullable();
            // Dinero en pesos colombianos, sin decimales.
            $table->bigInteger('cost');
            $table->bigInteger('price');
            $table->integer('stock')->default(0);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_visible', 'deleted_at', 'category']);
            $table->index('name');
        });

        // Reglas que la propia base de datos hace cumplir.
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_stock_check CHECK (stock >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_cost_check CHECK (cost >= 0)');
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_price_check CHECK (price >= 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
