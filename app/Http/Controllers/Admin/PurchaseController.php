<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\PurchaseRequest;
use App\Models\Product;
use App\Models\Purchase;
use App\Services\PurchaseException;
use App\Services\PurchasesExcelExport;
use App\Services\PurchaseService;
use App\Support\DateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PurchaseController extends Controller
{
    public function __construct(private PurchaseService $purchases) {}

    public function index(Request $request): View
    {
        $range = DateRange::fromRequest($request);
        $query = $this->filtered($range);

        $purchases = (clone $query)->orderByDesc('purchased_at')->orderByDesc('id')->paginate(25)->withQueryString();

        $totals = (clone $query)->valid()
            ->selectRaw('COUNT(*) AS count, COALESCE(SUM(quantity), 0) AS units, COALESCE(SUM(total), 0) AS total')
            ->first();

        $summary = [];
        foreach (['hoy', 'semana', 'mes'] as $key) {
            $summary[$key] = $this->filtered(DateRange::preset($key))->valid()
                ->selectRaw('COALESCE(SUM(total), 0) AS total, COALESCE(SUM(quantity), 0) AS units')
                ->first();
        }

        return view('admin.purchases.index', compact('purchases', 'totals', 'range', 'summary'));
    }

    public function create(Request $request): View
    {
        return view('admin.purchases.create', [
            'products' => Product::query()->orderBy('name')->get(['id', 'name', 'cost', 'stock']),
            'selected' => (int) $request->query('producto', 0),
            'suppliers' => Purchase::query()->whereNotNull('supplier')
                ->distinct()->orderBy('supplier')->limit(50)->pluck('supplier'),
        ]);
    }

    public function store(PurchaseRequest $request): RedirectResponse
    {
        try {
            $purchase = $this->purchases->register(
                (int) $request->validated('product_id'),
                (int) $request->validated('quantity'),
                (int) $request->validated('unit_cost'),
                $request->validated('supplier'),
                $request->purchasedAt(),
            );
        } catch (PurchaseException $e) {
            return back()->withInput()->withErrors(['quantity' => $e->getMessage()]);
        }

        $units = $purchase->quantity === 1 ? '1 unidad' : num($purchase->quantity).' unidades';
        $product = Product::whereKey($purchase->product_id)->first(['stock', 'cost']);

        $message = "Compra registrada: {$units} de «{$purchase->product_name}» a ".cop($purchase->unit_cost)
            .' c/u ('.cop($purchase->total).'). Ahora hay '.num($product->stock)
            .' en stock, con un costo promedio de '.cop($product->cost).'.';

        return redirect()->route('admin.compras.index', ['periodo' => 'hoy'])->with('success', $message);
    }

    public function void(Purchase $purchase): RedirectResponse
    {
        try {
            $this->purchases->void($purchase);
        } catch (PurchaseException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Se anuló la compra de «{$purchase->product_name}» y esas unidades se restaron del stock.");
    }

    public function export(Request $request, PurchasesExcelExport $export): BinaryFileResponse
    {
        $range = DateRange::fromRequest($request);
        $path = tempnam(sys_get_temp_dir(), 'compras');

        $export->write($this->filtered($range), $path);

        $name = 'compras_'.$range->from->format('Y-m-d').'_a_'.$range->to->format('Y-m-d').'.xlsx';

        return response()->download($path, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    private function filtered(DateRange $range): Builder
    {
        return Purchase::query()->whereBetween('purchased_at', [$range->from, $range->to]);
    }
}
