<?php

namespace Tests\Unit;

use App\Mail\AccesoPortalMail;
use App\Models\Postulante;
use Tests\TestCase;

class AccesoPortalMailTest extends TestCase
{
    public function test_correo_muestra_usuario_y_password(): void
    {
        $p = new Postulante(['nombres' => 'Ana', 'email' => 'ana@example.com']);
        $mail = new AccesoPortalMail($p, 'http://localhost/portal/login', 'Temp#1234');

        $mail->assertHasSubject('Acceso a tu portal — USIL Convalidaciones');
        $mail->assertSeeInHtml('ana@example.com');
        $mail->assertSeeInHtml('Temp#1234');
    }
}
