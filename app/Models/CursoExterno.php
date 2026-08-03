<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CursoExterno extends Model
{
    protected $table = 'cursos_externos';

    protected $fillable = ['malla_externa_id', 'codigo', 'nombre', 'creditos', 'silabo_texto'];

    protected $casts = ['creditos' => 'decimal:1'];

    public function mallaExterna(): BelongsTo
    {
        return $this->belongsTo(MallaExterna::class, 'malla_externa_id');
    }
}
