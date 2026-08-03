<?php

namespace App\Http\Requests;

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
            'email' => ['required', 'email', 'max:150'],
            'password' => [
                'required', 'confirmed',
                Password::min(8)->letters()->numbers()->mixedCase()->symbols(),
            ],
        ];
    }
}
