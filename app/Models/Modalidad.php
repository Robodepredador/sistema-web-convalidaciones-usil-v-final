<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * MODALIDAD de estudio (Presencial, Semipresencial, Virtual).
 */
class Modalidad extends Model
{
    use SoftDeletes;

    protected $table = 'modalidades';

    protected $fillable = ['codigo', 'nombre', 'activo'];

    protected $casts = ['activo' => 'boolean'];
}
