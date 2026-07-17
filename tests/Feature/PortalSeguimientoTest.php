<?php

namespace Tests\Feature;

use App\Models\Postulante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PortalSeguimientoTest extends TestCase
{
    use RefreshDatabase;

    private function postulanteConAcceso(bool $debeCambiar): Postulante
    {
        return Postulante::create([
            'codigo' => 'POST-2026-' . random_int(90100, 90999),
            'tipo_documento' => 'DNI', 'numero_documento' => (string) random_int(90000100, 90000999),
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'email' => uniqid() . '@example.com',
            'estado' => 'en_evaluacion',
            'password_hash' => Hash::make('Temp#1234'),
            'acceso_habilitado' => true,
            'debe_cambiar_password' => $debeCambiar,
        ]);
    }

    public function test_primer_acceso_obliga_cambio_de_password(): void
    {
        $p = $this->postulanteConAcceso(true);

        // Con el flag activo, el seguimiento redirige al cambio de contraseña.
        $this->actingAs($p, 'postulante')->get('/portal/')
            ->assertRedirect(route('portal.password.cambiar.form'));

        // Cambiar la contraseña baja el flag y redirige al seguimiento.
        $this->actingAs($p, 'postulante')->post('/portal/password/cambiar', [
            'password' => 'NuevaClave#2026', 'password_confirmation' => 'NuevaClave#2026',
        ])->assertRedirect(route('portal.seguimiento'));

        $this->assertFalse($p->fresh()->debe_cambiar_password);

        // Ya con el flag en false, el seguimiento carga.
        $this->actingAs($p->fresh(), 'postulante')->get('/portal/')->assertOk();
    }
}
