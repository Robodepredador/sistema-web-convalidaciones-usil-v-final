<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    private function coordinador(): User
    {
        $rol = Role::create(['nombre' => Role::COORDINADOR]);

        return User::create([
            'nombre' => 'Coord', 'email' => 'c@usil.edu.pe',
            'password_hash' => Hash::make('x'), 'rol_id' => $rol->id,
            'activo' => true, 'primer_acceso' => false,
        ]);
    }

    /** RF-39: el coordinador no accede a la administración de usuarios. */
    public function test_coordinador_no_accede_a_usuarios(): void
    {
        $this->actingAs($this->coordinador())
            ->get('/usuarios')
            ->assertForbidden();
    }

    /** Crea un usuario con el rol indicado y sus permisos reales (RoleSeeder). */
    private function usuarioConRol(string $rolNombre): User
    {
        $this->seed(RoleSeeder::class);
        $rol = Role::where('nombre', $rolNombre)->firstOrFail();

        return User::create([
            'nombre' => $rolNombre, 'email' => strtolower(str_replace([' ', '/'], '', $rolNombre)).'@usil.edu.pe',
            'password_hash' => Hash::make('x'), 'rol_id' => $rol->id,
            'activo' => true, 'primer_acceso' => false,
        ]);
    }

    /** El Auditor (solo lectura) no puede ejecutar acciones de escritura. */
    public function test_auditor_no_puede_escribir(): void
    {
        $auditor = $this->usuarioConRol(Role::AUDITOR);

        // Rutas sin binding: el middleware responde 403 directo.
        $this->actingAs($auditor)->post('/mallas-externas', [])->assertForbidden();
        $this->actingAs($auditor)->post('/simulaciones', [])->assertForbidden();
        $this->actingAs($auditor)->post('/postulantes', [])->assertForbidden();

        // Rutas con binding {id}: el binding (404) corre antes que el permiso (403);
        // ambos deniegan — lo importante es que nunca sea 2xx/302 de éxito.
        foreach ([
            fn () => $this->put('/simulaciones/1', []),
            fn () => $this->delete('/simulaciones/1'),
            fn () => $this->post('/simulaciones/1/confirmar'),
            fn () => $this->post('/convalidaciones/1/anular', []),
            fn () => $this->delete('/postulantes/1'),
        ] as $peticion) {
            $this->actingAs($auditor);
            $status = $peticion()->getStatusCode();
            $this->assertContains($status, [403, 404], "Se esperaba denegación (403/404), llegó {$status}.");
        }
    }

    /** El Asesor de Admisión registra postulantes pero no evalúa. */
    public function test_asesor_gestiona_postulantes_no_evaluacion(): void
    {
        $asesor = $this->usuarioConRol(Role::ASESOR);

        $this->actingAs($asesor)->get('/postulantes')->assertOk();
        $this->actingAs($asesor)->get('/simulaciones')->assertForbidden();
        $this->actingAs($asesor)->get('/equivalencias')->assertForbidden();
        // (La denegación de la ruta de revisión se prueba en RevisionFlujoTest, donde ya existe la ruta.)
    }

    /** El Ejecutivo Comercial revisa expedientes y ve reportes; no evalúa. */
    public function test_ejecutivo_revisa_y_ve_reportes(): void
    {
        $ejecutivo = $this->usuarioConRol(Role::EJECUTIVO);

        $this->actingAs($ejecutivo)->get('/postulantes')->assertOk();
        $this->actingAs($ejecutivo)->get('/reportes')->assertOk();
        $this->actingAs($ejecutivo)->get('/simulaciones')->assertForbidden();
    }

    /** El Coordinador no accede al módulo de postulantes (lo gestiona Admisión). */
    public function test_coordinador_no_accede_a_postulantes(): void
    {
        $coordinador = $this->usuarioConRol(Role::COORDINADOR);

        $this->actingAs($coordinador)->get('/postulantes')->assertForbidden();
        $this->actingAs($coordinador)->post('/postulantes', [])->assertForbidden();
        // Pero conserva su módulo de evaluación.
        $this->actingAs($coordinador)->get('/simulaciones')->assertOk();
    }

    /**
     * El Coordinador SÍ gestiona mallas externas desde 2026-08-07.
     *
     * Antes no podía, y era deliberado. Cambió porque el mapeo de equivalencias
     * arranca subiendo la malla de la institución de origen: sin este permiso el
     * coordinador dependería de otro rol para dar el primer paso de su propio flujo.
     */
    public function test_coordinador_gestiona_mallas_externas(): void
    {
        $coordinador = $this->usuarioConRol(Role::COORDINADOR);

        $this->actingAs($coordinador)->get('/equivalencias')->assertOk();
        // 302 (redirect con errores de validación) = pasó la autorización; 403 = bloqueado.
        $this->actingAs($coordinador)->post('/mallas-externas', [])->assertStatus(302);
        $this->actingAs($coordinador)->get('/simulaciones')->assertOk();
    }

    /** El Decano sí puede gestionar mallas externas (pasa el middleware de permiso). */
    public function test_decano_puede_gestionar_mallas_externas(): void
    {
        $decano = $this->usuarioConRol(Role::DECANO);

        // 302 (redirect con errores de validación) = pasó la autorización; 403 = bloqueado.
        $this->actingAs($decano)->post('/mallas-externas', [])->assertStatus(302);
        $this->actingAs($decano)->get('/equivalencias')->assertOk();
    }
}
