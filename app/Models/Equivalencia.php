<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una opción válida de convalidación, declarada por el especialista tras
 * comparar sílabos. No es una decisión sobre un estudiante concreto: es
 * política, y vale para todos los que vengan de esa carrera externa.
 *
 * carrera_externa_id no es un dato independiente: es una clave propagada que
 * la FK compuesta obliga a coincidir con la carrera del curso externo.
 */
class Equivalencia extends Model
{
    protected $table = 'equivalencias';

    public $incrementing = false;

    protected $primaryKey = null;

    protected $keyType = 'string';

    protected $fillable = [
        'curso_usil_id', 'curso_externo_id', 'carrera_externa_id', 'registrado_por_id',
    ];

    public function cursoUsil(): BelongsTo
    {
        return $this->belongsTo(CursoUsil::class, 'curso_usil_id');
    }

    public function cursoExterno(): BelongsTo
    {
        return $this->belongsTo(CursoExterno::class, 'curso_externo_id');
    }
}
