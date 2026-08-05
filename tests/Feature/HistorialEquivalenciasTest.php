<?php

namespace Tests\Feature;

use App\Models\Carrera;
use App\Models\CarreraExterna;
use App\Models\Ciclo;
use App\Models\Convalidacion;
use App\Models\CursoUsil;
use App\Models\Facultad;
use App\Models\InstitucionExterna;
use App\Models\MallaCurricular;
use App\Models\Role;
use App\Models\Simulacion;
use App\Models\SimulacionDetalle;
use App\Models\TipoInstitucion;
use App\Models\UnidadNegocio;
use App\Models\User;
use App\Services\HistorialEquivalenciasService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Base de conocimiento histórica: agregación de lo ya convalidado.
 *
 * Cubre lo que se rompería en silencio: el conteo, el reconocimiento de
 * variantes del nombre, el filtro de alcance por carrera y la colisión de rutas
 * con `simulaciones/{simulacion}/excel`.
 */
class HistorialEquivalenciasTest extends TestCase
{
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $admin = User::create([
            'nombre' => 'Admin', 'email' => 'admin@usil.edu.pe', 'password_hash' => Hash::make('x'),
            'rol_id' => Role::where('nombre', Role::SUPERUSUARIO)->firstOrFail()->id,
            'activo' => true, 'primer_acceso' => false,
        ]);

        $un = UnidadNegocio::create(['nombre' => 'USIL']);
        $fac = Facultad::create(['unidad_negocio_id' => $un->id, 'nombre' => 'Ing', 'codigo' => 'ING']);

        // Dos carreras destino: la del coordinador y una ajena, para medir el alcance.
        [$sw, $mallaSw, $calculo] = $this->carreraConCurso($fac, 'Software', 'SW', 'Cálculo de una variable', $admin);
        [$civil, $mallaCivil, $topografia] = $this->carreraConCurso($fac, 'Civil', 'CIV', 'Topografía', $admin);

        $tipo = TipoInstitucion::create(['nombre' => 'Instituto']);
        $senati = InstitucionExterna::create(['tipo_id' => $tipo->id, 'nombre' => 'SENATI']);
        $carExt = CarreraExterna::create(['institucion_id' => $senati->id, 'nombre' => 'Ing. de Software']);

        $coord = User::create([
            'nombre' => 'Coord', 'email' => 'coord@usil.edu.pe', 'password_hash' => Hash::make('x'),
            'rol_id' => Role::where('nombre', Role::COORDINADOR)->firstOrFail()->id,
            'activo' => true, 'primer_acceso' => false,
        ]);
        $coord->carrerasPermitidas()->sync([$sw->id]);   // solo Software

