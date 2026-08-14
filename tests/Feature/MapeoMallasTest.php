<?php

namespace Tests\Feature;

use App\Models\Carrera;
use App\Models\CarreraExterna;
use App\Models\Ciclo;
use App\Models\CursoExterno;
use App\Models\CursoUsil;
use App\Models\EquivalenciaMalla;
use App\Models\Facultad;
use App\Models\InstitucionExterna;
use App\Models\MallaCurricular;
use App\Models\MallaExterna;
use App\Models\Role;
use App\Models\TipoInstitucion;
use App\Models\UnidadNegocio;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Mapeo de una malla externa contra una malla USIL: el criterio declarado por el
 * coordinador, antes de que existan expedientes que lo respalden.
 *
 * Lo que se protege es la regla 1 a 1 —la misma que la simulación ya exige, para
 * que el catálogo no pueda proponer algo que luego el sistema rechace— y que esa
 * regla se aplique POR PAR DE MALLAS y no globalmente.
 */
class MapeoMallasTest extends TestCase
{
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $admin = $this->usuario(Role::SUPERUSUARIO, 'admin');

        $un = UnidadNegocio::create(['nombre' => 'USIL']);
        $fac = Facultad::create(['unidad_negocio_id' => $un->id, 'nombre' => 'Ing', 'codigo' => 'ING']);

        // Carrera destino con dos cursos, y una segunda carrera para medir el alcance
        // y que la regla 1 a 1 no sea más estricta de la cuenta.
        [$sw, $mallaSw, $cursosSw] = $this->carreraUsil($fac, 'Software', 'SW', ['Cálculo', 'Álgebra'], $admin);
        [$civil, $mallaCivil, $cursosCivil] = $this->carreraUsil($fac, 'Civil', 'CIV', ['Topografía'], $admin);

        $tipo = TipoInstitucion::create(['nombre' => 'Universidad']);
        $inst = InstitucionExterna::create(['tipo_id' => $tipo->id, 'nombre' => 'UNI']);
        $carExt = CarreraExterna::create(['institucion_id' => $inst->id, 'nombre' => 'Sistemas']);
        $mallaExt = MallaExterna::create(['carrera_externa_id' => $carExt->id, 'anio' => '2026', 'version' => '1', 'activa' => true]);
        $cursosExt = collect(['Matemática I', 'Matemática II'])
            ->map(fn ($n) => CursoExterno::create(['malla_externa_id' => $mallaExt->id, 'nombre' => $n]));

        $coord = $this->usuario(Role::ADMINISTRATIVO, 'coord');
        $coord->carrerasPermitidas()->sync([$sw->id]);   // solo Software

