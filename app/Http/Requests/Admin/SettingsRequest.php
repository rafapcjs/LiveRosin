<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        // WhatsApp: se aceptan espacios, guiones, paréntesis y el +57 al inicio.
        $phone = preg_replace('/\D+/', '', (string) $this->input('whatsapp'));
        if (strlen($phone) === 12 && str_starts_with($phone, '57')) {
            $phone = substr($phone, 2);
        }

        $this->merge([
            'store_name' => trim((string) $this->input('store_name')),
            'tagline' => trim((string) $this->input('tagline')) ?: null,
            'whatsapp' => $phone,
            'low_stock_threshold' => trim((string) $this->input('low_stock_threshold')),
        ]);
    }

    public function rules(): array
    {
        return [
            'store_name' => ['required', 'string', 'max:80'],
            'tagline' => ['nullable', 'string', 'max:140'],
            'whatsapp' => ['required', 'regex:/^3\d{9}$/'],
            'low_stock_threshold' => ['required', 'integer', 'min:1', 'max:100000'],
        ];
    }

    public function messages(): array
    {
        return [
            'store_name.required' => 'Escribe el nombre de la tienda.',
            'store_name.max' => 'El nombre de la tienda no puede tener más de 80 letras.',
            'tagline.max' => 'La frase no puede tener más de 140 letras.',
            'whatsapp.required' => 'Escribe el número de WhatsApp donde recibes los pedidos.',
            'whatsapp.regex' => 'El número debe ser un celular colombiano de 10 dígitos que empiece por 3. Ej: 300 123 4567.',
            'low_stock_threshold.required' => 'Escribe desde cuántas unidades quieres la alerta.',
            'low_stock_threshold.integer' => 'Debe ser un número entero mayor o igual a 1.',
            'low_stock_threshold.min' => 'Debe ser un número entero mayor o igual a 1.',
            'low_stock_threshold.max' => 'El número es demasiado grande.',
        ];
    }

    /** Datos listos para guardar: el WhatsApp se guarda como 57 + número. */
    public function settingsData(): array
    {
        $data = $this->validated();
        $data['whatsapp'] = '57'.$data['whatsapp'];

        return $data;
    }
}
