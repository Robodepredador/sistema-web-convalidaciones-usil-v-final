<?php

namespace App\Http\Requests;

use App\Rules\Correo;
use Illuminate\Foundation\Http\FormRequest;

class StoreUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->esAdministrador() ?? false; // CU-10: solo Administrador
    }

    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:100'],
            'email' => [...Correo::reglas(), 'unique:usuarios,email'],
            'rol_id' => ['required', 'exists:roles,id'],
            'carreras' => ['array'],            // alcance carrera (Coordinador/Director)
            'carreras.*' => ['integer', 'exists:carreras,id'],
            'facultades' => ['array'],            // alcance facultad (Decano)
            'facultades.*' => ['integer', 'exists:facultades,id'],
            'activo' => ['boolean'],
        ];
    }
}
