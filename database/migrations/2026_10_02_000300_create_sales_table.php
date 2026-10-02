<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            // Los productos solo se eliminan "suave", así que la referencia nunca se rompe.
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            // Copias tomadas el día de la venta: si luego cambias el producto, la venta no cambia.
            $table->string('product_name', 150);
            $table->integer('quantity');
            $table->bigInteger('unit_price');
            $table->bigInteger('unit_cost');
            $table->bigInteger('total');
            $table->bigInteger('profit');
            $table->timestamp('sold_at');
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index(['sold_at', 'voided_at']);
            $table->index('product_id');
        });

        DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_money_check CHECK (unit_price >= 0 AND unit_cost >= 0)');
        DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_total_check CHECK (total = unit_price * quantity)');
        DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_profit_check CHECK (profit = (unit_price - unit_cost) * quantity)');
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
