<?php

/*
 |--------------------------------------------------------------------------
 | Añadir a config/services.php
 |--------------------------------------------------------------------------
 | La clave NO va en el código; se lee de variables de entorno (RNF-09).
 */
return [
    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o'),
    ],
];
