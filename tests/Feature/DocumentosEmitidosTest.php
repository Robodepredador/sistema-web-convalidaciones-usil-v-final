<?php

namespace Tests\Feature;

use App\Http\Controllers\ConvalidacionController;
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
use App\Models\TipoInstitucion;
use App\Models\UnidadNegocio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fidelidad de los documentos emitidos: lo que dicen tiene que coincidir con lo
 * registrado. Se renderiza la plantilla (HTML) en vez del PDF porque el binario
 * de DomPDF lleva el texto comprimido y no se puede afirmar sobre él.
 */
class DocumentosEmitidosTest extends TestCase
{
    use RefreshDatabase;

    private Simulacion $sim;

    protected function setUp(): void
    {
        parent::setUp();

        $rol = Role::create(['nombre' => Role::ADMIN]);
        $user = User::create(['nombre' => 'Decana Ruiz', 'email' => 'd@usil.edu.pe', 'password_hash' => Hash::make('x'), 'rol_id' => $rol->id, 'activo' => true, 'primer_acceso' => false]);

        $un = UnidadNegocio::create(['nombre' => 'USIL']);
        $fac = Facultad::create(['unidad_negocio_id' => $un->id, 'nombre' => 'Ing', 'codigo' => 'ING']);
        $carrera = Carrera::create(['facultad_id' => $fac->id, 'nombre' => 'SW', 'codigo' => 'SW']);
        $malla = MallaCurricular::create(['carrera_id' => $carrera->id, 'anio' => 2026, 'version' => 'A', 'origen_carga' => 'manual', 'usuario_id' => $user->id]);
        $ciclo = Ciclo::create(['malla_id' => $malla->id, 'numero' => 1]);
        // 3.5 créditos: el caso que el redondeo convertía en 4.
        $curso = CursoUsil::create(['ciclo_id' => $ciclo->id, 'codigo' => 'U1', 'nombre' => 'Álgebra', 'creditos' => 3.5]);

        $tipo = TipoInstitucion::create(['nombre' => 'Universidad']);
        $inst = InstitucionExterna::create(['tipo_id' => $tipo->id, 'nombre' => 'UNI']);
        $carExt = CarreraExterna::create(['institucion_id' => $inst->id, 'nombre' => 'Sistemas']);

        $this->sim = Simulacion::create([
            'nombres' => 'Ana', 'apellidos' => 'Pérez', 'tipo_documento' => 'DNI',
            'numero_documento' => '12345678', 'email' => 'ana@x.com', 'ciclo_postulacion' => '2026-1',
            'carrera_externa_id' => $carExt->id,
            'carrera_usil_id' => $carrera->id, 'malla_usil_id' => $malla->id,
            'estado' => 'generada', 'metodo' => 'manual', 'usuario_id' => $user->id,
        ]);
        $this->sim->detalles()->create([
            'curso_usil_id' => $curso->id, 'curso_origen_nombre' => 'Álgebra lineal',
            'nota_origen' => '15', 'clasificacion' => 'convalidable',
            'creditos_reconocidos' => 3.5, 'excluido' => false, 'origen' => 'manual',
        ]);
        $this->sim->load(['detalles.cursoUsil.ciclo', 'carreraUsil.facultad']);

        $this->usuario = $user;
    }

    private User $usuario;

    public function test_el_memorandum_no_redondea_los_creditos(): void
    {
        $conv = Convalidacion::create([
            'simulacion_id' => $this->sim->id, 'fecha_confirmacion' => now()->toDateString(),
            'memorandum_numero' => '0001 - 2026-1 / CPEL-USIL', 'estado' => Convalidacion::CONFIRMADA,
            'usuario_id' => $this->usuario->id,
        ]);

        $html = view('pdf.memorandum', [
            'convalidacion' => $conv, 'facultad' => 'Ingeniería', 'carrera' => 'SW',
            'estudiante' => 'PÉREZ, ANA', 'codigo' => 'POST-2026-00001', 'procedencia' => 'UNI',
            'periodo' => '2026-1', 'codigoMemo' => $conv->memorandum_numero,
            'fecha' => '5 de Agosto 2026', 'detalles' => $this->sim->detalles,
            'total' => 3.5, 'resp' => ConvalidacionController::MEMO_DEFAULTS,
            'emitidoPor' => $this->usuario->nombre,
        ])->render();

        $this->assertStringContainsString('3.5', $html, 'El memorándum redondeó los créditos.');
        // Evidencia del sustento y de quién emitió el acto.
        $this->assertStringContainsString('Nota de origen', $html);
        $this->assertStringContainsString('Decana Ruiz', $html);
        $this->assertStringContainsString($conv->memorandum_numero, $html);
    }

    public function test_la_preconvalidacion_no_redondea_ni_fecha_la_impresion(): void
    {
        $html = view('pdf.simulacion', [
            'simulacion' => $this->sim,
            'malla' => MallaCurricular::find($this->sim->malla_usil_id),
            'creditos' => 3.5,
            'convalidados' => $this->sim->detalles,
            'noConvalidables' => collect(),
            'desaprobados' => collect(),
        ])->render();

        $this->assertStringContainsString('3.5', $html, 'La preconvalidación redondeó los créditos.');
        $this->assertStringContainsString($this->sim->updated_at->format('d/m/Y'), $html);
    }

    /** Dos descargas del mismo expediente producen el mismo documento. */
    public function test_la_fecha_de_revision_no_cambia_entre_descargas(): void
    {
        // Sin el query builder, Eloquent pisa updated_at con la hora actual.
        Simulacion::where('id', $this->sim->id)->update(['updated_at' => now()->subDays(3)]);
        $esperada = $this->sim->fresh()->updated_at->format('d/m/Y');

        $this->assertNotSame(now()->format('d/m/Y'), $esperada, 'El escenario no distingue ambas fechas.');

        $render = fn () => view('pdf.simulacion', [
            'simulacion' => $this->sim->fresh(['detalles.cursoUsil.ciclo']),
            'malla' => MallaCurricular::find($this->sim->malla_usil_id),
            'creditos' => 3.5, 'convalidados' => $this->sim->detalles,
            'noConvalidables' => collect(), 'desaprobados' => collect(),
        ])->render();

        $this->assertStringContainsString($esperada, $render());
        $this->assertStringContainsString($esperada, $render());
    }
}
