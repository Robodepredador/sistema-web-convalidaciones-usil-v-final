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
use App\Models\SimulacionDetalle;
use App\Models\TipoInstitucion;
use App\Models\UnidadNegocio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * El postulante consulta el resultado de su evaluación EN PANTALLA.
 *
 * Antes el portal servía el PDF de la preconvalidación. El área usuaria decidió
 * (2026-08-10) que el postulante vea los cursos en la interfaz y que el
 * documento oficial se entregue fuera del sistema, así que la ruta de descarga
 * del portal desapareció. El personal sí conserva las suyas.
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
        $cursoUsil2 = CursoUsil::create(['ciclo_id' => $ciclo->id, 'codigo' => 'U2', 'nombre' => 'Física', 'creditos' => 3]);

        $tipo = TipoInstitucion::create(['nombre' => 'Universidad']);
        $inst = InstitucionExterna::create(['tipo_id' => $tipo->id, 'nombre' => 'UNI']);
        $carExt = CarreraExterna::create(['institucion_id' => $inst->id, 'nombre' => 'Sistemas']);

        $this->ctx = compact('user', 'carrera', 'malla', 'carExt', 'inst', 'cursoUsil', 'cursoUsil2');
    }

    /** Postulante con acceso al portal y la contraseña ya cambiada. */
    private function postulante(string $doc): Postulante
    {
        $p = Postulante::create([
            'codigo' => 'POST-2026-'.$doc, 'tipo_documento' => 'DNI', 'numero_documento' => $doc,
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'email' => $doc.'@example.com',
            'ciclo_postulacion' => '2026-1', 'institucion_origen_id' => $this->ctx['inst']->id,
            'carrera_externa_id' => $this->ctx['carExt']->id, 'carrera_destino_id' => $this->ctx['carrera']->id,
            'estado' => 'en_evaluacion', 'usuario_id' => $this->ctx['user']->id,
            'revision_estado' => 'aprobada', 'password_hash' => Hash::make('Temp#1234'),
            'acceso_habilitado' => true, 'debe_cambiar_password' => false,
        ]);
        $p->destinos()->create(['carrera_id' => $this->ctx['carrera']->id]);

        return $p;
    }

    /** Simulación con un curso convalidado y otro descartado con motivo. */
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
        SimulacionDetalle::create([
            'simulacion_id' => $sim->id, 'curso_usil_id' => $this->ctx['cursoUsil2']->id,
            'curso_origen_nombre' => 'Inglés I', 'clasificacion' => 'no_convalidable',
            'motivo' => 'Idiomas no se convalidan', 'creditos_reconocidos' => 0,
            'excluido' => false, 'origen' => 'manual',
        ]);

        return $sim;
    }

    /** Lo que sustituye a la descarga: el detalle viaja al portal. */
    public function test_el_seguimiento_muestra_los_cursos_convalidados(): void
    {
        $p = $this->postulante('90000001');
        $this->simulacion($p);

        $this->actingAs($p, 'postulante')->get('/portal/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('simulaciones', 1)
                ->where('simulaciones.0.cursos', 1)
                // Los créditos viajan como número JSON: 4.0 se serializa como 4.
                ->where('simulaciones.0.creditos', 4)
                ->has('simulaciones.0.convalidados', 1)
                ->where('simulaciones.0.convalidados.0.origen', 'Matemática I')
                ->where('simulaciones.0.convalidados.0.usil', 'Cálculo')
                ->where('simulaciones.0.convalidados.0.creditos', 4));
    }

    /**
     * El motivo del descarte llega al postulante. El evaluador está obligado a
     * escribirlo (regla `filas.*.motivo`) justo porque acaba aquí.
     */
    public function test_el_seguimiento_muestra_el_motivo_de_los_no_convalidados(): void
    {
        $p = $this->postulante('90000002');
        $this->simulacion($p);

        $this->actingAs($p, 'postulante')->get('/portal/')
            ->assertInertia(fn ($page) => $page
                ->has('simulaciones.0.no_convalidados', 1)
                ->where('simulaciones.0.no_convalidados.0.origen', 'Inglés I')
                ->where('simulaciones.0.no_convalidados.0.motivo', 'Idiomas no se convalidan'));
    }

    /** El portal no ofrece descarga alguna: ni URL en las props ni ruta que responda. */
    public function test_el_portal_no_expone_ninguna_descarga(): void
    {
        $p = $this->postulante('90000003');
        $sim = $this->simulacion($p);

        $this->actingAs($p, 'postulante')->get('/portal/')
            ->assertInertia(fn ($page) => $page->missing('simulaciones.0.pdf_url'));

        // La ruta que existía ya no está enrutada.
        $this->actingAs($p, 'postulante')
            ->get("/portal/preconvalidacion/{$sim->id}")
            ->assertNotFound();
    }

    /** Cada postulante ve solo lo suyo. */
    public function test_solo_ve_sus_propias_simulaciones(): void
    {
        $duenio = $this->postulante('90000004');
        $this->simulacion($duenio);
        $intruso = $this->postulante('90000005');

        $this->actingAs($intruso, 'postulante')->get('/portal/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('simulaciones', 0));
    }

    /** El personal sí conserva la descarga: es el canal por el que sale el documento. */
    public function test_el_personal_conserva_la_descarga_de_la_preconvalidacion(): void
    {
        $p = $this->postulante('90000006');
        $sim = $this->simulacion($p);

        $r = $this->actingAs($this->ctx['user'])
            ->get("/postulantes/{$p->id}/preconvalidacion/{$sim->id}/pdf");

        $r->assertOk();
        $r->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $r->getContent());
    }
}
