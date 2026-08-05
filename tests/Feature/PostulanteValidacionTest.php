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
 * Formato de los campos del registro de postulantes (CU-01).
 * El asistente valida en el cliente, pero la regla que manda es esta.
 */
class PostulanteValidacionTest extends TestCase
{
    use RefreshDatabase;

    private function asesor(): User
    {
        $this->seed(RoleSeeder::class);

        return User::create([
            'nombre' => 'Asesor', 'email' => uniqid().'@usil.edu.pe',
            'password_hash' => Hash::make('x'), 'rol_id' => Role::where('nombre', Role::ASESOR)->firstOrFail()->id,
            'activo' => true, 'primer_acceso' => false,
        ]);
    }

    public function test_rechaza_basura_en_los_campos_del_formulario(): void
    {
        $this->actingAs($this->asesor())->post('/postulantes', [
            'tipo_documento' => 'DNI', 'numero_documento' => 'asgsdgsadfsdfasdf',
            'nombres' => 'Sadf123', 'apellido_paterno' => 'X', 'apellido_materno' => '@@@',
            'nacionalidad' => 'Peruana99', 'fecha_nacimiento' => '2030-01-01',
            'email' => 'nuevo@ex.com', 'telefono' => 'no-es-telefono',
            'ciclo_postulacion' => '2026-3',
        ])->assertInvalid([
            'numero_documento', 'nombres', 'apellido_paterno', 'apellido_materno',
            'nacionalidad', 'fecha_nacimiento', 'telefono', 'ciclo_postulacion',
        ]);

        $this->assertSame(0, Postulante::count(), 'Nada debe persistirse si la validación falla.');
    }

    /** Cada tipo de documento exige su propio formato. */
    public function test_el_formato_del_documento_depende_del_tipo(): void
    {
        $asesor = $this->asesor();

        // 8 dígitos son un DNI válido, pero no alcanzan para un carné de extranjería.
        $this->actingAs($asesor)->post('/postulantes', [
            'borrador' => true, 'tipo_documento' => 'CE', 'numero_documento' => '12345678',
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez',
        ])->assertInvalid('numero_documento');

        $this->actingAs($asesor)->post('/postulantes', [
            'borrador' => true, 'tipo_documento' => 'CE', 'numero_documento' => '001234567',
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez',
        ])->assertValid();
    }

    /** Un registro legítimo pasa: acentos, apóstrofos y opcionales vacíos incluidos. */
    public function test_acepta_datos_validos_con_opcionales_vacios(): void
    {
        $this->actingAs($this->asesor())->post('/postulantes', [
            'borrador' => true,
            'tipo_documento' => 'DNI', 'numero_documento' => '45678912',
            'nombres' => 'María José', 'apellido_paterno' => "D'Angelo", 'apellido_materno' => 'de la Cruz',
            'fecha_nacimiento' => '2000-05-04', 'nacionalidad' => 'Peruana',
            'telefono' => '+51 999 888 777',
            // Los opcionales llegan vacíos desde el asistente y no deben estorbar.
            'genero' => '', 'observaciones' => '', 'email' => '',
        ])->assertValid()->assertRedirect('/postulantes');

        $this->assertDatabaseHas('postulantes', ['numero_documento' => '45678912']);
    }
}
