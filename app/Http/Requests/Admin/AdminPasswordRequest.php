<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

/**
 * Cambiar la contraseña de un administrador. Pide la contraseña actual de quien hace el cambio.
 */
class AdminPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers()],
            'current_password' => ['required', 'current_password'],
        ];
    }

    public function messages(): array
    {
        return self::passwordMessages();
    }

    /** @return array<string, string> */
    public static function passwordMessages(): array
    {
        return [
            'password.required' => 'Escribe la contraseña nueva.',
            'password.confirmed' => 'Las dos contraseñas nuevas no coinciden.',
            'password.min' => 'La contraseña debe tener al menos 10 caracteres.',
            'password.letters' => 'La contraseña debe tener al menos una letra.',
            'password.numbers' => 'La contraseña debe tener al menos un número.',
            'current_password.required' => 'Escribe tu contraseña actual para confirmar.',
            'current_password.current_password' => 'Tu contraseña actual no es correcta.',
        ];
    }
}
