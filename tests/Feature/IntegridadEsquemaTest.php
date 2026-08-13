<?php

namespace Tests\Feature;

use App\Models\Carrera;
use App\Models\CarreraExterna;
use App\Models\CursoNoConvalidable;
use App\Models\Facultad;
use App\Models\InstitucionExterna;
use App\Models\MallaCurricular;
use App\Models\MallaExterna;
use App\Models\Postulante;
use App\Models\Role;
use App\Models\Simulacion;
use App\Models\TipoInstitucion;
use App\Models\UnidadNegocio;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Restricciones de integridad del esquema (Fase 1 de la normalización).
 *
 * Cada prueba comprueba que la BASE DE DATOS rechaza un dato inválido, no que
 * la aplicación lo valide. Es la diferencia entre una regla y una costumbre:
 * la validación de Laravel se puede saltar con un seeder, un comando artisan o
 * una importación; una restricción de InnoDB no.
 */
class IntegridadEsquemaTest extends TestCase
{
    use RefreshDatabase;

    /** El estado de equivalencias vive en postulante_destinos, no duplicado en el padre. */
    public function test_postulantes_no_conserva_las_columnas_duplicadas_de_destinos(): void
    {
        foreach (['estado_equivalencias', 'equivalencias_revisado_por', 'equivalencias_revisado_en'] as $columna) {
            $this->assertFalse(
                Schema::hasColumn('postulantes', $columna),
                "postulantes.{$columna} duplica a postulante_destinos y debió eliminarse."
            );
        }
    }

    /**
     * La columna simulaciones.tipo_documento admite los cinco tipos de
     * documento que postulantes acepta (DNI, CE, PASAPORTE, PTP, TEMP): solo
     * comprueba la forma del ENUM en information_schema, no que un valor
     * sobreviva a una escritura real. Esa escritura de punta a punta la cubre
     * test_simulacion_conserva_el_tipo_de_documento_temp_o_ptp_del_postulante().
     */
    public function test_simulaciones_acepta_todos_los_tipos_de_documento_del_postulante(): void
    {
        $tipos = \Illuminate\Support\Facades\DB::selectOne(
            "SELECT COLUMN_TYPE t FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'simulaciones' AND COLUMN_NAME = 'tipo_documento'"
        )->t;

        foreach (['DNI', 'CE', 'PASAPORTE', 'PTP', 'TEMP'] as $tipo) {
            $this->assertStringContainsString("'{$tipo}'", $tipos,
                "simulaciones.tipo_documento no admite '{$tipo}', que postulantes sí acepta.");
        }
    }

    /**
     * Un postulante con documento TEMP o PTP conserva ese tipo de documento
     * intacto al generar una simulación real: antes de ampliar el ENUM esta
     * escritura era imposible y el valor terminaba guardado como 'DNI' (ver
     * migración 2026_08_13_000002_unifica_tipo_documento_en_simulaciones).
     */
    public function test_simulacion_conserva_el_tipo_de_documento_temp_o_ptp_del_postulante(): void
    {
        $ctx = $this->crearDependenciasDeSimulacion();

        foreach (['TEMP', 'PTP'] as $tipo) {
            $postulante = Postulante::create([
                'codigo' => "POST-DOC-{$tipo}",
                'tipo_documento' => $tipo,
                'numero_documento' => "DOC-{$tipo}",
                'nombres' => 'Ana',
                'apellido_paterno' => 'Pérez',
                'email' => "postulante.{$tipo}@x.com",
                'institucion_origen_id' => $ctx['inst']->id,
                'carrera_externa_id' => $ctx['carExt']->id,
                'carrera_destino_id' => $ctx['carrera']->id,
                'usuario_id' => $ctx['user']->id,
            ]);

            $simulacion = Simulacion::create([
                'postulante_id' => $postulante->id,
                'nombres' => 'Ana',
                'apellidos' => 'Pérez',
                'tipo_documento' => $tipo,
                'numero_documento' => $postulante->numero_documento,
                'email' => $postulante->email,
                'ciclo_postulacion' => '2026-1',
                'carrera_externa_id' => $ctx['carExt']->id,
                'carrera_usil_id' => $ctx['carrera']->id,
                'malla_usil_id' => $ctx['malla']->id,
                'usuario_id' => $ctx['user']->id,
            ]);

            $recargada = $simulacion->fresh();

            $this->assertSame($tipo, $recargada->tipo_documento,
                "simulaciones.tipo_documento debió persistir '{$tipo}' y no mutar al releerlo de la base de datos.");
            $this->assertSame($postulante->tipo_documento, $recargada->tipo_documento,
                'El tipo de documento de la simulación debe coincidir con el de su postulante.');
        }
    }

