<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CursoUsil extends Model
{
    use SoftDeletes;

    protected $table = 'cursos_usil';

    protected $fillable = [
        'ciclo_id', 'codigo', 'nombre', 'creditos', 'horas_teoria',
        'horas_practica', 'es_electivo', 'convalidable', 'mencion',
        'prerequisito_id', 'prerequisito_texto', 'silabo_texto',
        'tipo_curso', 'area', 'competencias', 'resultados_aprendizaje',
    ];

    protected $casts = [
        'es_electivo' => 'boolean',
        'convalidable' => 'boolean',
        'creditos' => 'decimal:1',
        'competencias' => 'array',
    ];

    public function ciclo(): BelongsTo
    {
        return $this->belongsTo(Ciclo::class);
    }

    public function prerequisito(): BelongsTo
    {
        return $this->belongsTo(CursoUsil::class, 'prerequisito_id');
    }

    /** Detalles de simulación/convalidación que reconocen este curso. */
    public function detallesSimulacion(): HasMany
    {
        return $this->hasMany(SimulacionDetalle::class, 'curso_usil_id');
    }
}
