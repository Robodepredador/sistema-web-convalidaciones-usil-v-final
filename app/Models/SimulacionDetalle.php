<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SimulacionDetalle extends Model
{
    protected $table = 'simulacion_detalle';

    protected $fillable = [
        'simulacion_id', 'curso_usil_id', 'curso_externo_id',
        'curso_origen_nombre', 'nota_origen', 'creditos_origen', 'ciclo_origen',
        'clasificacion', 'motivo', 'confianza', 'creditos_reconocidos', 'excluido', 'origen',
    ];

    protected $casts = [
        'excluido' => 'boolean',
        'creditos_reconocidos' => 'decimal:1',
        'creditos_origen' => 'decimal:1',
        'confianza' => 'decimal:1',
    ];

    public function simulacion(): BelongsTo
    {
        return $this->belongsTo(Simulacion::class, 'simulacion_id');
    }

    public function cursoUsil(): BelongsTo
    {
        return $this->belongsTo(CursoUsil::class, 'curso_usil_id');
    }

    public function cursoExterno(): BelongsTo
    {
        return $this->belongsTo(CursoExterno::class, 'curso_externo_id');
    }

    /** Nombre del curso de origen: enlazado o snapshot. */
    public function getNombreOrigenAttribute(): ?string
    {
        return $this->cursoExterno?->nombre ?? $this->curso_origen_nombre;
    }
}
