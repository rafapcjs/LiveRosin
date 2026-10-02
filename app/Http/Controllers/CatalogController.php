<?php

namespace App\Http\Controllers;

use App\Catalog\CatalogItem;
use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Catálogo público. Se lee de la base de datos en cada visita (sin caché),
 * así cualquier cambio de precio o stock se ve al recargar.
 */
class CatalogController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $settings = Setting::current();
        $threshold = $settings->low_stock_threshold;

        $visible = Product::query()->where('is_visible', true); // los eliminados quedan fuera solos

        // Solo las categorías que tienen algo para mostrar.
        $categories = Category::ordered()
            ->whereIn('id', (clone $visible)->select('category_id'))
            ->get(['id', 'name', 'slug']);

        $slug = (string) $request->query('categoria', '');
        $current = $slug !== '' ? $categories->firstWhere('slug', $slug) : null;

        // Se envían TODOS los productos visibles: el filtro por categoría se aplica al instante
        // en el navegador (sin recargar). Si llega ?categoria=…, los demás van ocultos desde el servidor.
        $items = (clone $visible)
            ->with('category:id,name,slug')
            ->orderByRaw('CASE WHEN stock > 0 THEN 0 ELSE 1 END') // agotados al final
            ->orderBy('name')
            ->get()
            ->map(fn (Product $p) => CatalogItem::fromProduct($p, $threshold));

        // Búsqueda (?q=…). Funciona también sin JavaScript; con JavaScript se aplica mientras se escribe.
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 60);

        // Productos que deben verse con la categoría y la búsqueda pedidas; el resto va oculto.
        $shownIds = $items
            ->filter(fn (CatalogItem $i) => (! $current || $i->categoryId === $current->id) && ($search === '' || $i->matches($search)))
            ->pluck('id')
            ->all();

        // Cuántos productos tiene cada categoría (para el menú).
        $counts = $items->countBy('categoryId');

        // Última actualización: el cambio más reciente entre productos visibles (precio, stock por ventas…) y ajustes.
        $lastProduct = (clone $visible)->max('updated_at');
        $lastUpdate = collect([$lastProduct, $settings->updated_at])
            ->filter()
            ->map(fn ($d) => Carbon::parse($d))
            ->max();

        return response()
            ->view('catalog.index', [
                'items' => $items,
                'categories' => $categories,
                'current' => $current,
                'search' => $search,
                'shownIds' => array_flip($shownIds),
                'counts' => $counts,
                'unknownCategory' => $slug !== '' && ! $current,
                'storeName' => $settings->store_name,
                'tagline' => $settings->tagline,
                'whatsapp' => $settings->whatsapp,
                'lastUpdate' => $lastUpdate,
            ])
            ->header('Cache-Control', 'no-cache, private');
    }
}
