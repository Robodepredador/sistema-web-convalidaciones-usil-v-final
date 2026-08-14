<?php

namespace App\Models;

use App\Services\ConvalidacionEngine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CursoExterno extends Model
{
    protected $table = 'cursos_externos';

    protected $fillable = ['carrera_externa_id', 'codigo', 'nombre', 'creditos', 'silabo_texto'];

    protected $casts = ['creditos' => 'decimal:1'];

    protected static function booted(): void
    {
        static::saving(function (CursoExterno $curso) {
            $curso->nombre_normalizado = (new ConvalidacionEngine)->normaliza($curso->nombre);
        });
    }

    public function carreraExterna(): BelongsTo
    {
        return $this->belongsTo(CarreraExterna::class, 'carrera_externa_id');
    }
}
