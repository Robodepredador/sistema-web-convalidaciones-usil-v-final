<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportarMallaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'carrera_id' => ['required', 'exists:carreras,id'],
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            'version' => ['required', 'string', 'max:20'],
            // RF-08: validar estructura (extensión y tamaño) antes del procesamiento.
            'archivo' => ['required', 'file', 'mimes:xlsx,xls', 'max:10240'],
        ];
    }
}