        $this->ctx = compact('admin', 'coord', 'sw', 'mallaSw', 'cursosSw', 'civil', 'mallaCivil',
            'cursosCivil', 'inst', 'carExt', 'mallaExt', 'cursosExt');
    }

    private function usuario(string $rol, string $alias): User
    {
        return User::create([
            'nombre' => $alias, 'email' => "{$alias}@usil.edu.pe", 'password_hash' => Hash::make('x'),
            'rol_id' => Role::where('nombre', $rol)->firstOrFail()->id,
            'activo' => true, 'primer_acceso' => false,
        ]);
    }

    /** @return array{0:Carrera,1:MallaCurricular,2:Collection} */
    private function carreraUsil(Facultad $fac, string $nombre, string $codigo, array $cursos, User $user): array
    {
        $carrera = Carrera::create(['facultad_id' => $fac->id, 'nombre' => $nombre, 'codigo' => $codigo]);
        $malla = MallaCurricular::create([
            'carrera_id' => $carrera->id, 'anio' => 2026, 'version' => 'A',
            'activa' => true, 'origen_carga' => 'manual', 'usuario_id' => $user->id,
        ]);
        $ciclo = Ciclo::create(['malla_id' => $malla->id, 'numero' => 1]);
        $creados = collect($cursos)->map(fn ($n, $i) => CursoUsil::create([
            'ciclo_id' => $ciclo->id, 'codigo' => $codigo.'-'.($i + 1), 'nombre' => $n, 'creditos' => 4,
        ]));

        return [$carrera, $malla, $creados];
    }

    /** Guarda un par. Por defecto: primer curso externo → primer curso de Software. */
    private function guardar(array $datos = [], ?User $como = null)
    {
        return $this->actingAs($como ?? $this->ctx['admin'])->postJson('/mapeo-mallas', array_merge([
            'carrera_usil_id' => $this->ctx['sw']->id,
            'curso_externo_id' => $this->ctx['cursosExt'][0]->id,
            'curso_usil_id' => $this->ctx['cursosSw'][0]->id,
        ], $datos));
    }

    public function test_guarda_un_par_contra_las_dos_mallas(): void
    {
        $this->guardar()->assertOk();

        $par = EquivalenciaMalla::firstOrFail();
        $this->assertSame($this->ctx['cursosExt'][0]->id, $par->curso_externo_id);
        $this->assertSame($this->ctx['cursosSw'][0]->id, $par->curso_usil_id);
        // Las mallas se derivan en el servidor: no llegan desde el cliente.
        $this->assertSame($this->ctx['mallaExt']->id, $par->malla_externa_id);
        $this->assertSame($this->ctx['mallaSw']->id, $par->malla_usil_id);
        $this->assertSame($this->ctx['admin']->id, $par->usuario_id);
    }

    /** 1 a 1: un curso externo apunta a un solo curso USIL por malla destino. */
    public function test_rechaza_el_mismo_curso_externo_hacia_otro_curso_usil(): void
    {
        $this->guardar()->assertOk();

        $this->guardar(['curso_usil_id' => $this->ctx['cursosSw'][1]->id])->assertStatus(422);
        $this->assertSame(1, EquivalenciaMalla::count());
    }

    /** 1 a 1: un curso USIL recibe un solo curso externo por malla de origen. */
    public function test_rechaza_el_mismo_curso_usil_desde_otro_curso_externo(): void
    {
        $this->guardar()->assertOk();

        $this->guardar(['curso_externo_id' => $this->ctx['cursosExt'][1]->id])->assertStatus(422);
        $this->assertSame(1, EquivalenciaMalla::count());
    }

    /** La regla es por par de mallas: contra otra carrera USIL el mismo curso puede convalidar distinto. */
    public function test_el_mismo_curso_externo_si_puede_mapearse_hacia_otra_carrera_usil(): void
    {
        $this->guardar()->assertOk();

        $this->guardar([
            'carrera_usil_id' => $this->ctx['civil']->id,
            'curso_usil_id' => $this->ctx['cursosCivil'][0]->id,
        ])->assertOk();

        $this->assertSame(2, EquivalenciaMalla::count());
    }

    /** Sin borrado lógico: la fila borrada no puede seguir ocupando el índice único. */
    public function test_borrar_un_par_permite_volver_a_crearlo(): void
    {
        $this->guardar()->assertOk();
        $id = EquivalenciaMalla::firstOrFail()->id;

        $this->actingAs($this->ctx['admin'])->deleteJson("/mapeo-mallas/{$id}")->assertOk();
        $this->assertSame(0, EquivalenciaMalla::count());

        $this->guardar()->assertOk();
        $this->assertSame(1, EquivalenciaMalla::count());
    }

    /** RF-40: se mapea solo hacia las carreras asignadas. */
    public function test_no_permite_mapear_hacia_una_carrera_fuera_del_alcance(): void
    {
        $this->guardar([
            'carrera_usil_id' => $this->ctx['civil']->id,
            'curso_usil_id' => $this->ctx['cursosCivil'][0]->id,
        ], $this->ctx['coord'])->assertForbidden();

        $this->assertSame(0, EquivalenciaMalla::count());
    }

    /** El coordinador sí mapea hacia la suya. */
    public function test_el_coordinador_mapea_hacia_su_carrera(): void
    {
        $this->guardar([], $this->ctx['coord'])->assertOk();
    }

    /** Solo se convalida hacia cursos del plan destino. */
    public function test_rechaza_un_curso_usil_de_otra_malla(): void
    {
        $this->guardar(['curso_usil_id' => $this->ctx['cursosCivil'][0]->id])->assertStatus(422);
    }

    /** «Puede hacer este proceso cuantas veces desee»: lo guardado reaparece. */
    public function test_reentrar_en_el_mismo_par_de_mallas_muestra_lo_ya_guardado(): void
    {
        $this->guardar()->assertOk();

        $this->actingAs($this->ctx['admin'])
            ->getJson('/mapeo-mallas/cursos?malla_externa_id='.$this->ctx['mallaExt']->id
                .'&carrera_usil_id='.$this->ctx['sw']->id)
            ->assertOk()
            ->assertJsonCount(2, 'cursosExternos')
            ->assertJsonCount(2, 'cursosUsil')
            ->assertJsonCount(1, 'pares')
            ->assertJsonPath('pares.0.curso_externo_id', $this->ctx['cursosExt'][0]->id);
    }

    /** La pantalla índice responde «¿qué llevo mapeado?». */
    public function test_el_indice_lista_los_pares_de_mallas_mapeados(): void
    {
        $this->guardar()->assertOk();

        $this->actingAs($this->ctx['admin'])->get('/mapeo-mallas')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('MapeoMallas/Index')
                ->has('mapeos', 1)
                ->where('mapeos.0.equivalencias', 1)
                ->where('mapeos.0.carrera_usil', 'Software'));
    }
}