        $this->ctx = compact('admin', 'coord', 'sw', 'mallaSw', 'calculo', 'civil', 'mallaCivil', 'topografia', 'carExt', 'senati');
    }

    /** @return array{0:Carrera,1:MallaCurricular,2:CursoUsil} */
    private function carreraConCurso(Facultad $fac, string $nombre, string $codigo, string $curso, User $user): array
    {
        $carrera = Carrera::create(['facultad_id' => $fac->id, 'nombre' => $nombre, 'codigo' => $codigo]);
        $malla = MallaCurricular::create(['carrera_id' => $carrera->id, 'anio' => 2026, 'version' => 'A', 'origen_carga' => 'manual', 'usuario_id' => $user->id]);
        $ciclo = Ciclo::create(['malla_id' => $malla->id, 'numero' => 1]);
        $cursoUsil = CursoUsil::create(['ciclo_id' => $ciclo->id, 'codigo' => $codigo.'-1', 'nombre' => $curso, 'creditos' => 4]);

        return [$carrera, $malla, $cursoUsil];
    }

    /** Una simulación con una equivalencia ya decidida. */
    private function equivalencia(string $origen, CursoUsil $destino, Carrera $carrera, MallaCurricular $malla, array $detalle = []): Simulacion
    {
        $sim = Simulacion::create([
            'nombres' => 'Ana', 'apellidos' => 'Pérez', 'tipo_documento' => 'DNI',
            'numero_documento' => (string) random_int(10000000, 99999999), 'email' => 'a@x.com',
            'ciclo_postulacion' => '2026-1', 'carrera_externa_id' => $this->ctx['carExt']->id,
            'carrera_usil_id' => $carrera->id, 'malla_usil_id' => $malla->id,
            'estado' => 'generada', 'usuario_id' => $this->ctx['admin']->id,
        ]);

        SimulacionDetalle::create(array_merge([
            'simulacion_id' => $sim->id,
            'curso_usil_id' => $destino->id,
            'curso_origen_nombre' => $origen,
            'clasificacion' => 'convalidable',
            'creditos_reconocidos' => 4,
            'excluido' => false,
            'origen' => 'manual',
        ], $detalle));

        return $sim;
    }

    private function servicio(): HistorialEquivalenciasService
    {
        return app(HistorialEquivalenciasService::class);
    }

    /** El mismo par decidido dos veces cuenta como dos. */
    public function test_cuenta_las_veces_que_se_repite_una_equivalencia(): void
    {
        $this->equivalencia('Matemática I', $this->ctx['calculo'], $this->ctx['sw'], $this->ctx['mallaSw']);
        $this->equivalencia('Matemática I', $this->ctx['calculo'], $this->ctx['sw'], $this->ctx['mallaSw']);

        $antecedentes = $this->servicio()->antecedentes('Matemática I', $this->ctx['sw']->id);

        $this->assertCount(1, $antecedentes, 'Los dos casos son la misma equivalencia: un solo renglón.');
        $this->assertSame(2, $antecedentes[0]['veces']);
        $this->assertSame('Cálculo de una variable', $antecedentes[0]['curso_usil']);
        $this->assertTrue($antecedentes[0]['mismo_destino']);
    }

    /** «Matemática 1» y «Matemática I» son el mismo curso; «Cálculo diferencial» no. */
    public function test_reconoce_variantes_del_nombre_pero_no_cursos_distintos(): void
    {
        $this->equivalencia('Matemática I', $this->ctx['calculo'], $this->ctx['sw'], $this->ctx['mallaSw']);

        $this->assertCount(1, $this->servicio()->antecedentes('Matemática 1', $this->ctx['sw']->id));
        $this->assertCount(1, $this->servicio()->antecedentes('MATEMATICA I', $this->ctx['sw']->id));
        $this->assertSame([], $this->servicio()->antecedentes('Cálculo diferencial', $this->ctx['sw']->id));
    }

    /** Un expediente que llegó a memorándum pesa más y se distingue. */
    public function test_distingue_las_confirmadas_con_memorandum(): void
    {
        $conMemo = $this->equivalencia('Matemática I', $this->ctx['calculo'], $this->ctx['sw'], $this->ctx['mallaSw']);
        $this->equivalencia('Matemática I', $this->ctx['calculo'], $this->ctx['sw'], $this->ctx['mallaSw']);

        Convalidacion::create([
            'simulacion_id' => $conMemo->id, 'fecha_confirmacion' => now()->toDateString(),
            'memorandum_numero' => 'MEMO-001', 'estado' => Convalidacion::CONFIRMADA,
            'usuario_id' => $this->ctx['admin']->id,
        ]);

        $antecedentes = $this->servicio()->antecedentes('Matemática I', $this->ctx['sw']->id);

        $this->assertSame(2, $antecedentes[0]['veces']);
        $this->assertSame(1, $antecedentes[0]['confirmadas']);
    }

    /** Lo que no fue una decisión de convalidación no es antecedente. */
    public function test_ignora_filas_excluidas_y_simulaciones_eliminadas(): void
    {
        $this->equivalencia('Matemática I', $this->ctx['calculo'], $this->ctx['sw'], $this->ctx['mallaSw'], ['excluido' => true]);
        $this->equivalencia('Matemática I', $this->ctx['calculo'], $this->ctx['sw'], $this->ctx['mallaSw'], ['clasificacion' => 'desaprobado']);
        $borrada = $this->equivalencia('Matemática I', $this->ctx['calculo'], $this->ctx['sw'], $this->ctx['mallaSw']);
        $borrada->delete();

        $this->assertSame([], $this->servicio()->antecedentes('Matemática I', $this->ctx['sw']->id));
    }

    /** RF-40: el histórico de una carrera fuera del alcance no se filtra al coordinador. */
    public function test_el_alcance_oculta_el_historico_de_otra_carrera(): void
    {
        $this->equivalencia('Matemática I', $this->ctx['topografia'], $this->ctx['civil'], $this->ctx['mallaCivil']);

        $permitidas = [$this->ctx['sw']->id];
        $this->assertSame([], $this->servicio()->antecedentes('Matemática I', $this->ctx['sw']->id, null, $permitidas));

        // Sin restricción de alcance (Superusuario) sí se ve.
        $this->assertCount(1, $this->servicio()->antecedentes('Matemática I', $this->ctx['sw']->id, null, null));
    }

    /** La pantalla responde y pagina sobre la consulta agrupada. */
    public function test_pantalla_del_historico_lista_las_equivalencias(): void
    {
        $this->equivalencia('Matemática I', $this->ctx['calculo'], $this->ctx['sw'], $this->ctx['mallaSw']);

        $this->actingAs($this->ctx['coord'])->get('/simulaciones/historico')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Simulaciones/Historico')
                ->has('filas.data', 1)
                ->where('filas.data.0.curso_usil', 'Cálculo de una variable')
                ->where('filas.data.0.veces', 1));
    }

    /**
     * `simulaciones/{simulacion}/excel` no exige número: si se registrara antes,
     * capturaría esta ruta y la descarga fallaría con «simulación no encontrada».
     */
    public function test_la_descarga_del_historico_no_la_captura_la_ruta_de_simulacion(): void
    {
        $this->equivalencia('Matemática I', $this->ctx['calculo'], $this->ctx['sw'], $this->ctx['mallaSw']);

        $this->actingAs($this->ctx['coord'])->get('/simulaciones/historico/excel')->assertOk();
    }

    /** El endpoint del panel responde JSON y respeta el alcance del que pregunta. */
    public function test_endpoint_de_antecedentes(): void
    {
        $this->equivalencia('Matemática I', $this->ctx['calculo'], $this->ctx['sw'], $this->ctx['mallaSw']);

        $this->actingAs($this->ctx['coord'])
            ->getJson('/simulaciones/antecedentes?curso='.urlencode('Matemática I').'&carrera_usil_id='.$this->ctx['sw']->id)
            ->assertOk()
            ->assertJsonPath('antecedentes.0.curso_usil', 'Cálculo de una variable')
            ->assertJsonPath('antecedentes.0.veces', 1);

        // Preguntar por una carrera fuera del alcance se corta antes de responder.
        $this->actingAs($this->ctx['coord'])
            ->getJson('/simulaciones/antecedentes?curso=X&carrera_usil_id='.$this->ctx['civil']->id)
            ->assertForbidden();
    }
}
