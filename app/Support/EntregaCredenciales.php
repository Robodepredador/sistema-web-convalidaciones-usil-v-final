<?php

namespace App\Support;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Entrega una contraseña temporal por correo y dice la verdad sobre el intento.
 *
 * El correo es el ÚNICO canal por el que salen las contraseñas —del personal y
 * del postulante—, así que un envío fallido deja al usuario sin poder entrar.
 * Antes se capturaba la excepción, se anotaba en el log y la pantalla decía
 * «Se enviaron las credenciales» igualmente: el alta parecía correcta y no lo
 * era. Con `MAIL_MAILER=log` —el valor por defecto— ni siquiera había excepción
 * que capturar: el mensaje se escribía en un archivo y nadie se enteraba.
 *
 * No se lanza la excepción hacia arriba a propósito: el usuario ya está creado y
 * perder eso sería peor. Lo que se corrige es el mensaje.
 */
class EntregaCredenciales
{
    /** Envía y devuelve el aviso que debe leer quien dio de alta la cuenta. */
    public static function enviar(string $email, Mailable $mensaje, string $temporal): string
    {
        // Se intenta SIEMPRE, incluso sabiendo que el canal no entrega: así el
        // envío queda registrado y las pruebas pueden afirmar sobre él.
        try {
            Mail::to($email)->send($mensaje);
        } catch (\Throwable $e) {
            Log::error('No se pudo enviar el correo de credenciales.', ['email' => $email, 'excepcion' => $e]);

            return self::aviso('El correo NO se pudo enviar.', $email, $temporal);
        }

        // 'log' no entrega nada: escribe el mensaje en storage/logs y devuelve
        // éxito. Es el valor por defecto, así que sin SMTP configurado el alta
        // parecía correcta y el usuario nunca recibía nada.
        if (config('mail.default') === 'log') {
            Log::warning('Credenciales escritas en el log: no hay servidor de correo.', ['email' => $email]);

            return self::aviso('El correo NO llegó a enviarse: no hay servidor de correo configurado '
                .'(MAIL_MAILER=log), así que el mensaje solo quedó escrito en el log del servidor.',
                $email, $temporal);
        }

        return "Se enviaron las credenciales a {$email}.".self::pistaLocal($temporal);
    }

    /** Aviso de fallo, con la salida de emergencia que puede usar TI. */
    private static function aviso(string $motivo, string $email, string $temporal): string
    {
        return "ATENCIÓN: {$motivo} La cuenta quedó creada, pero {$email} no ha recibido su contraseña. "
            ."Entrégala por otro medio con «php artisan usuario:password {$email}»."
            .self::pistaLocal($temporal);
    }

    /**
     * Solo en desarrollo se muestra la contraseña en pantalla. En producción no
     * debe aparecer: queda en la sesión y en el historial del navegador.
     */
    private static function pistaLocal(string $temporal): string
    {
        return app()->environment('local') ? " (solo en desarrollo — temporal: {$temporal})" : '';
    }
}
