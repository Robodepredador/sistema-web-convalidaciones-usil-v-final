<?php

namespace Tests\Feature;

use App\Models\Postulante;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RevisionFlujoTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $rolNombre): User
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $rol = Role::where('nombre', $rolNombre)->firstOrFail();

        return User::create([
            'nombre' => $rolNombre, 'email' => uniqid() . '@usil.edu.pe',
            'password_hash' => Hash::make('x'), 'rol_id' => $rol->id,
            'activo' => true, 'primer_acceso' => false,
        ]);
    }

    private function postulanteDe(User $asesor): Postulante
    {
        return Postulante::create([
            'codigo' => 'POST-2026-' . random_int(10000, 99999),
            'tipo_documento' => 'DNI', 'numero_documento' => (string) random_int(10000000, 99999999),
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'email' => uniqid() . '@ex.com',
            'usuario_id' => $asesor->id,
        ]);
    }

    public function test_ejecutivo_aprueba_expediente(): void
    {
        $asesor = $this->usuario(Role::ASESOR);
        $ejecutivo = $this->usuario(Role::EJECUTIVO);
        $p = $this->postulanteDe($asesor);

        $this->actingAs($ejecutivo)
            ->post("/postulantes/{$p->id}/revisar", ['accion' => 'aprobar'])
            ->assertRedirect();

        $this->assertSame('aprobada', $p->fresh()->revision_estado);
        $this->assertSame($ejecutivo->id, $p->fresh()->revisado_por);
    }

    public function test_observar_exige_texto(): void
    {
        $asesor = $this->usuario(Role::ASESOR);
        $ejecutivo = $this->usuario(Role::EJECUTIVO);
        $p = $this->postulanteDe($asesor);

        $this->actingAs($ejecutivo)
            ->post("/postulantes/{$p->id}/revisar", ['accion' => 'observar'])
            ->assertSessionHasErrors('observaciones');

        $this->assertSame('pendiente', $p->fresh()->revision_estado);
    }

    public function test_asesor_reenvia_expediente_observado(): void
    {
        $asesor = $this->usuario(Role::ASESOR);
        $ejecutivo = $this->usuario(Role::EJECUTIVO);
        $p = $this->postulanteDe($asesor);

        $this->actingAs($ejecutivo)->post("/postulantes/{$p->id}/revisar",
            ['accion' => 'observar', 'observaciones' => 'Falta el sílabo de Cálculo.']);
        $this->assertSame('observada', $p->fresh()->revision_estado);

        $this->actingAs($asesor)->post("/postulantes/{$p->id}/reenviar-revision")->assertRedirect();
        $this->assertSame('pendiente', $p->fresh()->revision_estado);
    }

    public function test_asesor_no_puede_aprobar(): void
    {
        $asesor = $this->usuario(Role::ASESOR);
        $p = $this->postulanteDe($asesor);

        // No tiene el permiso solicitudes.validar → 403.
        $this->actingAs($asesor)
            ->post("/postulantes/{$p->id}/revisar", ['accion' => 'aprobar'])
            ->assertForbidden();
    }

    public function test_asesor_solo_ve_sus_postulantes(): void
    {
        $asesorA = $this->usuario(Role::ASESOR);
        $asesorB = $this->usuario(Role::ASESOR);
        $mio = $this->postulanteDe($asesorA);
        $ajeno = $this->postulanteDe($asesorB);

        // La lista paginada de Inertia solo trae el propio (1 fila en postulantes.data).
        $this->actingAs($asesorA)->get('/postulantes')
            ->assertInertia(fn ($page) => $page->has('postulantes.data', 1));

        // No puede editar el ajeno.
        $this->actingAs($asesorA)->get("/postulantes/{$ajeno->id}/editar")->assertForbidden();
    }
}
