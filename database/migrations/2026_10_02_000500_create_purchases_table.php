<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Compras: entradas de mercancía que aumentan el stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_name', 150);
            $table->integer('quantity');
            $table->bigInteger('unit_cost');
            $table->bigInteger('total');
            $table->string('supplier', 120)->nullable();
            // Si esta compra cambió el costo del producto, y cuál era el costo anterior
            // (para poder devolverlo si se anula la compra).
            $table->boolean('updated_cost')->default(false);
            $table->bigInteger('previous_cost')->nullable();
            $table->timestamp('purchased_at');
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index(['purchased_at', 'voided_at']);
            $table->index('product_id');
        });

        DB::statement('ALTER TABLE purchases ADD CONSTRAINT purchases_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE purchases ADD CONSTRAINT purchases_cost_check CHECK (unit_cost >= 0)');
        DB::statement('ALTER TABLE purchases ADD CONSTRAINT purchases_total_check CHECK (total = unit_cost * quantity)');
    }

    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
