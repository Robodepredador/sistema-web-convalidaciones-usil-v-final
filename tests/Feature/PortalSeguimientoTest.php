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

    public function test_seguimiento_avanza_con_documentos(): void
    {
        $p = $this->postulanteConAcceso(false); // sin forzar cambio

        // Fase 1 (registro) completada, fase 2 (documentos) actual.
        $this->actingAs($p, 'postulante')->get('/portal/')
            ->assertInertia(fn ($page) => $page
                ->where('timeline.0.estado', 'completado')
                ->where('timeline.1.estado', 'actual')
                ->where('postulante.estado', 'en_evaluacion'));

        // Cargar los 3 documentos del expediente.
        foreach (['certificado', 'silabos', 'constancia'] as $tipo) {
            $p->documentos()->create([
                'tipo' => $tipo, 'nombre_original' => "{$tipo}.pdf",
                'ruta' => "postulantes/{$p->id}/{$tipo}.pdf", 'tamano' => 1000,
            ]);
        }

        // Fase 2 completada, fase 3 (equivalencias) actual.
        $this->actingAs($p, 'postulante')->get('/portal/')
            ->assertInertia(fn ($page) => $page
                ->where('timeline.1.estado', 'completado')
                ->where('timeline.2.estado', 'actual'));
    }
}
