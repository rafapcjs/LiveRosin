<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Inventario por lotes (PEPS: primero en entrar, primero en salir).
 *
 * - stock_lots: cada entrada de mercancía con su propio costo y cuántas unidades le quedan.
 * - inventory_adjustments: bajas (daño, pérdida, consumo…) o altas sin compra (corrección de conteo…).
 * - lot_allocations: de qué lotes salió cada unidad vendida o dada de baja, y a qué costo.
 *
 * Regla que siempre se cumple: stock del producto = suma de unidades que quedan en sus lotes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inventory_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('product_name', 150);
            $table->string('type', 10);              // 'baja' (saca unidades) o 'alta' (agrega unidades)
            $table->string('reason', 20);            // danado, perdido, consumo, conteo, regalo, otro
            $table->string('note', 200)->nullable();
            $table->integer('quantity');
            $table->bigInteger('total_cost');        // valor de las unidades que salieron o entraron
            $table->timestamp('adjusted_at');
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'adjusted_at']);
            $table->index(['adjusted_at', 'voided_at']);
        });

        Schema::create('stock_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->string('source', 12);            // inicial, compra, ajuste, devolucion
            $table->foreignId('purchase_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('adjustment_id')->nullable()->constrained('inventory_adjustments')->restrictOnDelete();
            $table->bigInteger('unit_cost');
            $table->integer('quantity');             // unidades con las que entró el lote
            $table->integer('remaining');            // unidades que todavía quedan de este lote
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index(['product_id', 'remaining', 'received_at', 'id']);
        });

        DB::statement('ALTER TABLE stock_lots ADD CONSTRAINT stock_lots_qty_check CHECK (quantity > 0 AND remaining >= 0 AND remaining <= quantity AND unit_cost >= 0)');

        Schema::create('lot_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_lot_id')->constrained()->restrictOnDelete();
            $table->foreignId('sale_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('adjustment_id')->nullable()->constrained('inventory_adjustments')->restrictOnDelete();
            $table->integer('quantity');
            $table->bigInteger('unit_cost');
            $table->timestamps();

            $table->index('sale_id');
            $table->index('adjustment_id');
        });

        DB::statement('ALTER TABLE lot_allocations ADD CONSTRAINT lot_allocations_qty_check CHECK (quantity > 0 AND unit_cost >= 0)');
        DB::statement('ALTER TABLE lot_allocations ADD CONSTRAINT lot_allocations_owner_check CHECK ((sale_id IS NULL) <> (adjustment_id IS NULL))');

        // Ventas: el costo real puede venir de varios lotes, así que se guarda el costo total exacto.
        Schema::table('sales', function (Blueprint $table) {
            $table->bigInteger('total_cost')->default(0)->after('unit_cost');
        });
        DB::statement('UPDATE sales SET total_cost = unit_cost * quantity');
        DB::statement('ALTER TABLE sales DROP CONSTRAINT sales_profit_check');
        DB::statement('ALTER TABLE sales ADD CONSTRAINT sales_profit_check CHECK (total_cost >= 0 AND profit = total - total_cost)');

        // Compras: el costo del producto ahora se calcula solo (promedio de lo que hay en bodega).
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn(['updated_cost', 'previous_cost']);
        });

        // Lo que hay hoy en stock queda como "inventario inicial" al costo actual de cada producto.
        DB::statement("
            INSERT INTO stock_lots (product_id, source, unit_cost, quantity, remaining, received_at, created_at, updated_at)
            SELECT id, 'inicial', cost, stock, stock, created_at, NOW(), NOW()
            FROM products WHERE stock > 0
        ");
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->boolean('updated_cost')->default(false);
            $table->bigInteger('previous_cost')->nullable();
        });

        DB::statement('ALTER TABLE sales DROP CONSTRAINT sales_profit_check');
        DB::statement('UPDATE sales SET unit_cost = CASE WHEN quantity > 0 THEN total_cost / quantity ELSE unit_cost END');
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn('total_cost');
        });

        Schema::dropIfExists('lot_allocations');
        Schema::dropIfExists('stock_lots');
        Schema::dropIfExists('inventory_adjustments');
    }
};
