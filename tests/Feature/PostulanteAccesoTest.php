<?php

namespace Tests\Feature;

use App\Models\Postulante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostulanteAccesoTest extends TestCase
{
    use RefreshDatabase;

    public function test_flag_cambio_password_por_defecto_false(): void
    {
        $p = Postulante::create([
            'codigo' => 'POST-2026-90001', 'tipo_documento' => 'DNI', 'numero_documento' => '90000001',
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'email' => 'ana.acceso@example.com',
        ]);

        $this->assertFalse($p->fresh()->debe_cambiar_password);
    }
}
