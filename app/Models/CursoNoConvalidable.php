<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Palabra/frase clave de un curso de origen que nunca se convalida.
 */
class CursoNoConvalidable extends Model
{
    protected $table = 'cursos_no_convalidables';

    protected $fillable = ['palabra_clave', 'clave_normalizada', 'motivo', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    private const CACHE_KEY = 'cursos_no_convalidables.activos';

    /** Claves normalizadas activas (cacheadas). */
    public static function clavesActivas(): array
    {
        return Cache::rememberForever(
            self::CACHE_KEY,
            fn () => static::where('activo', true)->pluck('clave_normalizada')->all()
        );
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
