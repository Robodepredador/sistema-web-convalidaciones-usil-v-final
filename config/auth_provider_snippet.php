<?php

use App\Models\User;

/*
 |--------------------------------------------------------------------------
 | Fragmento para config/auth.php
 |--------------------------------------------------------------------------
 | Reemplazar el provider 'users' por el modelo y tabla del proyecto.
 */
return [
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => User::class, // mapeado a la tabla 'usuarios'
        ],
    ],
];
