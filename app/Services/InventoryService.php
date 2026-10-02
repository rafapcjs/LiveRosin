<?php

namespace App\Services;

use App\Models\LotAllocation;
use App\Models\Product;
use App\Models\StockLot;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Motor de inventario por lotes (PEPS: primero en entrar, primero en salir).
 *
 * Todas las funciones esperan que el producto YA esté bloqueado (lockForUpdate)
 * dentro de una transacción: así nunca hay dos movimientos del mismo producto a la vez.
 *
 * Regla: stock del producto = suma de unidades que quedan en sus lotes.
 */
class InventoryService
{
    /** Entrada de unidades: crea un lote nuevo con su costo. */
    public function addLot(
        Product $product,
        int $quantity,
        int $unitCost,
        string $source,
        ?CarbonInterface $receivedAt = null,
        ?int $purchaseId = null,
        ?int $adjustmentId = null,
    ): StockLot {
        $this->assertInTransaction();

        $lot = StockLot::create([
            'product_id' => $product->id,
            'source' => $source,
            'purchase_id' => $purchaseId,
            'adjustment_id' => $adjustmentId,
            'unit_cost' => $unitCost,
            'quantity' => $quantity,
            'remaining' => $quantity,
            'received_at' => $receivedAt ?? now(),
        ]);

        $product->stock += $quantity;
        $this->refreshCost($product);

        return $lot;
    }

    /**
     * Salida de unidades: las toma de los lotes más antiguos primero.
     *
     * @return array{total_cost: int, allocations: list<array{stock_lot_id: int, quantity: int, unit_cost: int}>}
     */
    public function consume(Product $product, int $quantity): array
    {
        $this->assertInTransaction();

        if ($quantity > $product->stock) {
            throw new LogicException('No hay unidades suficientes.');
        }

        $lots = StockLot::where('product_id', $product->id)
            ->where('remaining', '>', 0)
            ->orderBy('received_at')->orderBy('id')
            ->lockForUpdate()
            ->get();

        $pending = $quantity;
        $allocations = [];
        $totalCost = 0;

        foreach ($lots as $lot) {
            if ($pending === 0) {
                break;
            }
            $take = min($pending, $lot->remaining);
            $lot->remaining -= $take;
            $lot->save();

            $allocations[] = ['stock_lot_id' => $lot->id, 'quantity' => $take, 'unit_cost' => $lot->unit_cost];
            $totalCost += $take * $lot->unit_cost;
            $pending -= $take;
        }

        if ($pending > 0) {
            // Solo pasaría si el stock y los lotes no coinciden.
            throw new LogicException("El stock de «{$product->name}» no coincide con sus lotes.");
        }

        $product->stock -= $quantity;
        $this->refreshCost($product);

        return ['total_cost' => $totalCost, 'allocations' => $allocations];
    }

    /** Devuelve a sus lotes las unidades de una venta o baja anulada. */
    public function restore(Product $product, iterable $allocations): void
    {
        $this->assertInTransaction();

        foreach ($allocations as $allocation) {
            /** @var LotAllocation $allocation */
            $lot = StockLot::whereKey($allocation->stock_lot_id)->lockForUpdate()->firstOrFail();
            $lot->remaining += $allocation->quantity;
            $lot->save();
            $product->stock += $allocation->quantity;
        }

        $this->refreshCost($product);
    }

    /** Retira un lote completo (al anular su compra o su alta). Solo si no se ha usado nada de él. */
    public function removeLot(Product $product, StockLot $lot): void
    {
        $this->assertInTransaction();

        $lot = StockLot::whereKey($lot->id)->lockForUpdate()->firstOrFail();
        $product->stock -= $lot->remaining;
        $lot->remaining = 0;
        $lot->save();

        $this->refreshCost($product);
    }

    /**
     * Costo de referencia del producto = promedio de lo que hay en bodega.
     * Si no queda nada, se conserva el último costo.
     */
    public function refreshCost(Product $product): void
    {
        if ($product->stock > 0) {
            $value = (int) StockLot::where('product_id', $product->id)->sum(DB::raw('remaining * unit_cost'));
            $product->cost = (int) round($value / $product->stock);
        }

        $product->save();
    }

    private function assertInTransaction(): void
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Los movimientos de inventario deben hacerse dentro de una transacción.');
        }
    }
}
