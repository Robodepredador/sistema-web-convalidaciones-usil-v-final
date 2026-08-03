<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Edición de los datos generales de una malla (RF-05).
 * No modifica la carrera ni la estructura de ciclos/cursos.
 */
class UpdateMallaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $malla = $this->route('malla');

        return [
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            'version' => ['required', 'string', 'max:20'],
            'modalidad' => ['required', 'in:presencial,hibrido,virtual'],
            'periodo' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'activa' => ['boolean'],
            // RN-01 / RN-03: carrera + año + versión únicos (ignorando la malla actual).
            'version_unica' => [
                Rule::unique('mallas_curriculares', 'version')
                    ->where(fn ($q) => $q->where('carrera_id', $malla->carrera_id)->where('anio', $this->anio))
                    ->ignore($malla->id)
                    ->withoutTrashed(),
            ],
        ];
    }

    public function prepareForValidation(): void
    {
        $this->merge(['version_unica' => $this->version]);
    }

    public function messages(): array
    {
        return [
            'version_unica.unique' => 'Ya existe una malla para esa carrera, año y versión (RN-01 / RN-03).',
            'periodo.regex' => 'El periodo debe tener el formato AAAA-NN (por ejemplo, 2024-01).',
        ];
    }
}
