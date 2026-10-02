<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Crear un administrador. Pide la contraseña actual de quien lo crea.
 */
class AdminUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'username' => strtolower(trim((string) $this->input('username'))),
            'email' => strtolower(trim((string) $this->input('email'))) ?: null,
        ]);
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'alpha_dash', 'min:3', 'max:50', Rule::unique('users', 'username')],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'current_password' => ['required', 'current_password'],
        ];
    }

    public function messages(): array
    {
        return AdminPasswordRequest::passwordMessages() + [
            'username.required' => 'Escribe el nombre de usuario.',
            'username.alpha_dash' => 'El usuario solo puede tener letras, números, guiones y guiones bajos (sin espacios).',
            'username.min' => 'El usuario debe tener al menos 3 caracteres.',
            'username.max' => 'El usuario no puede tener más de 50 caracteres.',
            'username.unique' => 'Ya existe un administrador con ese usuario.',
            'email.email' => 'Escribe un correo válido.',
            'email.unique' => 'Ya existe un administrador con ese correo.',
        ];
    }
}
