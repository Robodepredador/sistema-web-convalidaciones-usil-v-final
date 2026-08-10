<?php

namespace Tests\Feature;

use App\Mail\AccesoSistemaMail;
use App\Models\Postulante;
use App\Models\Role;
use App\Models\User;
use App\Support\EntregaCredenciales;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * El correo es el único canal por el que salen las contraseñas: si no sale, hay
 * que decirlo. Antes la pantalla afirmaba «Se enviaron las credenciales» aunque
 * el envío hubiera fallado o el sistema no tuviera servidor de correo, y el alta
 * parecía correcta mientras nadie podía entrar.
 */
class EntregaCredencialesTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $email = 'nuevo@usil.edu.pe'): User
    {
        $rol = Role::create(['nombre' => Role::ADMIN]);

        return User::create([
            'nombre' => 'Nuevo', 'email' => $email, 'password_hash' => Hash::make('inicial'),
            'rol_id' => $rol->id, 'activo' => true, 'primer_acceso' => true,
        ]);
    }

    public function test_con_servidor_de_correo_confirma_el_envio(): void
    {
        Mail::fake();
        $u = $this->usuario();

        $aviso = EntregaCredenciales::enviar($u->email, new AccesoSistemaMail($u, '/login', 'Temp#1234'), 'Temp#1234');

        Mail::assertSent(AccesoSistemaMail::class);
        $this->assertStringContainsString('Se enviaron las credenciales', $aviso);
        $this->assertStringNotContainsString('ATENCIÓN', $aviso);
    }

    /** MAIL_MAILER=log es el valor por defecto: escribe en un archivo y no entrega. */
    public function test_sin_servidor_de_correo_avisa_y_ofrece_la_salida_de_emergencia(): void
    {
        config(['mail.default' => 'log']);
        $u = $this->usuario();

        $aviso = EntregaCredenciales::enviar($u->email, new AccesoSistemaMail($u, '/login', 'Temp#1234'), 'Temp#1234');

        $this->assertStringContainsString('ATENCIÓN', $aviso);
        $this->assertStringContainsString('MAIL_MAILER=log', $aviso);
        $this->assertStringContainsString('usuario:password', $aviso,
            'El aviso debe decir cómo entregar la contraseña sin correo.');
    }

    public function test_si_el_envio_falla_no_afirma_que_se_envio(): void
    {
        $u = $this->usuario();
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('SMTP caído'));

        $aviso = EntregaCredenciales::enviar($u->email, new AccesoSistemaMail($u, '/login', 'Temp#1234'), 'Temp#1234');

        $this->assertStringContainsString('ATENCIÓN', $aviso);
        $this->assertStringNotContainsString('Se enviaron las credenciales', $aviso);
    }

    /** Salida de emergencia: arrancar sin SMTP tiene que ser posible. */
    public function test_el_comando_restablece_la_contrasena_del_personal(): void
    {
        $u = $this->usuario();
        $hashPrevio = $u->password_hash;

        $this->artisan('usuario:password', ['email' => $u->email])
            ->expectsOutputToContain('Contraseña:')
            ->assertSuccessful();

        $u->refresh();
        $this->assertNotSame($hashPrevio, $u->password_hash);
        $this->assertTrue((bool) $u->primer_acceso, 'Debe exigir el cambio en el primer acceso.');
    }

    public function test_el_comando_tambien_sirve_al_postulante(): void
    {
        $p = Postulante::create([
            'codigo' => 'POST-2026-00001', 'tipo_documento' => 'DNI', 'numero_documento' => '12345678',
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'email' => 'ana@ext.com',
            'estado' => 'nuevo', 'acceso_habilitado' => false,
        ]);

        $this->artisan('usuario:password', ['email' => 'ana@ext.com'])->assertSuccessful();

        $p->refresh();
        $this->assertTrue((bool) $p->acceso_habilitado, 'Debe reactivar el acceso al portal.');
        $this->assertTrue((bool) $p->debe_cambiar_password);
    }

    public function test_el_comando_falla_con_un_correo_desconocido(): void
    {
        $this->artisan('usuario:password', ['email' => 'nadie@usil.edu.pe'])->assertFailed();
    }
}
