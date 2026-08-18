<?php

namespace Tests\Feature;

use App\Models\Postulante;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RevisionFlujoTest extends TestCase
{
    use RefreshDatabase;

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

    /** Con el expediente documental completo: sin él la aprobación ya no procede. */
    private function postulanteDe(User $asesor): Postulante
    {
        $p = Postulante::create([
            'codigo' => 'POST-2026-'.random_int(10000, 99999),
            'tipo_documento' => 'DNI', 'numero_documento' => (string) random_int(10000000, 99999999),
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'email' => uniqid().'@ex.com',
            'usuario_id' => $asesor->id,
        ]);

        foreach (['dni', 'certificado'] as $tipo) {
            $p->documentos()->create([
                'tipo' => $tipo, 'nombre_original' => "{$tipo}.pdf",
                'ruta' => "postulantes/{$p->id}/{$tipo}.pdf", 'tamano' => 1024,
            ]);
        }

        return $p;
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
        $this->assertFalse((bool) $p->fresh()->revision_provisional);
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

    public function test_aprobar_avanza_estado_a_en_evaluacion(): void
    {
        $asesor = $this->usuario(Role::ASESOR);
        $ejecutivo = $this->usuario(Role::EJECUTIVO);
        $p = $this->postulanteDe($asesor);
        $p->update(['estado' => 'nuevo']); // como lo deja el registro del asesor (store()).

        $this->actingAs($ejecutivo)->post("/postulantes/{$p->id}/revisar", ['accion' => 'aprobar']);

        $this->assertSame('en_evaluacion', $p->fresh()->estado);
    }

    /**
     * Antes de la revisión de la Task A1 el Ejecutivo no tenía 'solicitudes.crear'
     * y estas dos rutas -gateadas por ese mismo permiso, no por uno propio de
     * "eliminar" o "resetear"- le quedaban vedadas. El hallazgo 4 de esa revisión
     * se lo dio (el brief ya lo pedía y se había quedado fuera al implementar),
     * así que ahora las alcanza: sin alcance por carrera (rol global) y sin la
     * restricción de propiedad que sí aplica al Asesor, puede operar cualquier
     * postulante. Si "registrar" y "borrar/resetear" deben separarse en permisos
     * distintos es una decisión de diseño que queda para la Task A2, junto con
     * el resto del conjunto exacto de Ejecutivo.
     */
    public function test_ejecutivo_elimina_y_resetea_con_solicitudes_crear(): void
    {
        $asesor = $this->usuario(Role::ASESOR);
        $ejecutivo = $this->usuario(Role::EJECUTIVO);

        $this->actingAs($ejecutivo)
            ->patch('/postulantes/'.$this->postulanteDe($asesor)->id.'/reset-acceso')
            ->assertRedirect();

        $this->actingAs($ejecutivo)
            ->delete('/postulantes/'.$this->postulanteDe($asesor)->id)
            ->assertRedirect();
    }

    public function test_filtro_por_revision(): void
    {
        $asesor = $this->usuario(Role::ASESOR);
        $ejecutivo = $this->usuario(Role::EJECUTIVO);
        $pendiente = $this->postulanteDe($asesor);
        $observado = $this->postulanteDe($asesor);
        $observado->update(['revision_estado' => 'observada']);

        $this->actingAs($ejecutivo)->get('/postulantes?revision=observada')
            ->assertInertia(fn ($page) => $page->has('postulantes.data', 1)
                ->where('postulantes.data.0.id', $observado->id));
    }
}
