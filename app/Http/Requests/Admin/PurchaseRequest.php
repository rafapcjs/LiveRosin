<?php

namespace App\Http\Requests\Admin;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PurchaseRequest extends FormRequest
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
            'unit_cost' => $clean($this->input('unit_cost')),
            'supplier' => trim((string) $this->input('supplier')) ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'unit_cost' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'supplier' => ['nullable', 'string', 'max:120'],
            'purchased_at' => ['nullable', 'date_format:Y-m-d\TH:i', 'before_or_equal:'.now()->addMinutes(5)->format('Y-m-d\TH:i')],
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'Elige el producto que compraste.',
            'product_id.integer' => 'Elige un producto de la lista.',
            'product_id.exists' => 'Elige un producto de la lista.',
            'quantity.required' => 'Escribe cuántas unidades compraste.',
            'quantity.integer' => 'La cantidad debe ser un número entero mayor que 0.',
            'quantity.min' => 'La cantidad debe ser un número entero mayor que 0.',
            'quantity.max' => 'La cantidad es demasiado grande.',
            'unit_cost.required' => 'Escribe cuánto te costó cada unidad.',
            'unit_cost.integer' => 'El costo debe ser un número mayor o igual a 0, sin decimales.',
            'unit_cost.min' => 'El costo debe ser un número mayor o igual a 0, sin decimales.',
            'unit_cost.max' => 'El costo es demasiado grande.',
            'supplier.max' => 'El proveedor no puede tener más de 120 letras.',
            'purchased_at.date_format' => 'La fecha de la compra no es válida.',
            'purchased_at.before_or_equal' => 'La fecha de la compra no puede ser en el futuro.',
        ];
    }

    public function purchasedAt(): ?CarbonImmutable
    {
        $value = $this->validated('purchased_at');

        return $value ? CarbonImmutable::createFromFormat('Y-m-d\TH:i', $value) : null;
    }
}
