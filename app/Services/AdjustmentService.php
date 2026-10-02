<?php

namespace App\Services;

use App\Models\InventoryAdjustment;
use App\Models\LotAllocation;
use App\Models\Product;
use App\Models\StockLot;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Ajustes de inventario: sacar unidades sin venderlas (daño, pérdida, consumo…)
 * o agregar unidades sin compra (corrección de conteo, regalo del proveedor…).
 */
class AdjustmentService
{
    public function __construct(private InventoryService $inventory) {}

    /** Baja: las unidades salen de los lotes más antiguos y se registra su costo real. */
    public function takeOut(int $productId, int $quantity, string $reason, ?string $note = null, ?CarbonInterface $at = null): InventoryAdjustment
    {
        return DB::transaction(function () use ($productId, $quantity, $reason, $note, $at) {
            $product = $this->lockProduct($productId);

            if ($quantity > $product->stock) {
                $units = $product->stock === 1 ? 'Solo queda 1 unidad' : "Solo quedan {$product->stock} unidades";
                throw new AdjustmentException("{$units} de «{$product->name}».");
            }

            $consumed = $this->inventory->consume($product, $quantity);

            $adjustment = InventoryAdjustment::create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'type' => 'baja',
                'reason' => $reason,
                'note' => $note,
                'quantity' => $quantity,
                'total_cost' => $consumed['total_cost'],
                'adjusted_at' => $at ?? now(),
            ]);

            foreach ($consumed['allocations'] as $a) {
                LotAllocation::create($a + ['adjustment_id' => $adjustment->id]);
            }

            return $adjustment;
        });
    }

    /** Alta: entran unidades sin compra, como un lote nuevo con el costo indicado (puede ser 0). */
    public function putIn(int $productId, int $quantity, int $unitCost, string $reason, ?string $note = null, ?CarbonInterface $at = null): InventoryAdjustment
    {
        return DB::transaction(function () use ($productId, $quantity, $unitCost, $reason, $note, $at) {
            $product = $this->lockProduct($productId);
            $at ??= now();

            $adjustment = InventoryAdjustment::create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'type' => 'alta',
                'reason' => $reason,
                'note' => $note,
                'quantity' => $quantity,
                'total_cost' => $quantity * $unitCost,
                'adjusted_at' => $at,
            ]);

            $this->inventory->addLot($product, $quantity, $unitCost, 'ajuste', $at, adjustmentId: $adjustment->id);

            return $adjustment;
        });
    }

    public function void(InventoryAdjustment $adjustment): InventoryAdjustment
    {
        return DB::transaction(function () use ($adjustment) {
            /** @var InventoryAdjustment $locked */
            $locked = InventoryAdjustment::whereKey($adjustment->id)->lockForUpdate()->firstOrFail();

            if ($locked->isVoided()) {
                throw new AdjustmentException('Este ajuste ya estaba anulado.');
            }

            $product = Product::withTrashed()->whereKey($locked->product_id)->lockForUpdate()->firstOrFail();

            if ($locked->isOut()) {
                $this->inventory->restore($product, $locked->allocations()->get());
            } else {
                $lot = StockLot::where('adjustment_id', $locked->id)->lockForUpdate()->firstOrFail();
                if ($lot->remaining < $lot->quantity) {
                    throw new AdjustmentException('No puedes anular este ajuste: ya salieron unidades de ese lote.');
                }
                $this->inventory->removeLot($product, $lot);
            }

            $locked->voided_at = now();
            $locked->save();

            return $locked;
        });
    }

    private function lockProduct(int $productId): Product
    {
        $product = Product::whereKey($productId)->lockForUpdate()->first();

        if (! $product) {
            throw new AdjustmentException('Ese producto no existe o fue eliminado.');
        }

        return $product;
    }
}
