<?php

namespace Tests\Feature;

use App\Models\AuditoriaLog;
use App\Models\Carrera;
use App\Models\CarreraExterna;
use App\Models\Ciclo;
use App\Models\Convalidacion;
use App\Models\CursoUsil;
use App\Models\Facultad;
use App\Models\InstitucionExterna;
use App\Models\MallaCurricular;
use App\Models\Postulante;
use App\Models\Role;
use App\Models\Simulacion;
use App\Models\TipoInstitucion;
use App\Models\UnidadNegocio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Integridad del acto oficial: un expediente que ya produjo un memorándum no
 * cambia de contenido, y el documento que se descarga es el que se emitió.
 */
class IntegridadConvalidacionTest extends TestCase
{
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();

        $rol = Role::create(['nombre' => Role::ADMIN]);
        $user = User::create(['nombre' => 'A', 'email' => 'a@usil.edu.pe', 'password_hash' => Hash::make('x'), 'rol_id' => $rol->id, 'activo' => true, 'primer_acceso' => false]);

        $un = UnidadNegocio::create(['nombre' => 'USIL']);
        $fac = Facultad::create(['unidad_negocio_id' => $un->id, 'nombre' => 'Ing', 'codigo' => 'ING']);
        $carrera = Carrera::create(['facultad_id' => $fac->id, 'nombre' => 'SW', 'codigo' => 'SW']);
        $malla = MallaCurricular::create(['carrera_id' => $carrera->id, 'anio' => 2026, 'version' => 'A', 'origen_carga' => 'manual', 'usuario_id' => $user->id]);
        $ciclo = Ciclo::create(['malla_id' => $malla->id, 'numero' => 1]);
        $cursoUsil = CursoUsil::create(['ciclo_id' => $ciclo->id, 'codigo' => 'U1', 'nombre' => 'Cálculo', 'creditos' => 4]);
        $otroUsil = CursoUsil::create(['ciclo_id' => $ciclo->id, 'codigo' => 'U2', 'nombre' => 'Álgebra', 'creditos' => 3.5]);

        $tipo = TipoInstitucion::create(['nombre' => 'Universidad']);
        $inst = InstitucionExterna::create(['tipo_id' => $tipo->id, 'nombre' => 'UNI']);
        $carExt = CarreraExterna::create(['institucion_id' => $inst->id, 'nombre' => 'Sistemas']);

        $postulante = Postulante::create([
            'codigo' => 'POST-2026-00001', 'tipo_documento' => 'DNI', 'numero_documento' => '12345678',
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'email' => 'ana@x.com',
            'ciclo_postulacion' => '2026-1', 'institucion_origen_id' => $inst->id,
            'carrera_externa_id' => $carExt->id, 'carrera_destino_id' => $carrera->id,
            'estado' => 'en_evaluacion', 'usuario_id' => $user->id, 'revision_estado' => 'aprobada',
        ]);
        $postulante->destinos()->create(['carrera_id' => $carrera->id]);

