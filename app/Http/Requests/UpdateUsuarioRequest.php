<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUsuarioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->esAdministrador() ?? false;
    }

    public function rules(): array
    {
        $id = $this->route('usuario')->id ?? null;

        return [
            'nombre' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:150', Rule::unique('usuarios', 'email')->ignore($id)],
            'rol_id' => ['required', 'exists:roles,id'],
            'carreras' => ['array'],
            'carreras.*' => ['integer', 'exists:carreras,id'],
            'facultades' => ['array'],
            'facultades.*' => ['integer', 'exists:facultades,id'],
            'activo' => ['boolean'],
        ];
    }
}
