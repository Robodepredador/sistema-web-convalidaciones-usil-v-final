<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditoriaLog extends Model
{
    protected $table = 'auditoria_log';

    public $timestamps = false; // solo created_at gestionado manualmente

    protected $fillable = [
        'usuario_id', 'accion', 'tabla_afectada', 'registro_id',
        'valores_anteriores', 'valores_nuevos', 'ip_origen', 'created_at',
    ];

    protected $casts = [
        'valores_anteriores' => 'array',
        'valores_nuevos' => 'array',
        'created_at' => 'datetime',
    ];
}
