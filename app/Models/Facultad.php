<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Facultad extends Model
{
    use SoftDeletes;

    protected $table = 'facultades';

    protected $fillable = ['unidad_negocio_id', 'nombre', 'codigo', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function unidadNegocio(): BelongsTo
    {
        return $this->belongsTo(UnidadNegocio::class);
    }

    /** Alias de dominio: la Sede de la facultad. */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(UnidadNegocio::class, 'unidad_negocio_id');
    }

    public function carreras(): HasMany
    {
        return $this->hasMany(Carrera::class);
    }
}
