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

    /**
     * El cliente describe cinco roles: superusuario, especialista, administrativo
     * de facultad, asesor de admisión y ejecutivo comercial. Los cuatro que
     * sobraban sostenían una cadena de aprobación que este flujo no tiene.
     */
    public function test_el_catalogo_de_roles_es_el_del_flujo_del_cliente(): void
    {
        $esperados = [
            Role::SUPERUSUARIO,
            Role::ESPECIALISTA,
            Role::ADMINISTRATIVO,
            Role::ASESOR,
            Role::EJECUTIVO,
        ];

        $this->seed(RoleSeeder::class);

        $this->assertEqualsCanonicalizing($esperados, Role::pluck('nombre')->all());
    }

    private function administrativo(): User
    {
        $rol = Role::create(['nombre' => Role::ADMINISTRATIVO]);

        return User::create([
            'nombre' => 'Admin Facultad', 'email' => 'af@usil.edu.pe',
            'password_hash' => Hash::make('x'), 'rol_id' => $rol->id,
            'activo' => true, 'primer_acceso' => false,
        ]);
    }

    /** RF-39: el administrativo de facultad no accede a la administración de usuarios. */
    public function test_administrativo_no_accede_a_usuarios(): void
    {
        $this->actingAs($this->administrativo())
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

    /** El Asesor de Admisión registra postulantes pero no evalúa. */
    public function test_asesor_gestiona_postulantes_no_evaluacion(): void
    {
        $asesor = $this->usuarioConRol(Role::ASESOR);

        $this->actingAs($asesor)->get('/postulantes')->assertOk();
        $this->actingAs($asesor)->get('/simulaciones')->assertForbidden();
        $this->actingAs($asesor)->get('/equivalencias')->assertForbidden();
        // (La denegación de la ruta de revisión se prueba en RevisionFlujoTest, donde ya existe la ruta.)
    }

    /** El Ejecutivo Comercial revisa expedientes de admisión; no evalúa. */
    public function test_ejecutivo_revisa_expedientes_no_evalua(): void
    {
        $ejecutivo = $this->usuarioConRol(Role::EJECUTIVO);

        $this->actingAs($ejecutivo)->get('/postulantes')->assertOk();
        $this->actingAs($ejecutivo)->get('/simulaciones')->assertForbidden();
    }

    /** El Administrativo ve solicitudes (nuevo: RF-40 ya no se lo impide) pero no las gestiona: eso es de Admisión. */
    public function test_administrativo_no_gestiona_postulantes(): void
    {
        $administrativo = $this->usuarioConRol(Role::ADMINISTRATIVO);

        $this->actingAs($administrativo)->get('/postulantes')->assertOk();
        $this->actingAs($administrativo)->post('/postulantes', [])->assertForbidden();
        // Pero conserva su módulo de evaluación.
        $this->actingAs($administrativo)->get('/simulaciones')->assertOk();
    }

    /**
     * El Administrativo ya NO gestiona mallas externas: ese permiso se centralizó
     * en el Especialista en Convalidaciones, que registra la política una sola vez.
     */
    public function test_administrativo_no_gestiona_mallas_externas(): void
    {
        $administrativo = $this->usuarioConRol(Role::ADMINISTRATIVO);

        $this->actingAs($administrativo)->post('/mallas-externas', [])->assertForbidden();
        $this->actingAs($administrativo)->get('/simulaciones')->assertOk();
    }
}
