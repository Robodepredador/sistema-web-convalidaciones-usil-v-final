<?php

namespace Tests\Feature;

use App\Models\Carrera;
use App\Models\Facultad;
use App\Models\Postulante;
use App\Models\PostulanteDocumento;
use App\Models\Role;
use App\Models\UnidadNegocio;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Consentimiento para el tratamiento de datos personales.
 *
 * Art. 15 del Reglamento de Admisión: el postulante lo otorga «de manera
 * expresa e inequívoca», incluidos los datos de carácter sensible (Ley 29733).
 * Es además la puerta del envío de su récord académico al proveedor de IA.
 */
class ConsentimientoDatosTest extends TestCase
{
    use RefreshDatabase;

    private function asesor(): User
    {
        $this->seed(RoleSeeder::class);

        return User::create([
            'nombre' => 'Asesor', 'email' => uniqid().'@usil.edu.pe',
            'password_hash' => Hash::make('x'),
            'rol_id' => Role::where('nombre', Role::ASESOR)->firstOrFail()->id,
            'activo' => true, 'primer_acceso' => false,
        ]);
    }

    private function carrera(): Carrera
    {
        $un = UnidadNegocio::create(['nombre' => 'USIL']);
        $fac = Facultad::create(['unidad_negocio_id' => $un->id, 'nombre' => 'Ing', 'codigo' => 'ING']);

        return Carrera::create(['facultad_id' => $fac->id, 'nombre' => 'SW', 'codigo' => 'SW']);
    }

    private function datos(array $extra = []): array
    {
        return array_merge([
            'tipo_documento' => 'DNI', 'numero_documento' => '45678912',
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez',
            'email' => 'ana@ex.com', 'ciclo_postulacion' => '2026-2',
            'carrera_destino_ids' => [$this->carrera()->id],
        ], $extra);
    }

    public function test_no_se_registra_un_postulante_sin_su_consentimiento(): void
    {
        $this->actingAs($this->asesor())
            ->post('/postulantes', $this->datos())
            ->assertInvalid('consentimiento_datos');

        $this->assertNull(Postulante::first());
    }

    public function test_el_consentimiento_queda_fechado(): void
    {
        $this->actingAs($this->asesor())
            ->post('/postulantes', $this->datos(['consentimiento_datos' => true]))
            ->assertRedirect();

        $p = Postulante::firstOrFail();
        $this->assertNotNull($p->consentimiento_datos_en);
        $this->assertTrue($p->tieneConsentimientoDatos());
    }

    /** Un borrador todavía no es un registro: no se le exige. */
    public function test_el_borrador_no_exige_consentimiento(): void
    {
        $this->actingAs($this->asesor())->post('/postulantes', [
            'borrador' => true, 'tipo_documento' => 'DNI', 'numero_documento' => '45678912',
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez',
        ])->assertRedirect();

        $p = Postulante::firstOrFail();
        $this->assertSame('borrador', $p->estado);
        $this->assertFalse($p->tieneConsentimientoDatos());
    }

    /** Sin consentimiento no se manda el récord al proveedor de IA. */
    public function test_la_extraccion_con_ia_exige_consentimiento(): void
    {
        $asesor = $this->asesor();
        $this->actingAs($asesor)->post('/postulantes', $this->datos())->assertInvalid();

        $p = Postulante::create([
            'codigo' => 'POST-2026-00009', 'tipo_documento' => 'DNI', 'numero_documento' => '11111111',
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'email' => 'sin@consent.com',
            'estado' => 'nuevo', 'usuario_id' => $asesor->id, 'revision_estado' => 'aprobada',
        ]);
        $doc = PostulanteDocumento::create([
            'postulante_id' => $p->id, 'tipo' => 'certificado',
            'nombre_original' => 'record.pdf', 'ruta' => "postulantes/{$p->id}/record.pdf", 'tamano' => 1024,
        ]);

        $admin = User::create([
            'nombre' => 'Admin', 'email' => 'admin@usil.edu.pe', 'password_hash' => Hash::make('x'),
            'rol_id' => Role::where('nombre', Role::SUPERUSUARIO)->firstOrFail()->id,
            'activo' => true, 'primer_acceso' => false,
        ]);

        $resp = $this->actingAs($admin)->postJson('/simulaciones/extraer-ia', ['documento_id' => $doc->id]);

        $resp->assertStatus(422);
        $this->assertStringContainsString('consentimiento', $resp->json('message'));
    }
}
