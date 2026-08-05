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

    /**
     * ¿El expediente ya produjo un acto oficial y por tanto no admite EDICIÓN?
     *
     * Cuenta también la convalidación ANULADA: el memorándum anulado se conserva
     * (RF-46) y su detalle es la evidencia de lo que certificó. Reescribirlo
     * dejaría la resolución archivada diciendo una cosa y la base de datos otra.
     * Una corrección se hace en una simulación nueva, no encima de esta.
     */
    public function estaCerrada(): bool
    {
        return $this->convalidacion()->exists();
    }

    /**
     * ¿Sustenta un memorándum VIGENTE? Regla del borrado, más laxa que la de la
     * edición: archivar un expediente cuya resolución ya se anuló es legítimo;
     * dejar sin respaldo una resolución en vigor, no.
     */
    public function tieneConvalidacionVigente(): bool
    {
        return $this->convalidacion()->where('estado', Convalidacion::CONFIRMADA)->exists();
    }

    public function carreraUsil(): BelongsTo
    {
        return $this->belongsTo(Carrera::class, 'carrera_usil_id');
    }
}
