<?php

namespace Tests\Feature;

use App\Models\Postulante;
use App\Models\Role;
use App\Models\User;
use App\Rules\Correo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Mitigación de CVE-2026-48019: inyección CRLF en la regla `email`.
 *
 * Afecta a toda la rama 11.x de Laravel y solo está corregido en 12.60+. Se
 * decidió permanecer en la 11.54, así que estas pruebas son la garantía de que
 * la mitigación funciona y sigue en su sitio.
 *
 * Importa el detalle: los CRLF sueltos la regla `email` YA los rechaza. El que
 * pasa —y por el que existe el CVE— es la parte local entrecomillada.
 */
class CorreoSinInyeccionTest extends TestCase
{
    use RefreshDatabase;

    /** El vector real: comillas alrededor de la parte local para colar el salto. */
    private const ENTRECOMILLADO = "\"ana\r\n\"@usil.edu.pe";

    /** Deja constancia de que sin la mitigación el ataque pasaría. */
    public function test_la_regla_email_por_si_sola_acepta_el_vector(): void
    {
        $pasa = ! Validator::make(['email' => self::ENTRECOMILLADO], ['email' => ['email']])->fails();

        $this->assertTrue($pasa,
            'La regla `email` ya rechaza el vector: revisa si Laravel se actualizó y la mitigación sobra.');
    }

    public function test_la_regla_del_proyecto_lo_rechaza(): void
    {
        $this->assertTrue(
            Validator::make(['email' => self::ENTRECOMILLADO], ['email' => Correo::reglas()])->fails(),
            'La mitigación no bloqueó la inyección CRLF.'
        );
    }

    /** Ningún carácter de control, por si aparece otra variante del mismo truco. */
    public function test_rechaza_cualquier_caracter_de_control(): void
    {
        foreach (["ana@usil.edu.pe\r\nBcc: x@y.com", "ana@usil.edu.pe\n", "ana\t@usil.edu.pe", "ana@usil.edu.pe\0"] as $carga) {
            $this->assertTrue(
                Validator::make(['email' => $carga], ['email' => Correo::reglas()])->fails(),
                'Pasó un correo con caracteres de control: '.addcslashes($carga, "\r\n\t\0")
            );
        }
    }

    /** Y no rompe lo legítimo: la mitigación no puede dejar fuera a nadie real. */
    public function test_acepta_correos_normales(): void
    {
        foreach (['ana@usil.edu.pe', 'ana.perez+admision@usil.edu.pe', 'a_b-c@sub.dominio.pe'] as $valido) {
            $this->assertFalse(
                Validator::make(['email' => $valido], ['email' => Correo::reglas()])->fails(),
                "Se rechazó un correo válido: {$valido}"
            );
        }
    }

    /** Y por HTTP: el alta de personal es uno de los puntos donde se envía correo. */
    public function test_el_alta_de_usuario_rechaza_el_vector(): void
    {
        $rol = Role::create(['nombre' => Role::ADMIN]);
        $admin = User::create([
            'nombre' => 'Admin', 'email' => 'admin@usil.edu.pe', 'password_hash' => Hash::make('x'),
            'rol_id' => $rol->id, 'activo' => true, 'primer_acceso' => false,
        ]);

        $this->actingAs($admin)
            ->post('/usuarios', ['nombre' => 'Intruso', 'email' => self::ENTRECOMILLADO, 'rol_id' => $rol->id])
            ->assertSessionHasErrors('email');

        $this->assertSame(1, User::count(), 'Se creó el usuario con un correo que inyecta cabeceras.');
    }

    /** Y el registro de postulantes, que también dispara un envío. */
    public function test_el_login_del_portal_rechaza_el_vector(): void
    {
        Postulante::create([
            'codigo' => 'POST-2026-00001', 'tipo_documento' => 'DNI', 'numero_documento' => '12345678',
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'email' => 'ana@ext.com',
            'estado' => 'nuevo', 'password_hash' => Hash::make('Temp#1234'), 'acceso_habilitado' => true,
        ]);

        $this->post('/portal/login', ['email' => self::ENTRECOMILLADO, 'password' => 'Temp#1234'])
            ->assertSessionHasErrors('email');
    }
}
