<?php

namespace App\Http\Requests\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $clean = fn ($v) => is_string($v) ? preg_replace('/[\s$.]/u', '', $v) : $v;

        $this->merge([
            'quantity' => $clean($this->input('quantity')),
            'unit_price' => $clean($this->input('unit_price')),
            'note' => trim((string) $this->input('note')) ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'unit_price' => ['required', 'integer', 'min:0', 'max:999999999999'],
            // Nota opcional: descuento aplicado, forma de pago, etc.
            'note' => ['nullable', 'string', 'max:200'],
            // Fecha y hora de la venta: permite registrar ventas pasadas, pero no futuras.
            'sold_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'before_or_equal:'.now()->addMinutes(5)->format('Y-m-d\TH:i')],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Elige el producto que vendiste.',
            'product_id.integer' => 'Elige un producto de la lista.',
            'product_id.exists' => 'Elige un producto de la lista.',
            'quantity.required' => 'Escribe cuántas unidades vendiste.',
            'quantity.integer' => 'La cantidad debe ser un número entero mayor que 0.',
            'quantity.min' => 'La cantidad debe ser un número entero mayor que 0.',
            'quantity.max' => 'La cantidad es demasiado grande.',
            'unit_price.required' => 'Escribe el precio al que vendiste cada unidad.',
            'unit_price.integer' => 'El precio debe ser un número mayor o igual a 0, sin decimales.',
            'unit_price.min' => 'El precio debe ser un número mayor o igual a 0, sin decimales.',
            'unit_price.max' => 'El precio es demasiado grande.',
            'sold_at.date_format' => 'La fecha de la venta no es válida.',
            'sold_at.before_or_equal' => 'La fecha de la venta no puede ser en el futuro.',
            'note.max' => 'La nota no puede tener más de 200 letras.',
        ];
    }

    public function soldAt(): ?CarbonImmutable
    {
        $value = $this->validated('sold_at');

        return $value ? CarbonImmutable::createFromFormat('Y-m-d\TH:i', $value) : null;
    }
}
