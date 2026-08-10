<?php

namespace Tests\Feature;

use App\Models\Carrera;
use App\Models\CarreraExterna;
use App\Models\Ciclo;
use App\Models\CursoExterno;
use App\Models\CursoUsil;
use App\Models\Facultad;
use App\Models\InstitucionExterna;
use App\Models\MallaCurricular;
use App\Models\MallaExterna;
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
        $mallaExt = MallaExterna::create(['carrera_externa_id' => $carExt->id, 'anio' => '2026', 'version' => '1', 'activa' => true]);
        $cursoExt = CursoExterno::create(['malla_externa_id' => $mallaExt->id, 'nombre' => 'Matemática I']);

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
    }

    /** RF-26: la simulación genera la tabla comparativa automáticamente. */
    public function test_genera_detalle_automatico(): void
    {
        $this->actingAs($this->ctx['user'])->post('/simulaciones', [
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

        $sim = Simulacion::first();
        $this->assertNotNull($sim, 'La simulación no se creó.');
        $this->assertEquals('generada', $sim->estado);
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

    /**
     * El catálogo de la malla de origen viaja al espacio de trabajo para poder
     * elegir de él. Es distinto de `cursosOrigen`, que son las filas con las que
     * arranca la pantalla: meter aquí la malla haría que la simulación afirmara
     * que el alumno cursó el plan entero.
     */
    public function test_el_workspace_recibe_los_cursos_de_la_malla_de_origen(): void
    {
        $this->actingAs($this->ctx['user'])
            ->get('/simulaciones/simular/'.$this->ctx['postulante']->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('cursosOrigen', 0)
                ->has('cursosMallaOrigen', 1)
                ->where('cursosMallaOrigen.0.id', $this->ctx['cursoExt']->id)
                ->where('cursosMallaOrigen.0.nombre', 'Matemática I'));
    }

    /** Sin malla activa no hay de dónde elegir; el alta a mano sigue siendo el camino. */
    public function test_sin_malla_activa_el_catalogo_de_origen_llega_vacio(): void
    {
        MallaExterna::where('carrera_externa_id', $this->ctx['carExt']->id)->update(['activa' => false]);

        $this->actingAs($this->ctx['user'])
            ->get('/simulaciones/simular/'.$this->ctx['postulante']->id)
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('cursosMallaOrigen', 0));
    }

    /** Elegir de la malla deja el curso identificado, no solo su nombre en texto. */
    public function test_la_fila_elegida_de_la_malla_guarda_el_curso_externo(): void
    {
        $this->actingAs($this->ctx['user'])->post('/simulaciones', [
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

        $this->assertSame(
            $this->ctx['cursoExt']->id,
            Simulacion::first()->detalles()->first()->curso_externo_id
        );
    }

    /**
     * Escribir el nombre a mano sigue permitido y deja el identificador nulo: el
     * récord real puede traer cursos de un plan anterior o de otra carrera, y
     * obligar a elegir de la lista haría inevaluable justo el caso raro.
     */
    public function test_la_fila_escrita_a_mano_no_guarda_curso_externo(): void
    {
        $this->actingAs($this->ctx['user'])->post('/simulaciones', [
            'postulante_id' => $this->ctx['postulante']->id,
            'carrera_usil_id' => $this->ctx['carrera']->id,
            'metodo' => 'manual',
            'filas' => [[
                'curso_origen_nombre' => 'Taller de teatro (plan 2015)',
                'curso_usil_id' => $this->ctx['cursoUsil']->id,
                'clasificacion' => 'convalidable',
            ]],
        ]);

        $this->assertNull(Simulacion::first()->detalles()->first()->curso_externo_id);
    }

    /** BD-07: 'catalogo' desapareció con el catálogo de equivalencias. */
    public function test_rechaza_origen_catalogo_retirado(): void
    {
        $this->actingAs($this->ctx['user'])->postJson('/simulaciones', [
            'postulante_id' => $this->ctx['postulante']->id,
            'carrera_usil_id' => $this->ctx['carrera']->id,
            'metodo' => 'manual',
            'filas' => [[
                'curso_origen_nombre' => 'Matemática I',
                'curso_usil_id' => $this->ctx['cursoUsil']->id,
                'clasificacion' => 'convalidable',
                'origen' => 'catalogo',
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

    /** Una fila desaprobada/no convalidable no puede llevar destino ni sumar créditos. */
    public function test_fila_no_convalidable_no_lleva_destino(): void
    {
        $this->actingAs($this->ctx['user'])->postJson('/simulaciones', [
            'postulante_id' => $this->ctx['postulante']->id,
            'carrera_usil_id' => $this->ctx['carrera']->id,
            'metodo' => 'manual',
            'filas' => [[
                'curso_origen_nombre' => 'Inglés I',
                'curso_usil_id' => $this->ctx['cursoUsil']->id,   // llega con destino por error
                'clasificacion' => 'no_convalidable',
                'motivo' => 'Idiomas',
            ]],
        ])->assertOk();

        $detalle = Simulacion::first()->detalles()->first();
        $this->assertNull($detalle->curso_usil_id);
        $this->assertEquals(0, (float) $detalle->creditos_reconocidos);
        $this->assertSame('Idiomas', $detalle->motivo);
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
     * retirada; ver P-3 en docs/Plan-Correccion-Entrega-TI.md.
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
