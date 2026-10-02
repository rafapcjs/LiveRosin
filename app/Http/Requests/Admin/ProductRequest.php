<?php

namespace App\Http\Requests\Admin;

use App\Models\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** Quita "$", espacios y puntos de miles para aceptar "1.250.000". */
    protected function prepareForValidation(): void
    {
        $clean = fn ($v) => is_string($v) ? preg_replace('/[\s$.]/u', '', $v) : $v;

        $this->merge([
            'cost' => $clean($this->input('cost')),
            'price' => $clean($this->input('price')),
            'stock' => $clean($this->input('stock')),
            'is_visible' => $this->boolean('is_visible'),
            'remove_photo' => $this->boolean('remove_photo'),
            'new_category' => trim(preg_replace('/\s+/u', ' ', (string) $this->input('new_category'))),
        ]);
    }

    /** Valor especial de la lista de categorías: "Crear nueva categoría…". */
    public const NEW_CATEGORY = 'nueva';

    public function wantsNewCategory(): bool
    {
        return $this->input('category_id') === self::NEW_CATEGORY;
    }

    /**
     * Devuelve la categoría elegida. Si se pidió una nueva, la crea
     * (o reutiliza la que ya exista con el mismo nombre).
     */
    public function categoryId(): int
    {
        if (! $this->wantsNewCategory()) {
            return (int) $this->validated('category_id');
        }

        $name = $this->validated('new_category');
        $existing = Category::whereRaw('LOWER(name) = LOWER(?)', [$name])->first();

        return ($existing ?? Category::create(['name' => $name]))->id;
    }

    /** ¿Es un producto nuevo? Solo al crear se escribe el costo de referencia. */
    public function isCreating(): bool
    {
        return $this->route('product') === null;
    }

    /**
     * Datos del producto listos para guardar. El stock y el costo NO van aquí:
     * se manejan con lotes (inventario inicial, compras, ventas y ajustes).
     */
    public function productData(): array
    {
        return [
            ...$this->safe()->except(['photo', 'remove_photo', 'new_category', 'category_id', 'cost', 'stock']),
            'category_id' => $this->categoryId(),
        ];
    }

    public function rules(): array
    {
        $maxMoney = 999_999_999_999; // casi un billón de pesos

        return [
            'name' => ['required', 'string', 'max:150'],
            'category_id' => $this->wantsNewCategory()
                ? ['required']
                : ['required', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at')],
            'new_category' => $this->wantsNewCategory()
                ? ['required', 'string', 'max:60']
                : ['nullable'],
            'description' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', 'max:4096'],
            'remove_photo' => ['boolean'],
            'price' => ['required', 'integer', 'min:0', 'max:'.$maxMoney],
            'is_visible' => ['boolean'],
            // El costo solo se pide al crear, como referencia para calcular el precio.
            // El stock nunca se escribe aquí: entra con Compras.
            ...($this->isCreating() ? [
                'cost' => ['required', 'integer', 'min:0', 'max:'.$maxMoney],
            ] : []),
        ];
    }

    public function messages(): array
    {
        $money = fn (string $f) => "$f debe ser un número mayor o igual a 0, sin decimales.";

        return [
            'name.required' => 'El nombre es obligatorio.',
            'name.max' => 'El nombre no puede tener más de 150 letras.',
            'category_id.required' => 'Elige una categoría.',
            'category_id.integer' => 'Elige una categoría de la lista.',
            'category_id.exists' => 'Elige una categoría de la lista.',
            'new_category.required' => 'Escribe el nombre de la nueva categoría.',
            'new_category.max' => 'El nombre de la categoría no puede tener más de 60 letras.',
            'description.max' => 'La descripción no puede tener más de 2000 letras.',
            'photo.file' => 'No se pudo leer la foto. Intenta con otra.',
            'photo.uploaded' => 'No se pudo subir la foto. Revisa que pese menos de 4 MB.',
            'photo.mimes' => 'La foto debe ser JPG, PNG o WEBP.',
            'photo.mimetypes' => 'La foto debe ser JPG, PNG o WEBP.',
            'photo.max' => 'La foto pesa más de 4 MB. Elige una más liviana.',
            'cost.required' => 'El costo es obligatorio.',
            'cost.integer' => $money('El costo'),
            'cost.min' => $money('El costo'),
            'cost.max' => 'El costo es demasiado grande.',
            'price.required' => 'El precio es obligatorio.',
            'price.integer' => $money('El precio'),
            'price.min' => $money('El precio'),
            'price.max' => 'El precio es demasiado grande.',
            'stock.required' => 'El stock es obligatorio (escribe 0 si no hay unidades).',
            'stock.integer' => 'El stock debe ser un número entero mayor o igual a 0.',
            'stock.min' => 'El stock debe ser un número entero mayor o igual a 0.',
            'stock.max' => 'El stock es demasiado grande.',
        ];
    }
}
