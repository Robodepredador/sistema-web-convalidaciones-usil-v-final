<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Credenciales iniciales del personal (alta de usuario y restablecimiento).
 * La contraseña temporal viaja por correo y no por la pantalla del administrador.
 */
class AccesoSistemaMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $usuario,
        public string $url,
        public string $password,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Acceso al sistema — USIL Convalidaciones',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.acceso-sistema',
        );
    }
}
