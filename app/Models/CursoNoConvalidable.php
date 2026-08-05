<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

/**
 * Palabra o frase clave de un curso de ORIGEN que no se convalida.
 *
 * Con `carrera_id` nula la regla es institucional; con carrera, es de esa
 * carrera y pisa a la institucional de la misma clave — sirve tanto para añadir
 * una exclusión propia como para levantar una general.
 */
class CursoNoConvalidable extends Model
{
    protected $table = 'cursos_no_convalidables';

    protected $fillable = ['carrera_id', 'palabra_clave', 'clave_normalizada', 'motivo', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    private const CACHE_KEY = 'cursos_no_convalidables.reglas';

    public function carrera(): BelongsTo
    {
        return $this->belongsTo(Carrera::class, 'carrera_id');
    }

    /**
     * Reglas vigentes para una carrera: clave normalizada => motivo.
     *
     * Se cachea la tabla entera y se resuelve en memoria. Son decenas de filas
     * para decenas de carreras: cachear por carrera obligaría a invalidar N
     * claves en cada cambio y no compraría nada.
     *
     * @return array<string,?string>
     */
    public static function reglasVigentes(?int $carreraId = null): array
    {
        $filas = Cache::rememberForever(self::CACHE_KEY, fn () => static::query()
            ->get(['clave_normalizada', 'motivo', 'activo', 'carrera_id'])
            ->map(fn ($r) => [
                'clave' => $r->clave_normalizada,
                'motivo' => $r->motivo,
                'activo' => (bool) $r->activo,
                'carrera_id' => $r->carrera_id,
            ])->all());

        // Primero las institucionales; después las de la carrera, que las pisan
        // (incluso desactivándolas: por eso son dos pasadas y no un filtro).
        $reglas = [];
        foreach ($filas as $f) {
            if ($f['carrera_id'] === null && $f['activo']) {
                $reglas[$f['clave']] = $f['motivo'];
            }
        }

        if ($carreraId === null) {
            return $reglas;
        }

        foreach ($filas as $f) {
            if ((int) $f['carrera_id'] !== $carreraId) {
                continue;
            }
            if ($f['activo']) {
                $reglas[$f['clave']] = $f['motivo'];
            } else {
                unset($reglas[$f['clave']]);
            }
        }

        return $reglas;
    }

    /** Claves normalizadas vigentes para una carrera. */
    public static function clavesActivas(?int $carreraId = null): array
    {
        return array_keys(self::reglasVigentes($carreraId));
    }

    public static function limpiarCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::limpiarCache());
        static::deleted(fn () => self::limpiarCache());
    }
}
