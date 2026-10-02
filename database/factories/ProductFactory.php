<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\StockLot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $cost = fake()->numberBetween(50, 2000) * 100;

        return [
            'name' => ucfirst(fake()->words(3, true)),
            'category_id' => Category::factory(),
            'description' => fake()->sentence(),
            'cost' => $cost,
            'price' => $cost + fake()->numberBetween(10, 1000) * 100,
            'stock' => fake()->numberBetween(1, 50),
            'is_visible' => true,
        ];
    }

    /**
     * El stock debe coincidir con los lotes: el stock del producto creado
     * queda como un lote de "inventario inicial" a su costo.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Product $product) {
            if ($product->stock > 0 && ! $product->lots()->exists()) {
                StockLot::create([
                    'product_id' => $product->id,
                    'source' => 'inicial',
                    'unit_cost' => $product->cost,
                    'quantity' => $product->stock,
                    'remaining' => $product->stock,
                    'received_at' => $product->created_at,
                ]);
            }
        });
    }
}
