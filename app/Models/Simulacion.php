<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Simulacion extends Model
{
    use SoftDeletes;

    protected $table = 'simulaciones';

    protected $fillable = [
        'postulante_id', 'nombres', 'apellidos', 'tipo_documento', 'numero_documento', 'email',
        'telefono', 'ciclo_postulacion', 'carrera_externa_id', 'carrera_usil_id',
        'malla_usil_id', 'estado', 'metodo', 'pdf_path', 'documento_path',
        'universidad_origen', 'escala_notas', 'nota_minima', 'observaciones', 'motivo_eliminacion', 'usuario_id',
    ];

    protected $casts = ['nota_minima' => 'decimal:2'];

    public function detalles(): HasMany
    {
        return $this->hasMany(SimulacionDetalle::class, 'simulacion_id');
    }

    public function postulante(): BelongsTo
    {
        return $this->belongsTo(Postulante::class, 'postulante_id');
    }

    public function carreraExterna(): BelongsTo
    {
        return $this->belongsTo(CarreraExterna::class, 'carrera_externa_id');
    }

    public function convalidacion(): HasOne
    {
        return $this->hasOne(Convalidacion::class, 'simulacion_id');
    }

    public function carreraUsil(): BelongsTo
    {
        return $this->belongsTo(Carrera::class, 'carrera_usil_id');
    }
}
