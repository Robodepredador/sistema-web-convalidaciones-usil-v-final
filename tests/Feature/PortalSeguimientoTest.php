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
            'codigo' => 'POST-2026-'.random_int(90100, 90999),
            'tipo_documento' => 'DNI', 'numero_documento' => (string) random_int(90000100, 90000999),
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'email' => uniqid().'@example.com',
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

    public function test_password_rechazada_no_filtra_claves_de_traduccion(): void
    {
        $p = $this->postulanteConAcceso(true);

        // 'abc' incumple min, mixedCase, numbers y symbols a la vez.
        $this->actingAs($p, 'postulante')->post('/portal/password/cambiar', [
            'password' => 'abc', 'password_confirmation' => 'abc',
        ])->assertSessionHasErrors('password');

        foreach (session('errors')->get('password') as $mensaje) {
            $this->assertStringNotContainsString('validation.', $mensaje, "Mensaje sin traducir: $mensaje");
        }
    }

    public function test_seguimiento_avanza_con_aprobacion_de_admision(): void
    {
        $p = $this->postulanteConAcceso(false); // revision_estado='pendiente' por defecto

        // 4 etapas. Etapa 1 (registro) completada, etapa 2 (revisión de documentos) actual.
        $this->actingAs($p, 'postulante')->get('/portal/')
            ->assertInertia(fn ($page) => $page
                ->has('timeline', 4)
                ->where('timeline.0.estado', 'completado')
                ->where('timeline.1.label', 'Revisión de documentos')
                ->where('timeline.1.estado', 'actual')
                ->where('postulante.estado', 'en_evaluacion'));

        // El Ejecutivo Comercial de Admisión aprueba el expediente.
        $p->update(['revision_estado' => 'aprobada']);

        // Etapa 2 completada, etapa 3 (simulación) actual.
        $this->actingAs($p->fresh(), 'postulante')->get('/portal/')
            ->assertInertia(fn ($page) => $page
                ->where('timeline.1.estado', 'completado')
                ->where('timeline.2.label', 'Simulación de convalidación')
                ->where('timeline.2.estado', 'actual'));
    }
}
