<?php

namespace App\Mail;

use App\Models\Postulante;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AccesoPortalMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Postulante $postulante,
        public string $url,
        public string $password,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Acceso a tu portal — USIL Convalidaciones',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.acceso-portal',
        );
    }
}
