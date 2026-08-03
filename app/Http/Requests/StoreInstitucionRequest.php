<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInstitucionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'tipo_id' => ['required', 'exists:tipos_institucion,id'], // RF-18
            'nombre' => ['required', 'string', 'max:200'],
            'pais' => ['nullable', 'string', 'max:100'],
            'gestion' => ['nullable', 'in:publica,privada'],
            'activa' => ['boolean'],
            'carreras' => ['array'],
            'carreras.*.nombre' => ['required', 'string', 'max:200'],
        ];
    }
}
