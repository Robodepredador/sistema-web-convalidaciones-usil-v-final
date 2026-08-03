<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Convalidacion extends Model
{
    protected $table = 'convalidaciones';

    public const CONFIRMADA = 'confirmada';

    public const ANULADA = 'anulada';

    protected $fillable = [
        'simulacion_id', 'fecha_confirmacion', 'memorandum_numero',
        'memorandum_pdf_path', 'estado', 'motivo_anulacion', 'usuario_id',
    ];

    protected $casts = ['fecha_confirmacion' => 'date'];

    public function simulacion(): BelongsTo
    {
        return $this->belongsTo(Simulacion::class, 'simulacion_id');
    }
}
