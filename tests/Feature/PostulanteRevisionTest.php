<?php

namespace Tests\Feature;

use App\Models\Postulante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostulanteRevisionTest extends TestCase
{
    use RefreshDatabase;

    /** Un postulante nuevo arranca pendiente de revisión. */
    public function test_postulante_arranca_pendiente(): void
    {
        $p = Postulante::create([
            'codigo' => 'POST-2026-99999', 'tipo_documento' => 'DNI', 'numero_documento' => '99999999',
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'email' => 'ana@example.com',
        ]);

        $this->assertSame('pendiente', $p->fresh()->revision_estado);
    }
}
