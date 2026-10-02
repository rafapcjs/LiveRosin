<?php

namespace App\Catalog;

use App\Models\Product;

/**
 * Lo ÚNICO que el catálogo público sabe de un producto.
 *
 * Privacidad: aquí no existen el costo, la ganancia, el margen, el stock exacto
 * ni las ventas. Las vistas públicas reciben solo estos objetos, nunca el modelo completo.
 */
final class CatalogItem
{
    public const AVAILABLE = 'disponible';

    public const LAST_UNITS = 'ultimas';

    public const SOLD_OUT = 'agotado';

    private function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly string $category,
        public readonly int $categoryId,
        public readonly string $categorySlug,
        public readonly ?string $description,
        public readonly ?string $photoUrl,
        public readonly ?string $thumbUrl,
        public readonly int $price,
        public readonly string $status,
        public readonly bool $canOrder,
    ) {}

    public static function fromProduct(Product $product, int $lowStockThreshold): self
    {
        $status = match (true) {
            $product->stock <= 0 => self::SOLD_OUT,
            $product->stock <= $lowStockThreshold => self::LAST_UNITS,
            default => self::AVAILABLE,
        };

        return new self(
            id: $product->id,
            name: $product->name,
            category: $product->category?->name ?? '',
            categoryId: (int) $product->category_id,
            categorySlug: $product->category?->slug ?? '',
            description: $product->description,
            photoUrl: $product->photoUrl(),
            thumbUrl: $product->thumbUrl(),
            price: $product->price,
            status: $status,
            canOrder: $status !== self::SOLD_OUT,
        );
    }

    /** Texto para el buscador: nombre, descripción y categoría, sin tildes y en minúsculas. */
    public function searchText(): string
    {
        return self::normalize($this->name.' '.$this->description.' '.$this->category);
    }

    public static function normalize(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', \Illuminate\Support\Str::lower(\Illuminate\Support\Str::ascii($text))));
    }

    /** ¿Coincide con lo que se buscó? Cada palabra buscada debe aparecer. */
    public function matches(string $query): bool
    {
        $haystack = $this->searchText();
        foreach (explode(' ', self::normalize($query)) as $word) {
            if ($word !== '' && ! str_contains($haystack, $word)) {
                return false;
            }
        }

        return true;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::AVAILABLE => 'Disponible',
            self::LAST_UNITS => 'Últimas unidades',
            default => 'Agotado',
        };
    }

    /** Enlace de WhatsApp con el mensaje ya escrito. Null si no hay número configurado o está agotado. */
    public function whatsappUrl(?string $whatsapp): ?string
    {
        if (! $this->canOrder || ! $whatsapp) {
            return null;
        }

        $text = "Hola, quiero pedir: {$this->name} - ".cop($this->price);

        return 'https://wa.me/'.$whatsapp.'?text='.rawurlencode($text);
    }
}
