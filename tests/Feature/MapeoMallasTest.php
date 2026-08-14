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
use App\Models\Role;
use App\Models\TipoInstitucion;
use App\Models\UnidadNegocio;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MapeoMallasTest extends TestCase
{
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $un = UnidadNegocio::create(['nombre' => 'USIL']);
        $fac = Facultad::create(['unidad_negocio_id' => $un->id, 'nombre' => 'Ing', 'codigo' => 'ING']);

        $especialista = $this->usuario(Role::ESPECIALISTA, 'especialista');

        [$sw, $mallaSw, $cursosSw] = $this->carreraUsil($fac, 'Software', 'SW', ['Algoritmia Básica', 'Fundamentos de Programación', 'Introducción a Ingeniería de Software'], $especialista);
        [$civil, $mallaCivil, $cursosCivil] = $this->carreraUsil($fac, 'Civil', 'CIV', ['Topografía'], $especialista);

        $especialista->carrerasPermitidas()->sync([$sw->id]); // alcance

        $tipo = TipoInstitucion::create(['nombre' => 'Universidad']);
        $inst = InstitucionExterna::create(['tipo_id' => $tipo->id, 'nombre' => 'UNI']);
        $carreraExterna = CarreraExterna::create(['institucion_id' => $inst->id, 'nombre' => 'Sistemas']);

        $this->ctx = compact('especialista', 'sw', 'mallaSw', 'cursosSw', 'civil', 'cursosCivil', 'carreraExterna');
    }

    private function usuario(string $rol, string $alias): User
    {
        return User::create([
            'nombre' => $alias, 'email' => "{$alias}@usil.edu.pe", 'password_hash' => Hash::make('x'),
            'rol_id' => Role::where('nombre', $rol)->firstOrFail()->id,
            'activo' => true, 'primer_acceso' => false,
        ]);
    }

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

    /** El especialista registra tres opciones para el mismo curso USIL y las
     *  tres quedan disponibles. La pantalla anterior solo admitía una. */
    public function test_el_especialista_registra_varias_opciones_para_un_curso(): void
    {
        $especialista = $this->ctx['especialista'];
        $cursoUsil = $this->ctx['cursosSw'][0];
        $carreraExterna = $this->ctx['carreraExterna'];

        foreach (['Algoritmia Básica', 'Fundamentos de Programación', 'Introducción a Ingeniería de Software'] as $nombre) {
            $this->actingAs($especialista)
                ->postJson('/equivalencias-catalogo', [
                    'carrera_usil_id' => $this->ctx['sw']->id,
                    'curso_usil_id' => $cursoUsil->id,
                    'carrera_externa_id' => $carreraExterna->id,
                    'nombre_externo' => $nombre,
                ])
                ->assertSuccessful();
        }

        $this->assertSame(3, Equivalencia::where('curso_usil_id', $cursoUsil->id)->count());
    }

    /** Escribir el mismo nombre con otra grafía no crea un curso nuevo. */
    public function test_el_nombre_se_normaliza_antes_de_catalogar(): void
    {
        $especialista = $this->ctx['especialista'];
        $cursoUsil = $this->ctx['cursosSw'][0];
        $otroCursoUsil = $this->ctx['cursosSw'][1];
        $carreraExterna = $this->ctx['carreraExterna'];

        $this->actingAs($especialista)->postJson('/equivalencias-catalogo', [
            'carrera_usil_id' => $this->ctx['sw']->id,
            'curso_usil_id' => $cursoUsil->id, 'carrera_externa_id' => $carreraExterna->id,
            'nombre_externo' => 'Algoritmia Básica',
        ]);

        $this->actingAs($especialista)->postJson('/equivalencias-catalogo', [
            'carrera_usil_id' => $this->ctx['sw']->id,
            'curso_usil_id' => $otroCursoUsil->id, 'carrera_externa_id' => $carreraExterna->id,
            'nombre_externo' => 'ALGORITMIA  BASICA',
        ]);

        $this->assertSame(1, CursoExterno::where('carrera_externa_id', $carreraExterna->id)->count(),
            'La segunda grafía debió reutilizar el curso, no crear otro.');
    }

    /** Y el especialista no toca carreras USIL fuera de su alcance. */
    public function test_el_especialista_no_registra_en_carrera_ajena(): void
    {
        $especialista = $this->ctx['especialista'];
        $cursoDeOtraCarrera = $this->ctx['cursosCivil'][0];
        $carreraExterna = $this->ctx['carreraExterna'];

        $this->actingAs($especialista)->postJson('/equivalencias-catalogo', [
            'carrera_usil_id' => $this->ctx['civil']->id,
            'curso_usil_id' => $cursoDeOtraCarrera->id,
            'carrera_externa_id' => $carreraExterna->id,
            'nombre_externo' => 'Cualquiera',
        ])->assertForbidden();
    }
}