    /** El email es la credencial del portal: dos postulantes con el mismo correo
     *  vuelven ambiguo el inicio de sesión. Debe rechazarlo la base, no el formulario. */
    public function test_dos_postulantes_no_pueden_compartir_email(): void
    {
        $base = [
            'tipo_documento' => 'DNI', 'nombres' => 'Ana', 'apellido_paterno' => 'Pérez',
            'codigo' => 'P-0001', 'numero_documento' => '10000001', 'email' => 'repetido@ex.com',
        ];
        Postulante::create($base);

        $this->expectException(QueryException::class);
        Postulante::create(array_merge($base, [
            'codigo' => 'P-0002', 'numero_documento' => '10000002',
        ]));
    }

    /** Pero varios postulantes SIN correo deben seguir siendo válidos:
     *  en un índice único de MySQL cada NULL cuenta como distinto. */
    public function test_varios_postulantes_pueden_no_tener_email(): void
    {
        $base = ['tipo_documento' => 'DNI', 'nombres' => 'Sin', 'apellido_paterno' => 'Correo', 'email' => null];
        Postulante::create($base + ['codigo' => 'P-0003', 'numero_documento' => '10000003']);
        Postulante::create($base + ['codigo' => 'P-0004', 'numero_documento' => '10000004']);

        $this->assertSame(2, Postulante::whereNull('email')->count());
    }

    /** Sin version NOT NULL, el índice único de mallas externas admitía duplicados
     *  exactos: dos NULL nunca chocan entre sí. */
    public function test_no_se_repite_una_malla_externa_de_la_misma_carrera_y_anio(): void
    {
        $carrera = CarreraExterna::create([
            'institucion_id' => InstitucionExterna::create([
                'tipo_id' => TipoInstitucion::create(['nombre' => 'Universidad'])->id,
                'nombre' => 'Instituto de Prueba',
            ])->id,
            'nombre' => 'Ingeniería de Prueba',
        ]);

        MallaExterna::create(['carrera_externa_id' => $carrera->id, 'anio' => 2026]);

        try {
            MallaExterna::create(['carrera_externa_id' => $carrera->id, 'anio' => 2026]);
            $this->fail('Debió lanzar QueryException por violar uq_malla_externa_carrera_anio_version.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('uq_malla_externa_carrera_anio_version', $e->getMessage());
        }
    }

    /** Y la misma regla institucional no puede cargarse dos veces.
     *  Clave fuera de las que siembra 2026_08_05_000004: 'fisica' ya viene
     *  cargada de fábrica y haría chocar la primera inserción, no la segunda. */
    public function test_no_se_repite_una_regla_institucional_no_convalidable(): void
    {
        $regla = ['carrera_id' => null, 'palabra_clave' => 'Zoología', 'clave_normalizada' => 'zoologia', 'motivo' => 'Ciencia básica'];
        CursoNoConvalidable::create($regla);

        try {
            CursoNoConvalidable::create($regla);
            $this->fail('Debió lanzar QueryException por violar uq_no_convalidable_clave_carrera.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('uq_no_convalidable_clave_carrera', $e->getMessage());
        }
    }

    /**
     * Árbol mínimo de dependencias (carrera externa, carrera y malla USIL,
     * usuario) que exigen las FK NOT NULL de simulaciones. Mismo patrón que
     * SimulacionTest::setUp(), recortado a lo que esta prueba necesita.
     */
    private function crearDependenciasDeSimulacion(): array
    {
        $rol = Role::create(['nombre' => Role::ADMIN]);
        $user = User::create([
            'nombre' => 'A', 'email' => 'a@usil.edu.pe', 'password_hash' => Hash::make('x'),
            'rol_id' => $rol->id, 'activo' => true, 'primer_acceso' => false,
        ]);

        $un = UnidadNegocio::create(['nombre' => 'USIL']);
        $fac = Facultad::create(['unidad_negocio_id' => $un->id, 'nombre' => 'Ing', 'codigo' => 'ING']);
        $carrera = Carrera::create(['facultad_id' => $fac->id, 'nombre' => 'SW', 'codigo' => 'SW']);
        $malla = MallaCurricular::create(['carrera_id' => $carrera->id, 'anio' => 2026, 'version' => 'A', 'origen_carga' => 'manual', 'usuario_id' => $user->id]);

        $tipo = TipoInstitucion::create(['nombre' => 'Universidad']);
        $inst = InstitucionExterna::create(['tipo_id' => $tipo->id, 'nombre' => 'UNI']);
        $carExt = CarreraExterna::create(['institucion_id' => $inst->id, 'nombre' => 'Sistemas']);

        return compact('user', 'carrera', 'malla', 'inst', 'carExt');
    }
}
