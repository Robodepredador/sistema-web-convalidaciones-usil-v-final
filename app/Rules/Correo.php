<?php

namespace App\Rules;

/**
 * Reglas de validación de correo electrónico.
 *
 * Mitiga CVE-2026-48019 (inyección CRLF en la regla `email` por defecto), que
 * afecta a toda la rama 11.x de Laravel y solo está corregido a partir de la
 * 12.60. Se decidió permanecer en la 11.54, así que la defensa vive aquí.
 *
 * El vector no son los CRLF sueltos —esos la regla `email` ya los rechaza— sino
 * la parte local ENTRECOMILLADA, que los cuela: «"ana\r\n"@usil.edu.pe» pasa la
 * validación por defecto. Si ese valor acaba en una cabecera To:, el atacante
 * añade las suyas (Bcc:, Reply-To:) y desvía el correo. Es alcanzable: el
 * sistema envía credenciales a direcciones que escribe el usuario.
 *
 * Se rechazan todos los caracteres de control (C0 y DEL) y no solo CR/LF: no hay
 * dirección legítima que los contenga, y así no se acepta ninguna variante.
 * Se prefiere esto a `email:rfc,strict`, que también lo bloquea pero de paso
 * rechaza direcciones raras aunque válidas.
 *
 * Lo comprueba tests/Feature/CorreoSinInyeccionTest.php, que ataca el vector
 * real. Si algún día se sube a Laravel 12.60+, esa prueba sigue pasando y esta
 * regla queda como defensa en profundidad; retirarla es entonces opcional.
 */
final class Correo
{
    /** Ningún carácter de control en la dirección. */
    public const SIN_CONTROL = 'not_regex:/[\x00-\x1F\x7F]/';

    /**
     * Juego completo para un campo de correo.
     *
     * @return array<int, string>
     */
    public static function reglas(bool $obligatorio = true, int $max = 150): array
    {
        return [$obligatorio ? 'required' : 'nullable', 'email', self::SIN_CONTROL, "max:{$max}"];
    }
}
