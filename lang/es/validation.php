<?php

/**
 * Mensajes de validación en español. La aplicación es de cara al usuario en
 * español; Laravel 11 no incluye traducciones, así que se cubren las reglas que
 * el sistema realmente usa (ver los FormRequest de app/Http/Requests).
 */
return [
    'accepted' => 'Debe aceptar :attribute.',
    'after' => ':Attribute debe ser una fecha posterior a :date.',
    'after_or_equal' => ':Attribute debe ser una fecha posterior o igual a :date.',
    'alpha' => ':Attribute solo puede contener letras.',
    'alpha_dash' => ':Attribute solo puede contener letras, números, guiones y guiones bajos.',
    'alpha_num' => ':Attribute solo puede contener letras y números.',
    'array' => ':Attribute debe ser una lista.',
    'before' => ':Attribute debe ser una fecha anterior a :date.',
    'before_or_equal' => ':Attribute debe ser una fecha anterior o igual a :date.',
    'between' => [
        'array' => ':Attribute debe tener entre :min y :max elementos.',
        'file' => ':Attribute debe pesar entre :min y :max kilobytes.',
        'numeric' => ':Attribute debe estar entre :min y :max.',
        'string' => ':Attribute debe tener entre :min y :max caracteres.',
    ],
    'boolean' => 'El campo :attribute debe ser verdadero o falso.',
    'confirmed' => 'La confirmación de :attribute no coincide.',
    'current_password' => 'La contraseña es incorrecta.',
    'date' => ':Attribute no es una fecha válida.',
    'date_format' => ':Attribute no corresponde al formato :format.',
    'different' => ':Attribute y :other deben ser diferentes.',
    'digits' => ':Attribute debe tener :digits dígitos.',
    'digits_between' => ':Attribute debe tener entre :min y :max dígitos.',
    'email' => ':Attribute debe ser una dirección de correo válida.',
    'exists' => ':Attribute seleccionado no es válido.',
    'file' => ':Attribute debe ser un archivo.',
    'filled' => 'El campo :attribute es obligatorio.',
    'gt' => [
        'file' => ':Attribute debe pesar más de :value kilobytes.',
        'numeric' => ':Attribute debe ser mayor que :value.',
        'string' => ':Attribute debe tener más de :value caracteres.',
    ],
    'gte' => [
        'numeric' => ':Attribute debe ser mayor o igual que :value.',
    ],
    'image' => ':Attribute debe ser una imagen.',
    'in' => ':Attribute seleccionado no es válido.',
    'integer' => ':Attribute debe ser un número entero.',
    'lt' => [
        'numeric' => ':Attribute debe ser menor que :value.',
    ],
    'lte' => [
        'numeric' => ':Attribute debe ser menor o igual que :value.',
    ],
    'max' => [
        'array' => ':Attribute no debe tener más de :max elementos.',
        'file' => ':Attribute no debe pesar más de :max kilobytes.',
        'numeric' => ':Attribute no debe ser mayor que :max.',
        'string' => ':Attribute no debe tener más de :max caracteres.',
    ],
    'mimes' => ':Attribute debe ser un archivo de tipo: :values.',
    'mimetypes' => ':Attribute debe ser un archivo de tipo: :values.',
    'min' => [
        'array' => ':Attribute debe tener al menos :min elementos.',
        'file' => ':Attribute debe pesar al menos :min kilobytes.',
        'numeric' => ':Attribute debe ser al menos :min.',
        'string' => ':Attribute debe tener al menos :min caracteres.',
    ],
    'not_in' => ':Attribute seleccionado no es válido.',
    'numeric' => ':Attribute debe ser un número.',
    'present' => 'El campo :attribute debe estar presente.',
    'regex' => 'El formato de :attribute no es válido.',
    'required' => 'El campo :attribute es obligatorio.',
    'required_if' => 'El campo :attribute es obligatorio cuando :other es :value.',
    'required_with' => 'El campo :attribute es obligatorio cuando :values está presente.',
    'required_without' => 'El campo :attribute es obligatorio cuando :values no está presente.',
    'same' => ':Attribute y :other deben coincidir.',
    'size' => [
        'file' => ':Attribute debe pesar :size kilobytes.',
        'numeric' => ':Attribute debe ser :size.',
        'string' => ':Attribute debe tener :size caracteres.',
    ],
    'string' => ':Attribute debe ser texto.',
    'unique' => ':Attribute ya está registrado.',
    'uploaded' => 'Falló la carga de :attribute.',
    'url' => 'El formato de :attribute no es válido.',

    'custom' => [
        'password' => [
            'min' => 'La contraseña debe tener al menos :min caracteres.',
        ],
    ],

    /**
     * Nombres legibles: sin esto el mensaje muestra la clave cruda
     * ("carrera_usil_id" en vez de "carrera destino").
     */
    'attributes' => [
        'email' => 'correo',
        'password' => 'contraseña',
        'password_confirmation' => 'confirmación de contraseña',
        'nombre' => 'nombre',
        'nombres' => 'nombres',
        'apellido_paterno' => 'apellido paterno',
        'apellido_materno' => 'apellido materno',
        'tipo_documento' => 'tipo de documento',
        'numero_documento' => 'número de documento',
        'telefono' => 'teléfono',
        'rol_id' => 'rol',
        'carrera_id' => 'carrera',
        'carrera_usil_id' => 'carrera destino',
        'carrera_externa_id' => 'carrera de origen',
        'carrera_destino_id' => 'carrera destino',
        'institucion_id' => 'institución',
        'institucion_origen_id' => 'institución de origen',
        'malla_id' => 'malla curricular',
        'malla_externa_id' => 'malla externa',
        'curso_usil_id' => 'curso USIL',
        'curso_externo_id' => 'curso de origen',
        'ciclo_postulacion' => 'ciclo de postulación',
        'motivo' => 'motivo',
        'observaciones' => 'observaciones',
        'anio' => 'año',
        'creditos' => 'créditos',
        'documento' => 'documento',
    ],
];
