<?php

namespace App\Http\Requests;

use App\Models\Carrera;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreMallaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null; // RBAC fino se valida con policy en el controlador
    }

    public function rules(): array
    {
        return [
            'carrera_id' => ['required', 'exists:carreras,id'],
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            'version' => ['required', 'string', 'max:20'],
            'modalidad' => ['required', 'in:presencial,hibrido,virtual'],
            'periodo' => ['nullable', 'string', 'regex:/^\d{4}-\d{2}$/'],
            'activa' => ['boolean'],
            // RN-01 y RN-03: la combinación carrera + año + versión no debe existir
            'version_unica' => [
                Rule::unique('mallas_curriculares', 'version')
                    ->where(fn ($q) => $q->where('carrera_id', $this->carrera_id)->where('anio', $this->anio))
                    ->withoutTrashed(),
            ],
            'ciclos' => ['required', 'array', 'min:1'],
            'ciclos.*.numero' => ['required', 'integer', 'min:1', 'max:14'],
            'ciclos.*.cursos' => ['array'],
            'ciclos.*.cursos.*.codigo' => ['required', 'string', 'max:30'],
            'ciclos.*.cursos.*.nombre' => ['required', 'string', 'max:200'],
            'ciclos.*.cursos.*.creditos' => ['required', 'numeric', 'min:0.5', 'max:30'],
        ];
    }

    public function prepareForValidation(): void
    {
        // Para que la regla 'version_unica' tenga un valor que validar.
        $this->merge(['version_unica' => $this->version]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            // RF-07: no exceder el máximo de ciclos de la carrera (10 o 14)
            $carrera = Carrera::find($this->carrera_id);
            if ($carrera) {
                foreach ((array) $this->ciclos as $i => $ciclo) {
                    if (($ciclo['numero'] ?? 0) > $carrera->max_ciclos) {
                        $validator->errors()->add(
                            "ciclos.$i.numero",
                            "El ciclo excede el máximo permitido para la carrera ({$carrera->max_ciclos})."
                        );
                    }
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'version_unica.unique' => 'Ya existe una malla para esa carrera, año y versión (RN-01 / RN-03).',
        ];
    }
}
