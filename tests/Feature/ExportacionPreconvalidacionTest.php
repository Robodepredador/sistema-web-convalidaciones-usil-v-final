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
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

/**
 * La descarga de la preconvalidación en Excel, sobre la plantilla institucional.
 *
 * No existía ninguna prueba sobre esta ruta, y por eso pasó inadvertido que la
 * plantilla `.xltx` de la que depende estaba excluida del control de versiones:
 * la suite seguía verde y cualquier instalación nueva respondía 500. Lo detectó
 * el ensayo de instalación desde un clon limpio; la plantilla ya se versiona.
 *
 * Se comprueban las tres rutas que ofrecen la descarga y, sobre el archivo
 * generado, que la hoja `PRECONVA` lleve de verdad los cursos: es la entrada del
 * `VLOOKUP` con el que la hoja `Export` resuelve los códigos del ERP, así que si
 * sale vacía el archivo es inservible aunque abra sin errores.
 */
class ExportacionPreconvalidacionTest extends TestCase
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
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'email' => 'ana@ext.com',
            'ciclo_postulacion' => '2026-1', 'institucion_origen_id' => $inst->id,
            'carrera_externa_id' => $carExt->id, 'carrera_destino_id' => $carrera->id,
            'estado' => 'en_evaluacion', 'usuario_id' => $user->id, 'revision_estado' => 'aprobada',
        ]);
        $postulante->destinos()->create(['carrera_id' => $carrera->id]);

        $sim = Simulacion::create([
            'postulante_id' => $postulante->id,
            'nombres' => 'Ana', 'apellidos' => 'Pérez', 'tipo_documento' => 'DNI',
            'numero_documento' => '12345678', 'email' => 'ana@ext.com', 'ciclo_postulacion' => '2026-1',
            'carrera_externa_id' => $carExt->id, 'carrera_usil_id' => $carrera->id,
            'malla_usil_id' => $malla->id, 'estado' => 'generada', 'metodo' => 'manual',
            'usuario_id' => $user->id,
        ]);
        SimulacionDetalle::create([
            'simulacion_id' => $sim->id, 'curso_usil_id' => $cursoUsil->id,
            'curso_origen_nombre' => 'Matemática I', 'nota_origen' => '15',
            'clasificacion' => 'convalidable', 'creditos_reconocidos' => 4,
            'excluido' => false, 'origen' => 'manual',
        ]);
        SimulacionDetalle::create([
            'simulacion_id' => $sim->id, 'curso_usil_id' => null,
            'curso_origen_nombre' => 'Inglés I', 'clasificacion' => 'no_convalidable',
            'motivo' => 'Idiomas no se convalidan', 'creditos_reconocidos' => 0,
            'excluido' => false, 'origen' => 'manual',
        ]);

        $this->ctx = compact('user', 'postulante', 'sim');
    }

    /** @return list<array{string}> */
    public static function rutas(): array
    {
        return [
            'desde Simulaciones' => ['/simulaciones/{sim}/excel'],
            'desde Convalidaciones' => ['/convalidaciones/preconvalidacion/{sim}/excel'],
            'desde el expediente del postulante' => ['/postulantes/{postulante}/preconvalidacion/{sim}/excel'],
        ];
    }

    #[DataProvider('rutas')]
    public function test_la_descarga_en_excel_responde_con_un_archivo(string $plantillaRuta): void
    {
        $ruta = str_replace(
            ['{sim}', '{postulante}'],
            [$this->ctx['sim']->id, $this->ctx['postulante']->id],
            $plantillaRuta
        );

        $r = $this->actingAs($this->ctx['user'])->get($ruta);

        $r->assertOk();
        $this->assertStringContainsString('spreadsheetml', $r->headers->get('Content-Type'),
            "La ruta {$ruta} no devolvió un Excel.");

        // Un .xlsx es un ZIP: empieza por «PK». Si la respuesta viniera vacía o
        // fuera una página de error, esto lo delata.
        //
        // Excel::download() devuelve un BinaryFileResponse (un archivo temporal),
        // no un stream, así que se lee del disco.
        $base = $r->baseResponse;
        $contenido = $base instanceof BinaryFileResponse
            ? (string) file_get_contents($base->getFile()->getPathname())
            : $r->streamedContent();

        $this->assertStringStartsWith('PK', $contenido, "El archivo de {$ruta} no es un .xlsx válido.");
        $this->assertGreaterThan(1024, strlen($contenido), 'El archivo salió sospechosamente vacío.');
    }

    /** La hoja PRECONVA sale con los cursos convalidados, y solo con esos. */
    public function test_la_hoja_preconva_lleva_los_cursos_convalidados(): void
    {
        $r = $this->actingAs($this->ctx['user'])
            ->get("/simulaciones/{$this->ctx['sim']->id}/excel")
            ->assertOk();

        $ruta = $r->baseResponse->getFile()->getPathname();
        $libro = IOFactory::createReader('Xlsx')->load($ruta);

        $this->assertContains('PRECONVA', $libro->getSheetNames(), 'Se perdió la hoja de la plantilla.');
        $this->assertContains('Export', $libro->getSheetNames(), 'Se perdió la hoja que resuelve los códigos del ERP.');

        $hoja = $libro->getSheetByName('PRECONVA');
        $this->assertSame('Curso USIL', $hoja->getCell('A1')->getValue(), 'Se pisaron los encabezados.');
        $this->assertSame('Cálculo', $hoja->getCell('A2')->getValue());
        $this->assertSame('Matemática I', $hoja->getCell('B2')->getValue());

        // El curso no convalidable no entra: la hoja alimenta el ERP.
        $this->assertNull($hoja->getCell('A3')->getValue(),
            'Se escribió un curso que no se convalida.');
    }

    /** El alcance manda también aquí: no se descarga el expediente de otra carrera. */
    public function test_la_descarga_respeta_el_alcance(): void
    {
        $rolCoord = Role::create(['nombre' => Role::ADMINISTRATIVO]);
        $ajeno = User::create([
            'nombre' => 'Coord', 'email' => 'coord@usil.edu.pe', 'password_hash' => Hash::make('x'),
            'rol_id' => $rolCoord->id, 'activo' => true, 'primer_acceso' => false,
        ]);   // sin carreras asignadas: su alcance está vacío

        $this->actingAs($ajeno)
            ->get("/simulaciones/{$this->ctx['sim']->id}/excel")
            ->assertForbidden();
    }
}
