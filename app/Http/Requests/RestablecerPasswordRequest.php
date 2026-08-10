<?php

namespace App\Http\Requests;

use App\Rules\Correo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class RestablecerPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => Correo::reglas(),
            'password' => [
                'required', 'confirmed',
                Password::min(8)->letters()->numbers()->mixedCase()->symbols(),
            ],
        ];
    }
}
