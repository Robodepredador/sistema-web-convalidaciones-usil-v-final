<?php

namespace Tests\Feature;

use App\Models\Carrera;
use App\Models\CarreraExterna;
use App\Models\Ciclo;
use App\Models\CursoNoConvalidable;
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
use App\Services\ConvalidacionEngine;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * La política de cursos no convalidables tiene dos niveles: institucional
 * (Superusuario) y de carrera (su Administrativo). La de la carrera pisa a la
 * institucional, tanto para excluir de más como para levantar una exclusión.
 */
class NoConvalidablesPorCarreraTest extends TestCase
{
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $un = UnidadNegocio::create(['nombre' => 'USIL']);
        $fac = Facultad::create(['unidad_negocio_id' => $un->id, 'nombre' => 'Ing', 'codigo' => 'ING']);
        $ing = Carrera::create(['facultad_id' => $fac->id, 'nombre' => 'Ing. Civil', 'codigo' => 'CIV']);
        $adm = Carrera::create(['facultad_id' => $fac->id, 'nombre' => 'Administración', 'codigo' => 'ADM']);

        $admin = $this->usuario(Role::SUPERUSUARIO, 'admin');

        $mallaIng = MallaCurricular::create(['carrera_id' => $ing->id, 'anio' => 2026, 'version' => 'A',
            'origen_carga' => 'manual', 'usuario_id' => $admin->id]);
        $ciclo = Ciclo::create(['malla_id' => $mallaIng->id, 'numero' => 1]);
        CursoUsil::create(['ciclo_id' => $ciclo->id, 'codigo' => 'F1', 'nombre' => 'Física I', 'creditos' => 4]);

        // Administrativo con alcance SOLO a Ingeniería Civil.
        $coord = $this->usuario(Role::ADMINISTRATIVO, 'coord');
        $coord->carrerasPermitidas()->attach($ing->id);

        // Política institucional de partida: Geología no se convalida en ninguna
        // parte. No se usa 'fisica': la migración de datos que trae la política al
        // código ya siembra esa clave como institucional de fábrica en toda base
        // fresca, y reutilizarla chocaría con uq_no_convalidable_clave_carrera.
        CursoNoConvalidable::create([
            'carrera_id' => null, 'palabra_clave' => 'Geología',
            'clave_normalizada' => 'geologia', 'motivo' => 'Ciencia básica', 'activo' => true,
        ]);
        CursoNoConvalidable::limpiarCache();

