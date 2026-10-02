<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Services\InventoryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Categorías y productos de ejemplo para probar la tienda. Puedes borrarlos desde el panel.
 */
class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [];
        foreach (['Destilados', 'Vapers', 'Otras'] as $name) {
            $categories[$name] = Category::firstOrCreate(['name' => $name])->id;
        }

        $products = [
            ['Aguardiente Antioqueño Tapa Azul 750 ml', 'Destilados', 'Aguardiente sin azúcar, suave y tradicional.', 38000, 52000, 24],
            ['Ron Viejo de Caldas 5 años 750 ml', 'Destilados', 'Ron añejo colombiano, notas de madera y vainilla.', 45000, 65000, 12],
            ['Whisky Old Parr 12 años 750 ml', 'Destilados', 'Whisky escocés blend, clásico para compartir.', 120000, 165000, 3],
            ['Tequila José Cuervo Especial 750 ml', 'Destilados', 'Tequila reposado, ideal para cócteles.', 70000, 98000, 0],
            ['Vaper desechable 5000 puffs sabor mango', 'Vapers', 'Desechable recargable, batería de larga duración.', 28000, 45000, 15],
            ['Vaper desechable 8000 puffs sabor uva', 'Vapers', 'Sabor intenso, pantalla de batería.', 35000, 55000, 2],
            ['Líquido para vaper 30 ml menta', 'Vapers', 'Sales de nicotina 20 mg.', 18000, 30000, 10],
            ['Vasos de shot x 12', 'Otras', 'Vasos de vidrio para shots.', 15000, 25000, 8],
            ['Hielo en bolsa 2 kg', 'Otras', null, 3500, 6000, 20],
            ['Encendedor recargable', 'Otras', 'Encendedor metálico.', 9000, 8000, 5],
        ];

        $inventory = app(InventoryService::class);

        foreach ($products as [$name, $category, $description, $cost, $price, $stock]) {
            if (Product::withTrashed()->where('name', $name)->exists()) {
                continue;
            }

            DB::transaction(function () use ($inventory, $name, $categories, $category, $description, $cost, $price, $stock) {
                $product = Product::create([
                    'name' => $name,
                    'category_id' => $categories[$category],
                    'description' => $description,
                    'cost' => $cost,
                    'price' => $price,
                    'stock' => 0,
                    'is_visible' => true,
                ]);

                if ($stock > 0) {
                    $inventory->addLot($product, $stock, $cost, 'inicial');
                }
            });
        }
    }
}
