<?php

namespace Tests\Feature;

use App\Mail\AccesoPortalMail;
use App\Models\Postulante;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PostulanteAccesoTest extends TestCase
{
    use RefreshDatabase;

    public function test_flag_cambio_password_por_defecto_false(): void
    {
        $p = Postulante::create([
            'codigo' => 'POST-2026-90001', 'tipo_documento' => 'DNI', 'numero_documento' => '90000001',
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'email' => 'ana.acceso@example.com',
        ]);

        $this->assertFalse($p->fresh()->debe_cambiar_password);
    }

    private function asesor(): User
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $rol = Role::where('nombre', Role::ASESOR)->firstOrFail();

        return User::create([
            'nombre' => 'Asesor', 'email' => uniqid() . '@usil.edu.pe',
            'password_hash' => Hash::make('x'), 'rol_id' => $rol->id,
            'activo' => true, 'primer_acceso' => false,
        ]);
    }

    public function test_reset_acceso_envia_correo_y_marca_cambio(): void
    {
        Mail::fake();
        $asesor = $this->asesor();
        $p = Postulante::create([
            'codigo' => 'POST-2026-90002', 'tipo_documento' => 'DNI', 'numero_documento' => '90000002',
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'email' => 'ana.reset@example.com',
            'usuario_id' => $asesor->id,
        ]);

        $this->actingAs($asesor)->patch("/postulantes/{$p->id}/reset-acceso")->assertRedirect();

        $this->assertTrue($p->fresh()->debe_cambiar_password);
        $this->assertTrue($p->fresh()->acceso_habilitado);
        Mail::assertSent(AccesoPortalMail::class, fn ($m) => $m->hasTo('ana.reset@example.com'));
    }

    public function test_reset_acceso_exige_propiedad(): void
    {
        Mail::fake();
        $asesorA = $this->asesor();
        $asesorB = $this->asesor();
        $p = Postulante::create([
            'codigo' => 'POST-2026-90003', 'tipo_documento' => 'DNI', 'numero_documento' => '90000003',
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'email' => 'ana.reset2@example.com',
            'usuario_id' => $asesorA->id,
        ]);

        // Un asesor distinto no puede resetear el acceso de un postulante ajeno.
        $this->actingAs($asesorB)->patch("/postulantes/{$p->id}/reset-acceso")->assertForbidden();

        // El dueño sí puede.
        $this->actingAs($asesorA)->patch("/postulantes/{$p->id}/reset-acceso")->assertRedirect();
    }
}
