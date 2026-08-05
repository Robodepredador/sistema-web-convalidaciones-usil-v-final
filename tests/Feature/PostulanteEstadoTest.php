<?php

namespace Tests\Feature;

use App\Models\Carrera;
use App\Models\Facultad;
use App\Models\Postulante;
use App\Models\Role;
use App\Models\UnidadNegocio;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Estado del expediente: un borrador nace y se muestra como 'borrador',
 * y solo pasa a 'nuevo' cuando el asesor lo guarda completo.
 */
class PostulanteEstadoTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $rol): User
    {
        $this->seed(RoleSeeder::class);

        return User::create([
            'nombre' => $rol, 'email' => uniqid().'@usil.edu.pe',
            'password_hash' => Hash::make('x'), 'rol_id' => Role::where('nombre', $rol)->firstOrFail()->id,
            'activo' => true, 'primer_acceso' => false,
        ]);
    }

    private function carrera(): Carrera
    {
        $un = UnidadNegocio::create(['nombre' => 'Lima', 'codigo' => 'LIM']);
        $fac = Facultad::create(['unidad_negocio_id' => $un->id, 'nombre' => 'Ingeniería', 'codigo' => 'ING']);

        return Carrera::create(['facultad_id' => $fac->id, 'nombre' => 'Ing. de Software', 'codigo' => 'SW', 'max_ciclos' => 10]);
    }

    /** @return array{0: User, 1: array<string,mixed>} */
    private function datosCompletos(): array
    {
        return [
            'tipo_documento' => 'DNI', 'numero_documento' => '45678912',
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez',
            'email' => 'ana@ex.com', 'ciclo_postulacion' => '2026-2',
            'carrera_destino_ids' => [$this->carrera()->id],
            // Art. 15 del Reglamento de Admisión: sin consentimiento no hay registro.
            'consentimiento_datos' => true,
        ];
    }

    public function test_guardar_borrador_deja_el_expediente_en_borrador(): void
    {
        $this->actingAs($this->usuario(Role::ASESOR))->post('/postulantes', [
            'borrador' => true,
            'tipo_documento' => 'DNI', 'numero_documento' => '45678912',
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez',
        ])->assertRedirect('/postulantes');

        $this->assertSame('borrador', Postulante::firstOrFail()->estado);
    }

    public function test_registro_completo_nace_nuevo(): void
    {
        $this->actingAs($this->usuario(Role::ASESOR))
            ->post('/postulantes', $this->datosCompletos())
            ->assertRedirect('/postulantes');

        $this->assertSame('nuevo', Postulante::firstOrFail()->estado);
    }

    public function test_completar_un_borrador_lo_pasa_a_nuevo(): void
    {
        $asesor = $this->usuario(Role::ASESOR);

        $this->actingAs($asesor)->post('/postulantes', [
            'borrador' => true, 'tipo_documento' => 'DNI', 'numero_documento' => '45678912',
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez',
        ])->assertRedirect();

        $p = Postulante::firstOrFail();
        $this->assertSame('borrador', $p->estado);

        $this->actingAs($asesor)->put("/postulantes/{$p->id}", $this->datosCompletos())->assertRedirect();
        $this->assertSame('nuevo', $p->fresh()->estado, 'Guardar completo debe cerrar el borrador.');
    }

    /** Editar un expediente ya registrado nunca lo devuelve a borrador. */
    public function test_guardar_como_borrador_no_degrada_un_registro_definitivo(): void
    {
        $asesor = $this->usuario(Role::ASESOR);
        $this->actingAs($asesor)->post('/postulantes', $this->datosCompletos())->assertRedirect();
        $p = Postulante::firstOrFail();

        $this->actingAs($asesor)->put("/postulantes/{$p->id}", [
            'borrador' => true, 'tipo_documento' => 'DNI', 'numero_documento' => '45678912',
            'nombres' => 'Ana María', 'apellido_paterno' => 'Pérez',
        ])->assertRedirect();

        $this->assertSame('nuevo', $p->fresh()->estado);
    }

    public function test_un_borrador_no_puede_revisarse(): void
    {
        $asesor = $this->usuario(Role::ASESOR);
        $ejecutivo = $this->usuario(Role::EJECUTIVO);

        $this->actingAs($asesor)->post('/postulantes', [
            'borrador' => true, 'tipo_documento' => 'DNI', 'numero_documento' => '45678912',
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez',
        ])->assertRedirect();
        $p = Postulante::firstOrFail();

        $this->actingAs($ejecutivo)
            ->post("/postulantes/{$p->id}/revisar", ['accion' => 'aprobar'])
            ->assertStatus(422);

        $this->assertSame('pendiente', $p->fresh()->revision_estado, 'La revisión no debe haberse movido.');
    }

    /** 'borrador' no es un destino manual del cambio de estado. */
    public function test_no_se_puede_fijar_borrador_a_mano(): void
    {
        $asesor = $this->usuario(Role::ASESOR);
        $this->actingAs($asesor)->post('/postulantes', $this->datosCompletos())->assertRedirect();
        $p = Postulante::firstOrFail();

        $this->actingAs($asesor)->patch("/postulantes/{$p->id}/estado", ['estado' => 'borrador'])
            ->assertInvalid('estado');
        $this->actingAs($asesor)->patch("/postulantes/{$p->id}/estado", ['estado' => 'rechazado'])
            ->assertRedirect();

        $this->assertSame('rechazado', $p->fresh()->estado);
    }

    /** No se admite a nadie saltándose la revisión de su expediente. */
    public function test_no_se_admite_sin_pasar_por_evaluacion(): void
    {
        $asesor = $this->usuario(Role::ASESOR);
        $this->actingAs($asesor)->post('/postulantes', $this->datosCompletos())->assertRedirect();
        $p = Postulante::firstOrFail();

        foreach (['admitido', 'matriculado', 'en_evaluacion'] as $destino) {
            $this->actingAs($asesor)->patch("/postulantes/{$p->id}/estado", ['estado' => $destino])
                ->assertInvalid('estado');
        }

        $this->assertSame('nuevo', $p->fresh()->estado);
    }

    /** El recorrido válido: evaluación → admitido → matriculado. */
    public function test_la_secuencia_del_proceso_si_avanza(): void
    {
        $asesor = $this->usuario(Role::ASESOR);
        $this->actingAs($asesor)->post('/postulantes', $this->datosCompletos())->assertRedirect();
        $p = Postulante::firstOrFail();

        // 'en_evaluacion' se alcanza aprobando la revisión, no a mano.
        $p->update(['estado' => 'en_evaluacion']);

        $this->actingAs($asesor)->patch("/postulantes/{$p->id}/estado", ['estado' => 'admitido'])->assertRedirect();
        $this->actingAs($asesor)->patch("/postulantes/{$p->id}/estado", ['estado' => 'matriculado'])->assertRedirect();
        $this->assertSame('matriculado', $p->fresh()->estado);

        // Y ahí se detiene: un matriculado ya no cambia de estado.
        $this->actingAs($asesor)->patch("/postulantes/{$p->id}/estado", ['estado' => 'rechazado'])
            ->assertInvalid('estado');
        $this->assertSame('matriculado', $p->fresh()->estado);
    }
}
