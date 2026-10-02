<?php

namespace App\Http\Requests\Admin;

use App\Models\InventoryAdjustment;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustmentRequest extends FormRequest
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
            'note' => trim((string) $this->input('note')) ?: null,
        ]);
    }

    public function isOut(): bool
    {
        return $this->input('type') !== 'alta';
    }

    public function rules(): array
    {
        $reasons = array_keys($this->isOut() ? InventoryAdjustment::REASONS_OUT : InventoryAdjustment::REASONS_IN);

        return [
            'type' => ['required', Rule::in(['baja', 'alta'])],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000000'],
            'reason' => ['required', Rule::in($reasons)],
            'note' => ['nullable', 'string', 'max:200'],
            'unit_cost' => $this->isOut() ? ['nullable'] : ['required', 'integer', 'min:0', 'max:999999999999'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Elige si vas a sacar o agregar unidades.',
            'type.in' => 'Elige si vas a sacar o agregar unidades.',
            'quantity.required' => 'Escribe cuántas unidades.',
            'quantity.integer' => 'La cantidad debe ser un número entero mayor que 0.',
            'quantity.min' => 'La cantidad debe ser un número entero mayor que 0.',
            'quantity.max' => 'La cantidad es demasiado grande.',
            'reason.required' => 'Elige el motivo.',
            'reason.in' => 'Elige un motivo de la lista.',
            'note.max' => 'La nota no puede tener más de 200 letras.',
            'unit_cost.required' => 'Escribe el costo por unidad (puede ser 0 si fue regalo).',
            'unit_cost.integer' => 'El costo debe ser un número mayor o igual a 0, sin decimales.',
            'unit_cost.min' => 'El costo debe ser un número mayor o igual a 0, sin decimales.',
        ];
    }
}
