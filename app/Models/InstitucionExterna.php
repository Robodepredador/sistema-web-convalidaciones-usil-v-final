<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InstitucionExterna extends Model
{
    protected $table = 'instituciones_externas';

    protected $fillable = [
        'tipo_id', 'nombre', 'pais', 'gestion',
        'licenciamiento', 'licenciamiento_resolucion', 'activa',
    ];

    protected $casts = ['activa' => 'boolean'];

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(TipoInstitucion::class, 'tipo_id');
    }

    public function carreras(): HasMany
    {
        return $this->hasMany(CarreraExterna::class, 'institucion_id');
    }
}