        $this->ctx = compact('user', 'carrera', 'postulante', 'cursoUsil', 'otroUsil');
    }

    /** Crea una simulación con un curso convalidado y devuelve el modelo. */
    private function simular(): Simulacion
    {
        $this->actingAs($this->ctx['user'])->postJson('/simulaciones', [
            'postulante_id' => $this->ctx['postulante']->id,
            'carrera_usil_id' => $this->ctx['carrera']->id,
            'metodo' => 'manual',
            'filas' => [[
                'curso_origen_nombre' => 'Matemática I',
                'curso_usil_id' => $this->ctx['cursoUsil']->id,
                'nota_origen' => '15',
                'clasificacion' => 'convalidable',
            ]],
        ])->assertOk();

        return Simulacion::firstOrFail();
    }

    private function confirmar(Simulacion $sim): Convalidacion
    {
        $this->actingAs($this->ctx['user'])->post("/simulaciones/{$sim->id}/confirmar")->assertRedirect();

        return Convalidacion::firstOrFail();
    }

    /** El cuerpo de la petición de edición, con un curso extra. */
    private function cuerpoConDosCursos(): array
    {
        return [
            'postulante_id' => $this->ctx['postulante']->id,
            'carrera_usil_id' => $this->ctx['carrera']->id,
            'metodo' => 'manual',
            'filas' => [
                ['curso_origen_nombre' => 'Matemática I', 'curso_usil_id' => $this->ctx['cursoUsil']->id, 'nota_origen' => '15', 'clasificacion' => 'convalidable'],
                ['curso_origen_nombre' => 'Álgebra lineal', 'curso_usil_id' => $this->ctx['otroUsil']->id, 'nota_origen' => '14', 'clasificacion' => 'convalidable'],
            ],
        ];
    }

    public function test_no_se_edita_el_detalle_de_una_simulacion_convalidada(): void
    {
        $sim = $this->simular();
        $this->confirmar($sim);

        $this->actingAs($this->ctx['user'])
            ->putJson("/simulaciones/{$sim->id}", $this->cuerpoConDosCursos())
            ->assertStatus(422);

        $this->assertEquals(4.0, (float) $sim->fresh()->detalles()->sum('creditos_reconocidos'));
    }

    public function test_no_se_abre_el_editor_de_una_simulacion_convalidada(): void
    {
        $sim = $this->simular();
        $this->confirmar($sim);

        $this->actingAs($this->ctx['user'])->get("/simulaciones/{$sim->id}/editar")->assertStatus(422);
    }

    public function test_una_convalidacion_anulada_sigue_congelando_el_expediente(): void
    {
        $sim = $this->simular();
        $conv = $this->confirmar($sim);

        $this->actingAs($this->ctx['user'])
            ->post("/convalidaciones/{$conv->id}/anular", ['motivo' => 'Sílabo no corresponde'])
            ->assertRedirect();

        // El memorándum anulado se conserva: su detalle es la evidencia de lo que certificó.
        $this->actingAs($this->ctx['user'])
            ->putJson("/simulaciones/{$sim->id}", $this->cuerpoConDosCursos())
            ->assertStatus(422);
    }

    public function test_anular_devuelve_la_simulacion_a_generada(): void
    {
        $sim = $this->simular();
        $conv = $this->confirmar($sim);
        $this->assertSame('aceptada', $sim->fresh()->estado);

        $this->actingAs($this->ctx['user'])
            ->post("/convalidaciones/{$conv->id}/anular", ['motivo' => 'Error de mapeo'])
            ->assertRedirect();

        $this->assertSame('generada', $sim->fresh()->estado);
    }

    public function test_excluir_un_curso_exige_motivo_y_deja_auditoria(): void
    {
        $sim = $this->simular();
        $detalle = $sim->detalles()->firstOrFail();

        $this->actingAs($this->ctx['user'])
            ->patchJson("/simulaciones/{$sim->id}/detalle/{$detalle->id}", [])
            ->assertStatus(422);
        $this->assertFalse((bool) $detalle->fresh()->excluido);

        $this->actingAs($this->ctx['user'])
            ->patch("/simulaciones/{$sim->id}/detalle/{$detalle->id}", ['motivo' => 'El sílabo no cubre las competencias'])
            ->assertRedirect();

        $this->assertTrue((bool) $detalle->fresh()->excluido);
        $this->assertTrue(AuditoriaLog::where('tabla_afectada', 'simulacion_detalle')
            ->where('registro_id', $detalle->id)->exists(), 'La exclusión no dejó traza de auditoría.');
    }

    public function test_no_se_excluye_un_curso_de_una_simulacion_convalidada(): void
    {
        $sim = $this->simular();
        $detalle = $sim->detalles()->firstOrFail();
        $this->confirmar($sim);

        $this->actingAs($this->ctx['user'])
            ->patchJson("/simulaciones/{$sim->id}/detalle/{$detalle->id}", ['motivo' => 'Cambio de criterio'])
            ->assertStatus(422);

        $this->assertFalse((bool) $detalle->fresh()->excluido);
    }

    /** El número guardado es el que se imprime, y por tanto el que encuentra el buscador. */
    public function test_el_numero_de_memorandum_guardado_es_el_impreso(): void
    {
        $sim = $this->simular();
        $conv = $this->confirmar($sim);

        $this->assertSame(
            str_pad((string) $conv->id, 4, '0', STR_PAD_LEFT).' - 2026-1 / CPEL-USIL',
            $conv->memorandum_numero
        );

        // Y por tanto el buscador lo encuentra: es el número que el postulante trae en el papel.
        $this->actingAs($this->ctx['user'])
            ->get('/convalidaciones?q='.urlencode($conv->memorandum_numero))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('convalidaciones.data.0.memorandum', $conv->memorandum_numero));
    }

    /** La descarga sirve el archivo emitido, no una versión recalculada. */
    public function test_la_descarga_sirve_el_memorandum_archivado(): void
    {
        $sim = $this->simular();
        $conv = $this->confirmar($sim);

        $this->assertNotNull($conv->memorandum_pdf_path);
        Storage::put($conv->memorandum_pdf_path, 'ARCHIVO-EMITIDO');

        $contenido = $this->actingAs($this->ctx['user'])
            ->get("/convalidaciones/{$conv->id}/memorandum")
            ->assertOk()
            ->streamedContent();

        $this->assertSame('ARCHIVO-EMITIDO', $contenido,
            'La descarga re-renderizó el memorándum en lugar de servir el emitido.');
    }
}
