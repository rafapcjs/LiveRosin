<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdjustmentRequest;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Services\AdjustmentException;
use App\Services\AdjustmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdjustmentController extends Controller
{
    public function __construct(private AdjustmentService $adjustments) {}

    public function create(Product $product): View
    {
        return view('admin.adjustments.create', compact('product'));
    }

    public function store(AdjustmentRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();

        try {
            $adjustment = $request->isOut()
                ? $this->adjustments->takeOut($product->id, (int) $data['quantity'], $data['reason'], $data['note'] ?? null)
                : $this->adjustments->putIn($product->id, (int) $data['quantity'], (int) $data['unit_cost'], $data['reason'], $data['note'] ?? null);
        } catch (AdjustmentException $e) {
            return back()->withInput()->withErrors(['quantity' => $e->getMessage()]);
        }

        $units = $adjustment->quantity === 1 ? '1 unidad' : num($adjustment->quantity).' unidades';
        $message = $adjustment->isOut()
            ? "Se sacaron {$units} de «{$product->name}» (valor ".cop($adjustment->total_cost).').'
            : "Se agregaron {$units} a «{$product->name}».";

        return redirect()->route('admin.productos.edit', $product)->with('success', $message);
    }

    public function void(InventoryAdjustment $adjustment): RedirectResponse
    {
        try {
            $this->adjustments->void($adjustment);
        } catch (AdjustmentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Se anuló el ajuste de inventario.');
    }
}
