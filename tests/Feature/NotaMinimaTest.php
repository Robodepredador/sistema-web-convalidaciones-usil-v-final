<?php

namespace Tests\Feature;

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
 * Nota mínima aprobatoria (Reglamento de Estudios de Pregrado, Art. 15: la
 * escala es vigesimal y la mínima aprobatoria es 11). Antes el filtro vivía
 * solo en el navegador y solo en la rama de extracción con IA.
 */
class NotaMinimaTest extends TestCase
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

        $postulante = Postulante::create([
            'codigo' => 'POST-2026-00001', 'tipo_documento' => 'DNI', 'numero_documento' => '12345678',
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'email' => 'ana@x.com',
            'ciclo_postulacion' => '2026-1', 'institucion_origen_id' => $inst->id,
            'carrera_externa_id' => $carExt->id, 'carrera_destino_id' => $carrera->id,
            'estado' => 'en_evaluacion', 'usuario_id' => $user->id, 'revision_estado' => 'aprobada',
        ]);
        $postulante->destinos()->create(['carrera_id' => $carrera->id]);

        $this->ctx = compact('user', 'carrera', 'postulante', 'cursoUsil');
    }

    /** @param  array<string,mixed>  $fila */
    private function guardar(array $fila, array $extra = [])
    {
        return $this->actingAs($this->ctx['user'])->postJson('/simulaciones', array_merge([
            'postulante_id' => $this->ctx['postulante']->id,
            'carrera_usil_id' => $this->ctx['carrera']->id,
            'metodo' => 'manual',
            'escala_notas' => '0-20',
            'nota_minima' => 11,
            'filas' => [array_merge([
                'curso_origen_nombre' => 'Matemática I',
                'curso_usil_id' => $this->ctx['cursoUsil']->id,
                'clasificacion' => 'convalidable',
            ], $fila)],
        ], $extra));
    }

    public function test_rechaza_convalidar_un_curso_desaprobado(): void
    {
        $this->guardar(['nota_origen' => '08'])->assertStatus(422);

        $this->assertNull(Simulacion::first(), 'Se guardó una convalidación de un curso desaprobado.');
    }

    public function test_acepta_el_curso_aprobado_justo_en_la_minima(): void
    {
        $this->guardar(['nota_origen' => '11'])->assertOk();

        $this->assertEquals(4.0, (float) Simulacion::first()->detalles()->sum('creditos_reconocidos'));
    }

    /** La coma decimal es habitual en los certificados peruanos. */
    public function test_reconoce_la_nota_con_coma_decimal(): void
    {
        $this->guardar(['nota_origen' => '10,5'])->assertStatus(422);
    }

    /** Una nota no numérica no se puede juzgar: no se bloquea. */
    public function test_no_bloquea_una_nota_no_numerica(): void
    {
        $this->guardar(['nota_origen' => 'APROBADO'])->assertOk();
    }

    /** Una fila marcada como desaprobada sigue registrándose (queda como constancia). */
    public function test_registra_el_curso_desaprobado_clasificado_como_tal(): void
    {
        $this->guardar(['nota_origen' => '08', 'clasificacion' => 'desaprobado', 'curso_usil_id' => null])->assertOk();

        $d = Simulacion::first()->detalles()->firstOrFail();
        $this->assertSame('desaprobado', $d->clasificacion);
        $this->assertEquals(0.0, (float) $d->creditos_reconocidos);
    }

    public function test_no_se_puede_bajar_la_nota_minima_del_piso_normativo(): void
    {
        $this->guardar(['nota_origen' => '08'], ['nota_minima' => 0])->assertStatus(422);
        $this->assertNull(Simulacion::first());
    }

    /** Una carrera puede exigir más que el reglamento, no menos. */
    public function test_admite_una_nota_minima_mas_exigente(): void
    {
        $this->guardar(['nota_origen' => '12'], ['nota_minima' => 14])->assertStatus(422);
        $this->guardar(['nota_origen' => '15'], ['nota_minima' => 14])->assertOk();
    }
}
