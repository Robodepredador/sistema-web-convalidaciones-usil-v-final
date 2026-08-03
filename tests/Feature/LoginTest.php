<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function crearUsuario(): User
    {
        $rol = Role::create(['nombre' => Role::COORDINADOR]);

        return User::create([
            'nombre' => 'Coordinador Demo',
            'email' => 'coord@usil.edu.pe',
            'password_hash' => Hash::make('Clave#2026'),
            'rol_id' => $rol->id,
            'activo' => true,
            'primer_acceso' => false,
        ]);
    }

    public function test_login_correcto_redirige_al_dashboard(): void
    {
        $this->crearUsuario();

        $resp = $this->post('/login', [
            'email' => 'coord@usil.edu.pe',
            'password' => 'Clave#2026',
        ]);

        $resp->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_bloqueo_tras_cinco_intentos_fallidos(): void
    {
        $user = $this->crearUsuario();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', [
                'email' => 'coord@usil.edu.pe',
                'password' => 'incorrecta',
            ]);
        }

        $user->refresh();
        $this->assertNotNull($user->bloqueado_hasta);
        $this->assertTrue($user->estaBloqueado());
    }

    public function test_roles_inhabilitados_no_pueden_iniciar_sesion(): void
    {
        foreach ([Role::AUDITOR, Role::CONSULTA] as $i => $nombreRol) {
            $email = "sinacceso{$i}@usil.edu.pe";

            User::create([
                'nombre' => 'Sin Acceso',
                'email' => $email,
                'password_hash' => Hash::make('Clave#2026'),
                'rol_id' => Role::create(['nombre' => $nombreRol])->id,
                'activo' => true,
                'primer_acceso' => false,
            ]);

            $resp = $this->post('/login', ['email' => $email, 'password' => 'Clave#2026']);

            $resp->assertSessionHasErrors('email');
            $this->assertGuest();
        }
    }

    /**
     * BD-08: la misma regla que aplica el login debe verla la pantalla de
     * Usuarios; si divergen, el administrador ve "Activo" y no entiende por qué
     * el usuario no entra.
     */
    public function test_los_roles_sin_acceso_se_declaran_en_el_modelo(): void
    {
        $auditor = Role::create(['nombre' => Role::AUDITOR]);
        $asesor = Role::create(['nombre' => Role::ASESOR]);

        $this->assertFalse($auditor->puedeIniciarSesion());
        $this->assertTrue($asesor->puedeIniciarSesion());
        $this->assertContains(Role::CONSULTA, Role::SIN_ACCESO);
    }

    public function test_primer_acceso_obliga_cambio_de_password(): void
    {
        $user = $this->crearUsuario();
        $user->update(['primer_acceso' => true]);

        $resp = $this->post('/login', [
            'email' => 'coord@usil.edu.pe',
            'password' => 'Clave#2026',
        ]);

        $resp->assertRedirect(route('password.cambiar.form'));
    }
}
