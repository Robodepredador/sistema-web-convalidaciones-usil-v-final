<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CarreraExterna extends Model
{
    protected $table = 'carreras_externas';

    protected $fillable = ['institucion_id', 'nombre'];

    public function institucion(): BelongsTo
    {
        return $this->belongsTo(InstitucionExterna::class, 'institucion_id');
    }

    public function mallas(): HasMany
    {
        return $this->hasMany(MallaExterna::class, 'carrera_externa_id');
    }
}
