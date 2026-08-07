<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un par curso externo ↔ curso USIL declarado por el coordinador.
 *
 * Sin `SoftDeletes` a propósito: ver la migración que crea la tabla.
 */
class EquivalenciaMalla extends Model
{
    protected $table = 'equivalencias_malla';

    protected $fillable = [
        'curso_externo_id', 'curso_usil_id',
        'malla_externa_id', 'malla_usil_id', 'usuario_id',
    ];

    public function cursoExterno(): BelongsTo
    {
        return $this->belongsTo(CursoExterno::class, 'curso_externo_id');
    }

    public function cursoUsil(): BelongsTo
    {
        return $this->belongsTo(CursoUsil::class, 'curso_usil_id');
    }

    public function mallaExterna(): BelongsTo
    {
        return $this->belongsTo(MallaExterna::class, 'malla_externa_id');
    }

    public function mallaUsil(): BelongsTo
    {
        return $this->belongsTo(MallaCurricular::class, 'malla_usil_id');
    }
}
