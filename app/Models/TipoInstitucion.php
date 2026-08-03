<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TipoInstitucion extends Model
{
    protected $table = 'tipos_institucion';

    protected $fillable = ['nombre'];

    public function instituciones(): HasMany
    {
        return $this->hasMany(InstitucionExterna::class, 'tipo_id');
    }
}
