<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MallaCurricular extends Model
{
    use SoftDeletes;

    protected $table = 'mallas_curriculares';

    protected $fillable = ['carrera_id', 'anio', 'version', 'modalidad', 'periodo', 'activa', 'origen_carga', 'usuario_id'];

    protected $casts = ['activa' => 'boolean', 'anio' => 'integer'];

    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class);
    }

    public function ciclos(): HasMany
    {
        return $this->hasMany(Ciclo::class, 'malla_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
