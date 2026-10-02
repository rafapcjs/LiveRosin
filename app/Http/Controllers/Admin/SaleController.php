<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaleRequest;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Setting;
use App\Services\SaleException;
use App\Services\SalesExcelExport;
use App\Services\SaleService;
use App\Support\DateRange;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SaleController extends Controller
{
    public function __construct(private SaleService $sales) {}

    public function index(Request $request): View
    {
        $range = DateRange::fromRequest($request);
        $query = $this->filtered($range);

        $sales = (clone $query)->orderByDesc('sold_at')->orderByDesc('id')->paginate(25)->withQueryString();

        $totals = (clone $query)->valid()
            ->selectRaw('COUNT(*) AS count, COALESCE(SUM(quantity), 0) AS units, COALESCE(SUM(total), 0) AS total, COALESCE(SUM(profit), 0) AS profit')
            ->first();

        $summary = [];
        foreach (['hoy', 'semana', 'mes'] as $key) {
            $r = DateRange::preset($key);
            $summary[$key] = $this->filtered($r)->valid()
                ->selectRaw('COALESCE(SUM(total), 0) AS total, COALESCE(SUM(profit), 0) AS profit')
                ->first();
        }

        $lowStock = Product::lowStock()->orderBy('stock')->orderBy('name')->get(['id', 'name', 'stock']);

        return view('admin.sales.index', compact('sales', 'totals', 'range', 'summary', 'lowStock'));
    }

    public function create(Request $request): View
    {
        $products = Product::query()->with('category')->orderBy('name')
            ->get(['id', 'name', 'category_id', 'price', 'cost', 'stock']);

        return view('admin.sales.create', [
            'products' => $products,
            'selected' => (int) $request->query('producto', 0),
        ]);
    }

    public function store(SaleRequest $request): RedirectResponse
    {
        try {
            $sale = $this->sales->register(
                (int) $request->validated('product_id'),
                (int) $request->validated('quantity'),
                (int) $request->validated('unit_price'),
                $request->soldAt(),
                $request->validated('note'),
            );
        } catch (SaleException $e) {
            return back()->withInput()->withErrors(['quantity' => $e->getMessage()]);
        }

        $units = $sale->quantity === 1 ? '1 unidad' : "{$sale->quantity} unidades";
        $redirect = redirect()->route('admin.ventas.index', ['periodo' => 'hoy'])
            ->with('success', "Venta registrada: {$units} de «{$sale->product_name}» por ".cop($sale->total).'.');

        // Aviso si la venta dejó el producto agotado o con pocas unidades.
        $left = (int) Product::whereKey($sale->product_id)->value('stock');
        if ($left === 0) {
            $redirect->with('warning', "«{$sale->product_name}» quedó agotado.");
        } elseif ($left <= Setting::lowStockThreshold()) {
            $redirect->with('warning', ($left === 1 ? 'Queda 1 unidad' : "Quedan {$left} unidades")." de «{$sale->product_name}».");
        }

        return $redirect;
    }

    public function void(Sale $sale): RedirectResponse
    {
        try {
            $this->sales->void($sale);
        } catch (SaleException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Se anuló la venta de «{$sale->product_name}» y las unidades volvieron al stock.");
    }

    public function export(Request $request, SalesExcelExport $export): BinaryFileResponse
    {
        $range = DateRange::fromRequest($request);
        $path = tempnam(sys_get_temp_dir(), 'ventas');

        $export->write($this->filtered($range), $path);

        $name = 'ventas_'.$range->from->format('Y-m-d').'_a_'.$range->to->format('Y-m-d').'.xlsx';

        return response()->download($path, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend();
    }

    private function filtered(DateRange $range): Builder
    {
        return Sale::query()->whereBetween('sold_at', [$range->from, $range->to]);
    }
}
