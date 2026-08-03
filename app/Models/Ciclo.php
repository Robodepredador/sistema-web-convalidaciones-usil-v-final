<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ciclo extends Model
{
    protected $table = 'ciclos';

    protected $fillable = ['malla_id', 'numero', 'nombre'];

    public function malla(): BelongsTo
    {
        return $this->belongsTo(MallaCurricular::class, 'malla_id');
    }

    public function cursos(): HasMany
    {
        return $this->hasMany(CursoUsil::class, 'ciclo_id');
    }
}
