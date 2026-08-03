<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PLAN DE ESTUDIOS de un programa académico, en una modalidad.
 * Nivel previo a la Malla Curricular.
 */
class PlanEstudio extends Model
{
    use SoftDeletes;

    protected $table = 'planes_estudio';

    protected $fillable = ['codigo', 'carrera_id', 'modalidad_id', 'nombre', 'anio', 'version', 'activo'];

    protected $casts = ['activo' => 'boolean', 'anio' => 'integer'];

    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class);
    }

    public function modalidad(): BelongsTo
    {
        return $this->belongsTo(Modalidad::class);
    }
}
