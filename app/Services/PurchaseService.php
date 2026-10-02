<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\StockLot;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Reglas de las compras (entradas de mercancía). Cada compra crea un lote con su propio costo.
 */
class PurchaseService
{
    public function __construct(private InventoryService $inventory) {}

    public function register(
        int $productId,
        int $quantity,
        int $unitCost,
        ?string $supplier = null,
        ?CarbonInterface $purchasedAt = null,
    ): Purchase {
        if ($quantity < 1) {
            throw new PurchaseException('La cantidad debe ser al menos 1.');
        }

        return DB::transaction(function () use ($productId, $quantity, $unitCost, $supplier, $purchasedAt) {
            /** @var Product|null $product */
            $product = Product::whereKey($productId)->lockForUpdate()->first();

            if (! $product) {
                throw new PurchaseException('Ese producto no existe o fue eliminado.');
            }

            $purchasedAt ??= now();

            $purchase = Purchase::create([
                'product_id' => $product->id,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total' => $unitCost * $quantity,
                'supplier' => $supplier,
                'purchased_at' => $purchasedAt,
            ]);

            $this->inventory->addLot($product, $quantity, $unitCost, 'compra', $purchasedAt, purchaseId: $purchase->id);

            return $purchase;
        });
    }

    public function void(Purchase $purchase): Purchase
    {
        return DB::transaction(function () use ($purchase) {
            /** @var Purchase $locked */
            $locked = Purchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();

            if ($locked->isVoided()) {
                throw new PurchaseException('Esta compra ya estaba anulada.');
            }

            $product = Product::withTrashed()->whereKey($locked->product_id)->lockForUpdate()->firstOrFail();
            $lot = StockLot::where('purchase_id', $locked->id)->lockForUpdate()->first();

            if (! $lot) {
                throw new PurchaseException(
                    'Esta compra se registró antes del sistema de lotes y no se puede anular. '
                    .'Si necesitas corregir el stock, usa «Ajustar inventario» en el producto.'
                );
            }

            if ($lot->remaining < $lot->quantity) {
                $used = $lot->quantity - $lot->remaining;
                $what = $used === 1 ? '1 unidad' : "{$used} unidades";
                throw new PurchaseException(
                    "No puedes anular esta compra: ya salieron {$what} de ese lote (vendidas o dadas de baja)."
                );
            }

            $locked->voided_at = now();
            $locked->save();

            $this->inventory->removeLot($product, $lot);

            return $locked;
        });
    }
}
