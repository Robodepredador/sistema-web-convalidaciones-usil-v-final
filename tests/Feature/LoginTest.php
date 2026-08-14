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
        $rol = Role::create(['nombre' => Role::ADMINISTRATIVO]);

        return User::create([
            'nombre' => 'Administrativo Demo',
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
