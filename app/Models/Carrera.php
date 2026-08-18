<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * PROGRAMA ACADÉMICO (carrera) dentro de la estructura institucional.
 */
class Carrera extends Model
{
    use SoftDeletes;

    protected $table = 'carreras';

    protected $fillable = [
        'facultad_id', 'nombre', 'codigo', 'max_ciclos', 'activo',
    ];

    protected $casts = ['activo' => 'boolean', 'max_ciclos' => 'integer'];

    public function facultad(): BelongsTo
    {
        return $this->belongsTo(Facultad::class);
    }

    public function mallas(): HasMany
    {
        return $this->hasMany(MallaCurricular::class);
    }
}
