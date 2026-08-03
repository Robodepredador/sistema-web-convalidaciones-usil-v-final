<?php

namespace Tests\Feature;

use App\Models\Carrera;
use App\Models\CarreraExterna;
use App\Models\Ciclo;
use App\Models\Convalidacion;
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
use Illuminate\Database\QueryException;
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
            ]],
        ])->assertOk();

        $detalle = Simulacion::first()->detalles()->first();
        $this->assertNull($detalle->curso_usil_id);
        $this->assertEquals(0, (float) $detalle->creditos_reconocidos);
    }

    /**
     * Crea una simulación con una fila convalidable (mínimo para poder confirmarla).
     */
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

    /** RF-30: confirmar crea convalidación 1:1 e impide duplicado. */
    public function test_confirmar_convalidacion_es_unica(): void
    {
        $sim = $this->simulacionConvalidable();

        $this->actingAs($this->ctx['user'])->post("/simulaciones/{$sim->id}/confirmar");
        $this->actingAs($this->ctx['user'])->post("/simulaciones/{$sim->id}/confirmar"); // duplicado

        $this->assertEquals(1, Convalidacion::count());
        $this->assertEquals('confirmada', Convalidacion::first()->estado);
    }

    /** RF-46: anular cambia el estado sin eliminar el registro. */
    public function test_anular_convalidacion(): void
    {
        $sim = $this->simulacionConvalidable('888');
        $this->actingAs($this->ctx['user'])->post("/simulaciones/{$sim->id}/confirmar");
        $conv = Convalidacion::first();

        $this->actingAs($this->ctx['user'])->post("/convalidaciones/{$conv->id}/anular", ['motivo' => 'Error de datos']);

        $this->assertEquals('anulada', $conv->fresh()->estado);
        $this->assertEquals(1, Convalidacion::count()); // no se elimina
    }

    /**
     * BD-03: no se emite memorándum oficial sobre una simulación que no reconoce
     * ningún curso. Antes se creaba la convalidación con 0 cursos y 0 créditos.
     */
    public function test_no_confirma_simulacion_sin_cursos_convalidables(): void
    {
        $sim = Simulacion::create([
            'nombres' => 'Ana', 'apellidos' => 'Pérez', 'tipo_documento' => 'DNI',
            'numero_documento' => '777', 'email' => 'a@x.com', 'ciclo_postulacion' => '2026-1',
            'carrera_externa_id' => $this->ctx['carExt']->id, 'carrera_usil_id' => $this->ctx['carrera']->id,
            'malla_usil_id' => $this->ctx['malla']->id, 'estado' => 'generada', 'usuario_id' => $this->ctx['user']->id,
        ]);

        $this->actingAs($this->ctx['user'])
            ->post("/simulaciones/{$sim->id}/confirmar")
            ->assertSessionHasErrors('simulacion');

        $this->assertEquals(0, Convalidacion::count());
    }

    /** BD-03: una fila convalidable pero EXCLUIDA tampoco habilita la confirmación. */
    public function test_no_confirma_si_todas_las_filas_estan_excluidas(): void
    {
        $sim = $this->simulacionConvalidable('776');
        $sim->detalles()->update(['excluido' => true]);

        $this->actingAs($this->ctx['user'])
            ->post("/simulaciones/{$sim->id}/confirmar")
            ->assertSessionHasErrors('simulacion');

        $this->assertEquals(0, Convalidacion::count());
    }

    /**
     * BD-02: una simulación con convalidación confirmada es el sustento de un
     * documento oficial; no puede eliminarse mientras la resolución siga vigente.
     */
    public function test_no_elimina_simulacion_con_convalidacion_confirmada(): void
    {
        $sim = $this->simulacionConvalidable('666');
        $this->actingAs($this->ctx['user'])->post("/simulaciones/{$sim->id}/confirmar");

        $this->actingAs($this->ctx['user'])
            ->delete("/simulaciones/{$sim->id}", ['motivo' => 'Ya no aplica'])
            ->assertSessionHasErrors('simulacion');

        $this->assertNull($sim->fresh()->deleted_at, 'La simulación no debe eliminarse.');
    }

    /** BD-02: si la convalidación fue anulada, la simulación sí puede eliminarse. */
    public function test_elimina_simulacion_con_convalidacion_anulada(): void
    {
        $sim = $this->simulacionConvalidable('665');
        $this->actingAs($this->ctx['user'])->post("/simulaciones/{$sim->id}/confirmar");
        $conv = Convalidacion::first();
        $this->actingAs($this->ctx['user'])->post("/convalidaciones/{$conv->id}/anular", ['motivo' => 'Error de datos']);

        $this->actingAs($this->ctx['user'])->delete("/simulaciones/{$sim->id}", ['motivo' => 'Ya no aplica']);

        $this->assertNotNull($sim->fresh()->deleted_at);
    }

    /** BD-01: el número de memorándum es único a nivel de base de datos. */
    public function test_numero_de_memorandum_no_se_duplica(): void
    {
        $sim = $this->simulacionConvalidable('555');
        $this->actingAs($this->ctx['user'])->post("/simulaciones/{$sim->id}/confirmar");
        $numero = Convalidacion::first()->memorandum_numero;

        $otra = $this->simulacionConvalidable('554');

        $this->expectException(QueryException::class);
        Convalidacion::create([
            'simulacion_id' => $otra->id,
            'fecha_confirmacion' => now()->toDateString(),
            'memorandum_numero' => $numero,   // mismo número: la BD debe rechazarlo
            'estado' => Convalidacion::CONFIRMADA,
            'usuario_id' => $this->ctx['user']->id,
        ]);
    }
}
