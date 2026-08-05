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

/**
 * El postulante consulta el PDF de su preconvalidación desde el portal, pero
 * solo el suyo y solo una vez que Admisión confirma la convalidación.
 */
class PortalPreconvalidacionTest extends TestCase
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

        $this->ctx = compact('user', 'carrera', 'malla', 'carExt', 'inst', 'cursoUsil');
    }

    /** Postulante con acceso al portal y la contraseña ya cambiada. */
    private function postulante(string $doc): Postulante
    {
        return Postulante::create([
            'codigo' => 'POST-2026-'.$doc, 'tipo_documento' => 'DNI', 'numero_documento' => $doc,
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'email' => $doc.'@example.com',
            'ciclo_postulacion' => '2026-1', 'institucion_origen_id' => $this->ctx['inst']->id,
            'carrera_externa_id' => $this->ctx['carExt']->id, 'carrera_destino_id' => $this->ctx['carrera']->id,
            'estado' => 'en_evaluacion', 'usuario_id' => $this->ctx['user']->id,
            'revision_estado' => 'aprobada', 'password_hash' => Hash::make('Temp#1234'),
            'acceso_habilitado' => true, 'debe_cambiar_password' => false,
        ]);
    }

    private function simulacion(Postulante $p): Simulacion
    {
        $sim = Simulacion::create([
            'postulante_id' => $p->id,
            'nombres' => $p->nombres, 'apellidos' => $p->apellido_paterno, 'tipo_documento' => 'DNI',
            'numero_documento' => $p->numero_documento, 'email' => $p->email, 'ciclo_postulacion' => '2026-1',
            'carrera_externa_id' => $this->ctx['carExt']->id, 'carrera_usil_id' => $this->ctx['carrera']->id,
            'malla_usil_id' => $this->ctx['malla']->id, 'estado' => 'generada', 'usuario_id' => $this->ctx['user']->id,
        ]);

        SimulacionDetalle::create([
            'simulacion_id' => $sim->id, 'curso_usil_id' => $this->ctx['cursoUsil']->id,
            'curso_origen_nombre' => 'Matemática I', 'clasificacion' => 'convalidable',
            'creditos_reconocidos' => 4, 'excluido' => false, 'origen' => 'manual',
        ]);

        return $sim;
    }

    private function confirmar(Simulacion $sim, string $memo): void
    {
        Convalidacion::create([
            'simulacion_id' => $sim->id, 'fecha_confirmacion' => now()->toDateString(),
            'memorandum_numero' => $memo, 'estado' => Convalidacion::CONFIRMADA,
            'usuario_id' => $this->ctx['user']->id,
        ]);
    }

    public function test_confirmada_devuelve_el_pdf_en_linea(): void
    {
        $p = $this->postulante('90000001');
        $sim = $this->simulacion($p);
        $this->confirmar($sim, 'MEMO-001');

        $r = $this->actingAs($p, 'postulante')->get("/portal/preconvalidacion/{$sim->id}");

        $r->assertOk();
        $r->assertHeader('Content-Type', 'application/pdf');
        // 'inline' es lo que hace que se vea en el navegador en vez de descargarse.
        $this->assertStringStartsWith('inline;', $r->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF', $r->getContent());
    }

    public function test_sin_convalidacion_confirmada_no_expone_el_pdf(): void
    {
        $p = $this->postulante('90000002');
        $sim = $this->simulacion($p); // generada, sin convalidación

        $this->actingAs($p, 'postulante')->get("/portal/preconvalidacion/{$sim->id}")->assertForbidden();
    }

    public function test_anulada_no_expone_el_pdf(): void
    {
        $p = $this->postulante('90000003');
        $sim = $this->simulacion($p);
        Convalidacion::create([
            'simulacion_id' => $sim->id, 'fecha_confirmacion' => now()->toDateString(),
            'memorandum_numero' => 'MEMO-003', 'estado' => Convalidacion::ANULADA,
            'usuario_id' => $this->ctx['user']->id,
        ]);

        $this->actingAs($p, 'postulante')->get("/portal/preconvalidacion/{$sim->id}")->assertForbidden();
    }

    /** Una simulación ajena da 404: el 403 confirmaría que existe. */
    public function test_no_alcanza_la_simulacion_de_otro_postulante(): void
    {
        $duenio = $this->postulante('90000004');
        $sim = $this->simulacion($duenio);
        $this->confirmar($sim, 'MEMO-004');

        $intruso = $this->postulante('90000005');

        $this->actingAs($intruso, 'postulante')->get("/portal/preconvalidacion/{$sim->id}")->assertNotFound();
    }

    public function test_el_seguimiento_solo_publica_url_de_las_confirmadas(): void
    {
        $p = $this->postulante('90000006');
        $sinConfirmar = $this->simulacion($p);
        $confirmada = $this->simulacion($p);
        $this->confirmar($confirmada, 'MEMO-006');

        $this->actingAs($p, 'postulante')->get('/portal/')
            ->assertInertia(fn ($page) => $page
                ->has('simulaciones', 2)
                ->where('simulaciones.0.pdf_url', null)
                ->where('simulaciones.1.pdf_url', route('portal.preconvalidacion', $confirmada->id)));
    }
}
