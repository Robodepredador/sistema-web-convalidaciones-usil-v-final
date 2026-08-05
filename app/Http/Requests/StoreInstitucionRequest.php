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
            // Dato del proceso: el requisito de 72 créditos no aplica a
            // universidades no licenciadas, y el certificado exigido es el de SUNEDU.
            'licenciamiento' => ['nullable', 'in:licenciada,no_licenciada,desconocido'],
            'licenciamiento_resolucion' => ['nullable', 'string', 'max:120'],
            'activa' => ['boolean'],
            'carreras' => ['array'],
            'carreras.*.nombre' => ['required', 'string', 'max:200'],
        ];
    }
}
