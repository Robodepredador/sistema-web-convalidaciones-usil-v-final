<?php

namespace App\Models;

use Illuminate\Auth\Authenticatable as AuthenticatableTrait;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Postulante: solicitante de convalidación por traslado externo.
 * Tiene acceso al portal de seguimiento (guard 'postulante').
 */
class Postulante extends Model implements Authenticatable
{
    use AuthenticatableTrait, SoftDeletes;

    protected $table = 'postulantes';

    protected $fillable = [
        'codigo', 'tipo_documento', 'numero_documento', 'nombres',
        'apellido_paterno', 'apellido_materno', 'fecha_nacimiento', 'genero', 'nacionalidad',
        'email', 'password_hash', 'acceso_habilitado', 'ultimo_acceso', 'telefono', 'pais_residencia', 'direccion',
        'institucion_origen_id', 'carrera_externa_id', 'carrera_destino_id', 'ciclo_postulacion',
        'estado', 'estado_equivalencias', 'equivalencias_revisado_por', 'equivalencias_revisado_en',
        'observaciones', 'usuario_id',
        'revision_estado', 'revision_observaciones', 'revisado_por', 'revisado_en',
    ];

    protected $hidden = ['password_hash'];

    protected $casts = [
        'fecha_nacimiento'           => 'date',
        'acceso_habilitado'          => 'boolean',
        'ultimo_acceso'              => 'datetime',
        'equivalencias_revisado_en'  => 'datetime',
        'revisado_en'                => 'datetime',
    ];

    // --- Auth (el esquema usa 'password_hash' y no remember_token) ---
    public function getAuthPassword(): string
    {
        return (string) $this->password_hash;
    }

    public function getAuthPasswordName(): string
    {
        return 'password_hash';
    }

    public function getRememberTokenName(): ?string
    {
        return null;
    }

    public function getNombreCompletoAttribute(): string
    {
        return trim("{$this->apellido_paterno} {$this->apellido_materno}, {$this->nombres}");
    }

    // --- Nombres propios: se guardan en formato Título aunque se ingresen en MAYÚSCULAS ---

    /** Convierte un nombre a formato propio (Título), respetando conectores y acentos. */
    public static function aNombrePropio(?string $texto): ?string
    {
        if ($texto === null || trim($texto) === '') {
            return $texto;
        }
        $menores = ['de', 'del', 'la', 'las', 'los', 'y', 'e', 'da', 'do', 'dos', 'van', 'von'];
        $palabras = preg_split('/\s+/', trim(mb_strtolower($texto, 'UTF-8')));
        $salida = [];
        foreach ($palabras as $i => $w) {
            if ($w === '') {
                continue;
            }
            if ($i > 0 && in_array($w, $menores, true)) {
                $salida[] = $w;
                continue;
            }
            $salida[] = mb_strtoupper(mb_substr($w, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($w, 1, null, 'UTF-8');
        }

        return implode(' ', $salida);
    }

    public function setNombresAttribute($v): void
    {
        $this->attributes['nombres'] = self::aNombrePropio($v);
    }

    public function setApellidoPaternoAttribute($v): void
    {
        $this->attributes['apellido_paterno'] = self::aNombrePropio($v);
    }

    public function setApellidoMaternoAttribute($v): void
    {
        $this->attributes['apellido_materno'] = self::aNombrePropio($v);
    }

    public function institucionOrigen(): BelongsTo
    {
        return $this->belongsTo(InstitucionExterna::class, 'institucion_origen_id');
    }

    public function carreraExterna(): BelongsTo
    {
        return $this->belongsTo(CarreraExterna::class, 'carrera_externa_id');
    }

    public function carreraDestino(): BelongsTo
    {
        return $this->belongsTo(Carrera::class, 'carrera_destino_id');
    }

    /** Destinos USIL solicitados (una o más carreras a convalidar). */
    public function destinos(): HasMany
    {
        return $this->hasMany(PostulanteDestino::class, 'postulante_id');
    }

    public function simulaciones(): HasMany
    {
        return $this->hasMany(Simulacion::class, 'postulante_id');
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(PostulanteDocumento::class, 'postulante_id');
    }

    public function revisadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }
}