        $this->ctx = compact('admin', 'coord', 'ing', 'adm', 'mallaIng');
    }

    private function usuario(string $rol, string $slug): User
    {
        return User::create([
            'nombre' => $rol, 'email' => "{$slug}@usil.edu.pe", 'password_hash' => Hash::make('x'),
            'rol_id' => Role::where('nombre', $rol)->firstOrFail()->id,
            'activo' => true, 'primer_acceso' => false,
        ]);
    }

    private function engine(): ConvalidacionEngine
    {
        return app(ConvalidacionEngine::class);
    }

    public function test_la_regla_institucional_rige_en_todas_las_carreras(): void
    {
        $this->assertTrue($this->engine()->esNoConvalidable('Geología General', $this->ctx['ing']->id));
        $this->assertTrue($this->engine()->esNoConvalidable('Geología General', $this->ctx['adm']->id));
    }

    public function test_el_coordinador_agrega_una_regla_solo_para_su_carrera(): void
    {
        $this->actingAs($this->ctx['coord'])
            ->post("/mallas/{$this->ctx['mallaIng']->id}/no-convalidables",
                ['palabra_clave' => 'Topografía', 'motivo' => 'Se dicta con software propio'])
            ->assertRedirect();

        CursoNoConvalidable::limpiarCache();
        $this->assertTrue($this->engine()->esNoConvalidable('Topografía I', $this->ctx['ing']->id));
        $this->assertFalse($this->engine()->esNoConvalidable('Topografía I', $this->ctx['adm']->id),
            'La regla de una carrera se filtró a otra.');
    }

    /** El caso inverso: la carrera levanta una exclusión institucional. */
    public function test_el_coordinador_levanta_una_regla_institucional_en_su_carrera(): void
    {
        $this->actingAs($this->ctx['coord'])
            ->post("/mallas/{$this->ctx['mallaIng']->id}/no-convalidables",
                ['palabra_clave' => 'Geología', 'motivo' => 'Excepción de Ing. Civil', 'activo' => false])
            ->assertRedirect();

        CursoNoConvalidable::limpiarCache();
        $this->assertFalse($this->engine()->esNoConvalidable('Geología I', $this->ctx['ing']->id));
        $this->assertTrue($this->engine()->esNoConvalidable('Geología I', $this->ctx['adm']->id),
            'Levantar la regla en una carrera la levantó en todas.');
    }

    /**
     * Cada regla institucional se levanta por separado.
     *
     * «Geología» y «Geología General» son dos reglas distintas de la política:
     * dejar sin efecto una no toca a la otra. Por eso la pantalla de la malla
     * lista las institucionales una a una, cada cual con su acción.
     */
    public function test_levantar_una_regla_no_arrastra_a_las_de_nombre_parecido(): void
    {
        // Como en setUp(): esta clave debe quedarse fuera de las que siembra
        // 2026_08_05_000004_mueve_no_convalidables_del_codigo_a_la_bd, o chocará
        // con uq_no_convalidable_clave_carrera en cada base fresca.
        CursoNoConvalidable::create([
            'carrera_id' => null, 'palabra_clave' => 'Geología General',
            'clave_normalizada' => 'geologia general', 'motivo' => 'Ciencia básica', 'activo' => true,
        ]);

        $this->actingAs($this->ctx['coord'])
            ->post("/mallas/{$this->ctx['mallaIng']->id}/no-convalidables",
                ['palabra_clave' => 'Geología', 'activo' => false])
            ->assertRedirect();

        CursoNoConvalidable::limpiarCache();
        $this->assertFalse($this->engine()->esNoConvalidable('Geología I', $this->ctx['ing']->id));
        $this->assertTrue($this->engine()->esNoConvalidable('Geología General', $this->ctx['ing']->id),
            'Levantar «Geología» no debe levantar «Geología General»: son dos reglas.');
    }

    public function test_el_coordinador_no_toca_la_malla_de_otra_carrera(): void
    {
        $mallaAdm = MallaCurricular::create(['carrera_id' => $this->ctx['adm']->id, 'anio' => 2026,
            'version' => 'A', 'origen_carga' => 'manual', 'usuario_id' => $this->ctx['admin']->id]);

        $this->actingAs($this->ctx['coord'])
            ->post("/mallas/{$mallaAdm->id}/no-convalidables", ['palabra_clave' => 'Cálculo'])
            ->assertForbidden();
    }

    public function test_el_coordinador_no_edita_una_regla_institucional(): void
    {
        $institucional = CursoNoConvalidable::whereNull('carrera_id')->firstOrFail();

        $this->actingAs($this->ctx['coord'])
            ->patch("/mallas/{$this->ctx['mallaIng']->id}/no-convalidables/{$institucional->id}", ['activo' => false])
            ->assertForbidden();

        $this->assertTrue((bool) $institucional->fresh()->activo);
    }

    /** Y desde Configuración tampoco se editan las de una carrera. */
    public function test_configuracion_no_edita_una_regla_de_carrera(): void
    {
        $propia = CursoNoConvalidable::create([
            'carrera_id' => $this->ctx['ing']->id, 'palabra_clave' => 'Topografía',
            'clave_normalizada' => 'topografia', 'activo' => true,
        ]);

        $this->actingAs($this->ctx['admin'])
            ->patch("/configuracion/no-convalidables/{$propia->id}", ['activo' => false])
            ->assertForbidden();
    }

    /** Quitar la regla propia devuelve la carrera a la política institucional. */
    public function test_quitar_la_regla_propia_restituye_la_institucional(): void
    {
        $this->actingAs($this->ctx['coord'])->post("/mallas/{$this->ctx['mallaIng']->id}/no-convalidables",
            ['palabra_clave' => 'Geología', 'activo' => false])->assertRedirect();

        CursoNoConvalidable::limpiarCache();
        $this->assertFalse($this->engine()->esNoConvalidable('Geología I', $this->ctx['ing']->id));

        $propia = CursoNoConvalidable::where('carrera_id', $this->ctx['ing']->id)->firstOrFail();
        $this->actingAs($this->ctx['coord'])
            ->delete("/mallas/{$this->ctx['mallaIng']->id}/no-convalidables/{$propia->id}")
            ->assertRedirect();

        CursoNoConvalidable::limpiarCache();
        $this->assertTrue($this->engine()->esNoConvalidable('Geología I', $this->ctx['ing']->id));
    }

    /** El motivo de la regla viaja al expediente, para que el documento lo explique. */
    public function test_el_motivo_de_la_regla_llega_al_expediente(): void
    {
        $this->assertSame('Ciencia básica',
            $this->engine()->motivoNoConvalidable('Geología General', $this->ctx['ing']->id));
        $this->assertFalse($this->engine()->motivoNoConvalidable('Cálculo I', $this->ctx['ing']->id));
    }

    /** Un descarte sin motivo es justo lo que dejaba al postulante sin explicación. */
    public function test_marcar_no_convalidable_exige_motivo(): void
    {
        $tipo = TipoInstitucion::create(['nombre' => 'Universidad']);
        $inst = InstitucionExterna::create(['tipo_id' => $tipo->id, 'nombre' => 'UNI']);
        $carExt = CarreraExterna::create(['institucion_id' => $inst->id, 'nombre' => 'Sistemas']);

        $p = Postulante::create([
            'codigo' => 'POST-2026-00050', 'tipo_documento' => 'DNI', 'numero_documento' => '55555555',
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'email' => 'ana@x.com',
            'ciclo_postulacion' => '2026-1', 'institucion_origen_id' => $inst->id,
            'carrera_externa_id' => $carExt->id, 'carrera_destino_id' => $this->ctx['ing']->id,
            'estado' => 'en_evaluacion', 'usuario_id' => $this->ctx['admin']->id, 'revision_estado' => 'aprobada',
        ]);
        $p->destinos()->create(['carrera_id' => $this->ctx['ing']->id]);

        $cuerpo = fn (array $extra) => array_merge([
            'postulante_id' => $p->id,
            'carrera_usil_id' => $this->ctx['ing']->id,
            'metodo' => 'manual',
            'filas' => [array_merge([
                'curso_origen_nombre' => 'Química General',
                'clasificacion' => 'no_convalidable',
            ], $extra)],
        ], []);

        $this->actingAs($this->ctx['admin'])->postJson('/simulaciones', $cuerpo([]))->assertStatus(422);
        $this->assertNull(Simulacion::first());

        $this->actingAs($this->ctx['admin'])
            ->postJson('/simulaciones', $cuerpo(['motivo' => 'No forma parte del plan de estudios']))
            ->assertOk();

        $this->assertSame('No forma parte del plan de estudios',
            Simulacion::firstOrFail()->detalles()->firstOrFail()->motivo);
    }
}
