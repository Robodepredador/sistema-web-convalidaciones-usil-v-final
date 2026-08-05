<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edición de una institución externa y sus carreras de procedencia (RF-18).
 */
class UpdateInstitucionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'tipo_id' => ['required', 'exists:tipos_institucion,id'],
            'nombre' => ['required', 'string', 'max:200'],
            'pais' => ['nullable', 'string', 'max:100'],
            'gestion' => ['nullable', 'in:publica,privada'],
            'licenciamiento' => ['nullable', 'in:licenciada,no_licenciada,desconocido'],
            'licenciamiento_resolucion' => ['nullable', 'string', 'max:120'],
            'activa' => ['boolean'],

            // Carreras de procedencia: cada una puede traer un id (existente) o no (nueva).
            'carreras' => ['array'],
            'carreras.*.id' => [
                'nullable',
                'integer',
                Rule::exists('carreras_externas', 'id')
                    ->where('institucion_id', $this->route('institucion')->id),
            ],
            'carreras.*.nombre' => ['required', 'string', 'max:200', 'distinct:ignore_case'],
        ];
    }

    public function messages(): array
    {
        return [
            'carreras.*.nombre.required' => 'El nombre de la carrera es obligatorio.',
            'carreras.*.nombre.max' => 'El nombre de la carrera no puede superar los 200 caracteres.',
            'carreras.*.nombre.distinct' => 'Hay carreras con nombres duplicados.',
        ];
    }
}
