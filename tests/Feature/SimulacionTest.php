<?php

namespace Tests\Feature;

use App\Models\Carrera;
use App\Models\CarreraExterna;
use App\Models\Ciclo;
use App\Models\CursoExterno;
use App\Models\CursoUsil;
use App\Models\Equivalencia;
use App\Models\Facultad;
use App\Models\InstitucionExterna;
use App\Models\MallaCurricular;
use App\Models\Postulante;
use App\Models\Role;
use App\Models\Simulacion;
use App\Models\SimulacionDetalle;
use App\Models\TipoInstitucion;
use App\Models\UnidadNegocio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SimulacionTest extends TestCase
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

        $tipo = TipoInstitucion::create(['nombre' => 'Universidad']);
        $inst = InstitucionExterna::create(['tipo_id' => $tipo->id, 'nombre' => 'UNI']);
        $carExt = CarreraExterna::create(['institucion_id' => $inst->id, 'nombre' => 'Sistemas']);
        // Los cursos externos cuelgan de la malla oficial de la carrera de origen.
        $cursoExt = CursoExterno::create(['carrera_externa_id' => $carExt->id, 'nombre' => 'Matemática I']);

        $postulante = Postulante::create([
            'codigo' => 'POST-2026-00001', 'tipo_documento' => 'DNI', 'numero_documento' => '12345678',
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'email' => 'ana@x.com',
            'ciclo_postulacion' => '2026-1', 'institucion_origen_id' => $inst->id,
            'carrera_externa_id' => $carExt->id, 'carrera_destino_id' => $carrera->id,
            'estado' => 'nuevo', 'usuario_id' => $user->id,
            // Simular exige el expediente ya aprobado por Admisión.
            'revision_estado' => 'aprobada',
        ]);

        $this->ctx = compact('user', 'carrera', 'malla', 'carExt', 'postulante', 'cursoExt', 'cursoUsil');

        // Autorizar una equivalencia por defecto para que las simulaciones pasen
        Equivalencia::create([
            'carrera_externa_id' => $carExt->id,
            'curso_usil_id' => $cursoUsil->id,
            'curso_externo_id' => $cursoExt->id,
        ]);
    }

    public function test_genera_detalle_automatico(): void
    {
        $response = $this->actingAs($this->ctx['user'])->post('/simulaciones', [
            'postulante_id' => $this->ctx['postulante']->id,
            'carrera_usil_id' => $this->ctx['carrera']->id,
            'metodo' => 'manual',
            'filas' => [[
                'curso_origen_nombre' => 'Matemática I',
                'curso_externo_id' => $this->ctx['cursoExt']->id,
                'curso_usil_id' => $this->ctx['cursoUsil']->id,
                'clasificacion' => 'convalidable',
            ]],
        ]);

        if ($response->status() !== 200 && $response->status() !== 302) {
            dump($response->json());
            $response->assertOk();
        }

        $sim = Simulacion::first();
        $this->assertNotNull($sim, 'La simulación no se creó.');
        $this->assertEquals('borrador', $sim->estado);
        $this->assertEquals(1, $sim->detalles()->count());
    }

    /** Las filas emparejadas por similitud se guardan con su origen. */
    public function test_guardar_con_origen_similitud(): void
    {
        $resp = $this->actingAs($this->ctx['user'])->postJson('/simulaciones', [
            'postulante_id' => $this->ctx['postulante']->id,
            'carrera_usil_id' => $this->ctx['carrera']->id,
            'metodo' => 'manual',
            'filas' => [[
                'curso_origen_nombre' => 'Matemática I',
                'curso_externo_id' => $this->ctx['cursoExt']->id,
                'curso_usil_id' => $this->ctx['cursoUsil']->id,
                'clasificacion' => 'convalidable',
                'confianza' => 100,
                'origen' => 'similitud',
            ]],
        ]);

        $resp->assertOk();
        $this->assertEquals('similitud', Simulacion::first()->detalles()->first()->origen);
    }

    /** El desplegable de cada curso trae solo lo que el especialista autorizó. */
    public function test_la_simulacion_solo_ofrece_las_equivalencias_registradas(): void
    {
        $cursoExt2 = CursoExterno::create(['carrera_externa_id' => $this->ctx['carExt']->id, 'nombre' => 'Matemática II']);

        Equivalencia::create([
            'carrera_externa_id' => $this->ctx['carExt']->id,
            'curso_usil_id' => $this->ctx['cursoUsil']->id,
            'curso_externo_id' => $cursoExt2->id,
        ]);

        $cursoUsil2 = CursoUsil::create(['ciclo_id' => $this->ctx['cursoUsil']->ciclo_id, 'codigo' => 'U2', 'nombre' => 'Física', 'creditos' => 4]);

        $respuesta = $this->actingAs($this->ctx['user'])
            ->get("/simulaciones/simular/{$this->ctx['postulante']->id}?carrera={$this->ctx['carrera']->id}");

        $respuesta->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('cursosMalla')
                ->where('cursosMalla.0.opciones', fn ($ops) => count($ops) === 2)
                ->where('cursosMalla.1.opciones', fn ($ops) => count($ops) === 0)
            );
    }

    /** Y guardar una equivalencia no autorizada se rechaza en el servidor. */
    public function test_no_se_guarda_una_equivalencia_que_nadie_autorizo(): void
    {
        $externoSinAutorizar = CursoExterno::create(['carrera_externa_id' => $this->ctx['carExt']->id, 'nombre' => 'Física I']);

        $this->actingAs($this->ctx['user'])->post('/simulaciones', [
            'postulante_id' => $this->ctx['postulante']->id,
            'carrera_usil_id' => $this->ctx['carrera']->id,
            'metodo' => 'manual',
            'filas' => [[
                'curso_usil_id' => $this->ctx['cursoUsil']->id,
                'curso_externo_id' => $externoSinAutorizar->id,
                'curso_origen_nombre' => 'Física I',
                'clasificacion' => 'convalidable',
            ]],
        ])->assertStatus(422);
    }

    /** Un curso USIL de otra carrera/malla no es un destino válido. */
    public function test_rechaza_curso_usil_de_otra_malla(): void
    {
        $otraCarrera = Carrera::create(['facultad_id' => $this->ctx['carrera']->facultad_id, 'nombre' => 'Civil', 'codigo' => 'CIV']);
        $otraMalla = MallaCurricular::create(['carrera_id' => $otraCarrera->id, 'anio' => 2026, 'version' => 'A', 'origen_carga' => 'manual', 'usuario_id' => $this->ctx['user']->id]);
        $otroCiclo = Ciclo::create(['malla_id' => $otraMalla->id, 'numero' => 1]);
        $cursoAjeno = CursoUsil::create(['ciclo_id' => $otroCiclo->id, 'codigo' => 'X1', 'nombre' => 'Topografía', 'creditos' => 3]);

        $resp = $this->actingAs($this->ctx['user'])->postJson('/simulaciones', [
            'postulante_id' => $this->ctx['postulante']->id,
            'carrera_usil_id' => $this->ctx['carrera']->id,
            'metodo' => 'manual',
            'filas' => [[
                'curso_origen_nombre' => 'Matemática I',
                'curso_usil_id' => $cursoAjeno->id,
                'clasificacion' => 'convalidable',
            ]],
        ]);

        $resp->assertStatus(422);
        $this->assertEquals(0, Simulacion::count());
    }

    /** Una fila desaprobada/no convalidable puede registrarse vacía (sin externo). */
    public function test_fila_vacia_significa_no_convalidado(): void
    {
        $this->actingAs($this->ctx['user'])->postJson('/simulaciones', [
            'postulante_id' => $this->ctx['postulante']->id,
            'carrera_usil_id' => $this->ctx['carrera']->id,
            'metodo' => 'manual',
            'filas' => [[
                'curso_usil_id' => $this->ctx['cursoUsil']->id,
                'curso_externo_id' => null,
                'curso_origen_nombre' => '',
                'clasificacion' => 'no_convalidable',
            ]],
        ])->assertOk();

        $detalle = Simulacion::first()->detalles()->first();
        $this->assertEquals($this->ctx['cursoUsil']->id, $detalle->curso_usil_id);
        $this->assertNull($detalle->curso_externo_id);
        $this->assertEquals(0, (float) $detalle->creditos_reconocidos);
    }

    /** Crea una simulación con una fila convalidable ya persistida. */
    private function simulacionConvalidable(string $documento = '999'): Simulacion
    {
        $sim = Simulacion::create([
            'nombres' => 'Ana', 'apellidos' => 'Pérez', 'tipo_documento' => 'DNI',
            'numero_documento' => $documento, 'email' => 'a@x.com', 'ciclo_postulacion' => '2026-1',
            'carrera_externa_id' => $this->ctx['carExt']->id, 'carrera_usil_id' => $this->ctx['carrera']->id,
            'malla_usil_id' => $this->ctx['malla']->id, 'estado' => 'generada', 'usuario_id' => $this->ctx['user']->id,
        ]);

        SimulacionDetalle::create([
            'simulacion_id' => $sim->id,
            'curso_usil_id' => $this->ctx['cursoUsil']->id,
            'curso_origen_nombre' => 'Matemática I',
            'clasificacion' => 'convalidable',
            'creditos_reconocidos' => 4,
            'excluido' => false,
            'origen' => 'manual',
        ]);

        return $sim;
    }

    /**
     * Eliminar una preconvalidación es un acto trazable: exige motivo y conserva
     * el registro (borrado lógico).
     *
     * Aquí vivían las reglas BD-01/BD-02/BD-03, que protegían el memorándum
     * oficial: no confirmar sin cursos, no eliminar lo que sustentaba un acto
     * vigente, número de memorándum único. Se retiraron con el módulo de
     * Convalidación. Lo que queda en pie —y se prueba— es la trazabilidad del
     * borrado.
     *
     * NOTA: sin `confirmar()`, `Simulacion::estaCerrada()` devuelve siempre
     * falso, así que una preconvalidación ya entregada al postulante puede
     * editarse y eliminarse sin candado. Es una consecuencia aceptada de la
     * retirada del módulo de convalidación, decidida en agosto de 2026.
     */
    public function test_eliminar_simulacion_exige_motivo_y_conserva_el_registro(): void
    {
        $sim = $this->simulacionConvalidable('666');

        // Sin motivo no se elimina.
        $this->actingAs($this->ctx['user'])
            ->deleteJson("/simulaciones/{$sim->id}")
            ->assertStatus(422);
        $this->assertNull($sim->fresh()->deleted_at);

        // Con motivo: borrado lógico, el registro y su detalle siguen ahí.
        $this->actingAs($this->ctx['user'])
            ->delete("/simulaciones/{$sim->id}", ['motivo' => 'Duplicada por error de carga'])
            ->assertRedirect();

        $eliminada = Simulacion::withTrashed()->findOrFail($sim->id);
        $this->assertNotNull($eliminada->deleted_at);
        $this->assertSame('Duplicada por error de carga', $eliminada->motivo_eliminacion);
    }
}
