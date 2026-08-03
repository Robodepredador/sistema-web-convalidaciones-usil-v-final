<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * SEDE / campus institucional (tope de la estructura institucional).
 * Conserva el nombre de tabla 'unidades_negocio' por compatibilidad.
 */
class UnidadNegocio extends Model
{
    use SoftDeletes;

    protected $table = 'unidades_negocio';

    protected $fillable = ['codigo', 'nombre', 'direccion', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function facultades(): HasMany
    {
        return $this->hasMany(Facultad::class);
    }
}
