<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductRequest;
use App\Models\Product;
use App\Models\Setting;
use App\Services\PhotoService;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private PhotoService $photos) {}

    public function index(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));
        $categoryId = (int) $request->query('categoria', 0);
        $categories = Category::ordered()->pluck('name', 'id');
        $lowOnly = $request->query('stock') === 'bajo';
        $threshold = Setting::lowStockThreshold();

        $products = Product::query()
            ->with('category')
            ->when($search !== '', fn ($q) => $q->where('name', 'ilike', '%'.addcslashes($search, '%_\\').'%'))
            ->when($categoryId > 0, fn ($q) => $q->where('category_id', $categoryId))
            ->when($lowOnly, fn ($q) => $q->lowStock()->orderBy('stock'))
            ->orderBy('name')
            ->paginate(20)
            ->withQueryString();

        return view('admin.products.index', compact('products', 'search', 'categoryId', 'categories', 'lowOnly', 'threshold'));
    }

    public function create(): View
    {
        return view('admin.products.create', [
            'product' => new Product(['is_visible' => true, 'stock' => 0]),
            'categories' => Category::ordered()->pluck('name', 'id'),
        ]);
    }

    public function store(ProductRequest $request): RedirectResponse
    {
        $data = [];

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $this->photos->store($request->file('photo'));
        }

        // Todo o nada: categoría nueva (si se pidió) y producto.
        // El producto nace sin unidades: el stock entra solo con Compras.
        $product = DB::transaction(fn () => Product::create([
            ...$request->productData(),
            ...$data,
            'cost' => (int) $request->validated('cost'),
            'stock' => 0,
        ]));

        return redirect()->route('admin.compras.create', ['producto' => $product->id])
            ->with('success', "Se creó el producto «{$product->name}». Ahora registra la compra para agregarle unidades.");
    }

    public function edit(Product $product): View
    {
        return view('admin.products.edit', [
            'product' => $product,
            'categories' => Category::ordered()->pluck('name', 'id'),
            'lots' => $product->lots()->with('purchase:id,supplier')->where('remaining', '>', 0)
                ->orderBy('received_at')->orderBy('id')->get(),
            'adjustments' => $product->adjustments()->latest('adjusted_at')->latest('id')->limit(20)->get(),
        ]);
    }

    public function update(ProductRequest $request, Product $product): RedirectResponse
    {
        $data = [];
        $oldPhoto = $product->photo_path;

        if ($request->hasFile('photo')) {
            $data['photo_path'] = $this->photos->store($request->file('photo'));
        } elseif ($request->boolean('remove_photo')) {
            $data['photo_path'] = null;
        }

        DB::transaction(fn () => $product->update([...$request->productData(), ...$data]));

        if (array_key_exists('photo_path', $data) && $oldPhoto && $oldPhoto !== $data['photo_path']) {
            $this->photos->delete($oldPhoto);
        }

        return redirect()->route('admin.productos.index')
            ->with('success', "Se guardaron los cambios de «{$product->name}».");
    }

    public function destroy(Product $product): RedirectResponse
    {
        // Eliminación "suave": el producto desaparece del panel y del catálogo,
        // pero el historial de ventas lo sigue conociendo.
        $product->delete();

        return redirect()->route('admin.productos.index')
            ->with('success', "Se eliminó «{$product->name}».");
    }
}
