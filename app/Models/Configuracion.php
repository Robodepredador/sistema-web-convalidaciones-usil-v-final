<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * Ajustes del sistema (clave-valor).
 *
 * Los valores marcados como "secreto" (p. ej. API keys) se guardan cifrados
 * en reposo con la APP_KEY de Laravel (RNF-08/09).
 */
class Configuracion extends Model
{
    protected $table = 'configuraciones';

    protected $fillable = ['clave', 'valor'];

    public $timestamps = true;

    /** Claves cuyo valor se cifra en la base de datos. */
    public const SECRETOS = ['gemini_api_key', 'openai_api_key'];

    private const CACHE_KEY = 'configuraciones.map';

    /** Devuelve el valor de una clave (desde cache), descifrando si es secreto. */
    public static function get(string $clave, ?string $default = null): ?string
    {
        $map = Cache::rememberForever(self::CACHE_KEY, fn () => static::pluck('valor', 'clave')->all());
        $valor = $map[$clave] ?? null;

        if ($valor === null || $valor === '') {
            return $default;
        }

        if (in_array($clave, self::SECRETOS, true)) {
            try {
                return Crypt::decryptString($valor);
            } catch (\Throwable) {
                return $default;
            }
        }

        return $valor;
    }

    /** Guarda un valor (cifrándolo si es secreto). Un valor vacío borra la clave. */
    public static function set(string $clave, ?string $valor): void
    {
        if ($valor === null || $valor === '') {
            static::where('clave', $clave)->delete();
        } else {
            $guardar = in_array($clave, self::SECRETOS, true) ? Crypt::encryptString($valor) : $valor;
            static::updateOrCreate(['clave' => $clave], ['valor' => $guardar]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    /** ¿Existe un valor no vacío para la clave? (sin exponer el secreto). */
    public static function tiene(string $clave): bool
    {
        return static::get($clave) !== null && static::get($clave) !== '';
    }
}
