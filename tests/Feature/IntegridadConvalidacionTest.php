<?php

namespace Tests\Feature;

use App\Models\AuditoriaLog;
use App\Models\Carrera;
use App\Models\CarreraExterna;
use App\Models\Ciclo;
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
use Tests\TestCase;

/**
 * Integridad del detalle de la preconvalidación: cambiar lo que reconoce un
 * expediente es una decisión académica y tiene que quedar justificada y trazada.
 *
 * Este archivo probaba antes la integridad del ACTO OFICIAL: que un expediente
 * con memorándum emitido no cambiara de contenido y que la descarga sirviera el
 * documento archivado. Esas reglas desaparecieron con el módulo de Convalidación
 * —el memorándum se gestiona ahora fuera del sistema— y con ellas el candado:
 * `Simulacion::estaCerrada()` se apoyaba en que existiera una convalidación
 * confirmada, así que hoy devuelve siempre falso.
 *
 * La consecuencia está aceptada: se decidió al retirar el módulo, en agosto de
 * 2026, y se fija abajo con una prueba. Si algún día vuelve un candado, que sea
 * por decisión y no por accidente.
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

    /** RF-27: excluir un curso cambia los créditos reconocidos; exige motivo y deja traza. */
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

    /**
     * Consecuencia aceptada de retirar Convalidación: ya no hay estado que
     * congele el expediente, así que el mapeo sigue siendo editable después de
     * que el postulante lo haya visto en el portal.
     *
     * Se fija aquí a propósito. Si el día de mañana se decide reponer un candado
     * —una acción explícita de «cerrar expediente»— esta prueba fallará y
     * obligará a revisar la decisión en vez de dejarla pasar en silencio.
     */
    public function test_una_preconvalidacion_sigue_siendo_editable(): void
    {
        $sim = $this->simular();

        $this->actingAs($this->ctx['user'])
            ->putJson("/simulaciones/{$sim->id}", [
                'postulante_id' => $this->ctx['postulante']->id,
                'carrera_usil_id' => $this->ctx['carrera']->id,
                'metodo' => 'manual',
                'filas' => [
                    ['curso_origen_nombre' => 'Matemática I', 'curso_usil_id' => $this->ctx['cursoUsil']->id, 'nota_origen' => '15', 'clasificacion' => 'convalidable'],
                    ['curso_origen_nombre' => 'Álgebra lineal', 'curso_usil_id' => $this->ctx['otroUsil']->id, 'nota_origen' => '14', 'clasificacion' => 'convalidable'],
                ],
            ])
            ->assertOk();

        $this->assertEquals(7.5, (float) $sim->fresh()->detalles()->sum('creditos_reconocidos'));
    }
}
