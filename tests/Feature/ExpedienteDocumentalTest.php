<?php

namespace Tests\Feature;

use App\Models\Postulante;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Expediente documental de traslado externo.
 *
 * Reglamento de Admisión, Art. 24: el postulante es apto «siempre y cuando
 * cumpla con los requisitos y la presentación de todos los documentos
 * exigidos». La modalidad admite una vía temporal (récord de notas con
 * declaración jurada), que aquí es una aprobación provisional explícita.
 */
class ExpedienteDocumentalTest extends TestCase
{
    use RefreshDatabase;

    private const EXPEDIENTE = ['dni', 'certificado'];

    private function usuario(string $rolNombre): User
    {
        $this->seed(RoleSeeder::class);
        $rol = Role::where('nombre', $rolNombre)->firstOrFail();

        return User::create([
            'nombre' => $rolNombre, 'email' => uniqid().'@usil.edu.pe',
            'password_hash' => Hash::make('x'), 'rol_id' => $rol->id,
            'activo' => true, 'primer_acceso' => false,
        ]);
    }

    private function postulante(User $asesor, array $documentos = []): Postulante
    {
        $p = Postulante::create([
            'codigo' => 'POST-2026-'.random_int(10000, 99999),
            'tipo_documento' => 'DNI', 'numero_documento' => (string) random_int(10000000, 99999999),
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'email' => uniqid().'@ex.com',
            'estado' => 'nuevo', 'usuario_id' => $asesor->id,
        ]);

        foreach ($documentos as $tipo) {
            $p->documentos()->create([
                'tipo' => $tipo, 'nombre_original' => "{$tipo}.pdf",
                'ruta' => "postulantes/{$p->id}/{$tipo}.pdf", 'tamano' => 1024,
            ]);
        }

        return $p;
    }

    public function test_no_se_aprueba_un_expediente_sin_documentos(): void
    {
        $ejecutivo = $this->usuario(Role::EJECUTIVO);
        $p = $this->postulante($this->usuario(Role::ASESOR));

        $this->actingAs($ejecutivo)
            ->post("/postulantes/{$p->id}/revisar", ['accion' => 'aprobar'])
            ->assertStatus(422);

        $this->assertSame('pendiente', $p->fresh()->revision_estado);
        $this->assertSame('nuevo', $p->fresh()->estado, 'Avanzó a evaluación sin expediente.');
    }

    public function test_no_basta_con_parte_del_expediente(): void
    {
        $ejecutivo = $this->usuario(Role::EJECUTIVO);
        $p = $this->postulante($this->usuario(Role::ASESOR), ['dni']);

        $this->actingAs($ejecutivo)
            ->post("/postulantes/{$p->id}/revisar", ['accion' => 'aprobar'])
            ->assertStatus(422);

        $this->assertSame('pendiente', $p->fresh()->revision_estado);
    }

    public function test_con_el_expediente_completo_se_aprueba(): void
    {
        $ejecutivo = $this->usuario(Role::EJECUTIVO);
        $p = $this->postulante($this->usuario(Role::ASESOR), self::EXPEDIENTE);

        $this->actingAs($ejecutivo)
            ->post("/postulantes/{$p->id}/revisar", ['accion' => 'aprobar'])
            ->assertRedirect();

        $this->assertSame('aprobada', $p->fresh()->revision_estado);
        $this->assertFalse((bool) $p->fresh()->revision_provisional);
        $this->assertSame('en_evaluacion', $p->fresh()->estado);
    }

    /** Vía temporal del proceso: se admite, pero declarada y justificada. */
    public function test_la_aprobacion_provisional_queda_marcada_y_justificada(): void
    {
        $ejecutivo = $this->usuario(Role::EJECUTIVO);
        $p = $this->postulante($this->usuario(Role::ASESOR), ['dni']);

        // Provisional sin justificación: no procede.
        $this->actingAs($ejecutivo)
            ->post("/postulantes/{$p->id}/revisar", ['accion' => 'aprobar', 'provisional' => true])
            ->assertStatus(422);
        $this->assertSame('pendiente', $p->fresh()->revision_estado);

        $this->actingAs($ejecutivo)->post("/postulantes/{$p->id}/revisar", [
            'accion' => 'aprobar',
            'provisional' => true,
            'observaciones' => 'Presenta récord de notas y declaración jurada; regulariza certificado y sílabos al matricularse.',
        ])->assertRedirect();

        $p->refresh();
        $this->assertSame('aprobada', $p->revision_estado);
        $this->assertTrue((bool) $p->revision_provisional);
        $this->assertStringContainsString('declaración jurada', $p->revision_observaciones);
    }

    /** Con expediente completo la marca provisional no se pega sola. */
    public function test_el_expediente_completo_no_queda_marcado_como_provisional(): void
    {
        $ejecutivo = $this->usuario(Role::EJECUTIVO);
        $p = $this->postulante($this->usuario(Role::ASESOR), self::EXPEDIENTE);

        $this->actingAs($ejecutivo)->post("/postulantes/{$p->id}/revisar", [
            'accion' => 'aprobar', 'provisional' => false,
        ])->assertRedirect();

        $this->assertFalse((bool) $p->fresh()->revision_provisional);
        $this->assertNull($p->fresh()->revision_observaciones);
    }

    /** Observar nunca depende de los documentos: es justamente lo que se pide corregir. */
    public function test_observar_no_exige_expediente_completo(): void
    {
        $ejecutivo = $this->usuario(Role::EJECUTIVO);
        $p = $this->postulante($this->usuario(Role::ASESOR));

        $this->actingAs($ejecutivo)->post("/postulantes/{$p->id}/revisar", [
            'accion' => 'observar', 'observaciones' => 'Faltan el certificado SUNEDU y los sílabos visados.',
        ])->assertRedirect();

        $this->assertSame('observada', $p->fresh()->revision_estado);
    }
}
