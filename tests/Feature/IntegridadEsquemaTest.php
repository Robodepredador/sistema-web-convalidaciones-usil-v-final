<?php

namespace Tests\Feature;

use App\Models\Carrera;
use App\Models\CarreraExterna;
use App\Models\CursoExterno;
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
use Database\Seeders\RoleSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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
        $tipos = DB::selectOne(
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

    /** El catálogo SUNEDU se recarga periódicamente: sin unicidad, cada recarga
     *  fallida a medias deja instituciones repetidas que el usuario debe distinguir a ojo. */
    public function test_no_se_repite_una_institucion_externa(): void
    {
        $tipo = TipoInstitucion::create(['nombre' => 'Universidad']);
        $datos = ['tipo_id' => $tipo->id, 'nombre' => 'Universidad Duplicada de Prueba', 'pais' => 'Perú'];
        InstitucionExterna::create($datos);

        try {
            InstitucionExterna::create($datos);
            $this->fail('Debió lanzar QueryException por violar uq_institucion_nombre_pais.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('uq_institucion_nombre_pais', $e->getMessage());
        }
    }

    /** Ni una carrera dentro de la misma institución. */
    public function test_no_se_repite_una_carrera_externa_en_la_misma_institucion(): void
    {
        $institucion = InstitucionExterna::create([
            'tipo_id' => TipoInstitucion::create(['nombre' => 'Instituto'])->id,
            'nombre' => 'Instituto Duplicado de Prueba', 'pais' => 'Perú',
        ]);
        $datos = ['institucion_id' => $institucion->id, 'nombre' => 'Carrera Duplicada de Prueba'];
        CarreraExterna::create($datos);

        try {
            CarreraExterna::create($datos);
            $this->fail('Debió lanzar QueryException por violar uq_carrera_externa_institucion_nombre.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('uq_carrera_externa_institucion_nombre', $e->getMessage());
        }
    }

    /**
     * La simulación se calcula contra "la malla activa de la carrera". Si hay dos,
     * el motor elige la que devuelva primero el optimizador y dos postulantes de
     * la misma carrera pueden convalidarse contra planes distintos.
     */
    public function test_una_carrera_no_puede_tener_dos_mallas_activas(): void
    {
        $this->seed(RoleSeeder::class);
        $usuario = User::create([
            'nombre' => 'Admin', 'email' => uniqid().'@usil.edu.pe',
            'password_hash' => Hash::make('x'),
            'rol_id' => Role::where('nombre', Role::SUPERUSUARIO)->firstOrFail()->id,
            'activo' => true, 'primer_acceso' => false,
        ]);
        $unidad = UnidadNegocio::create(['nombre' => 'Sede Central', 'codigo' => 'SC']);
        $facultad = Facultad::create(['unidad_negocio_id' => $unidad->id, 'nombre' => 'Ingeniería', 'codigo' => 'ING']);
        $carrera = Carrera::create(['facultad_id' => $facultad->id, 'nombre' => 'Civil', 'codigo' => 'CIV']);

        $base = ['carrera_id' => $carrera->id, 'origen_carga' => 'manual', 'usuario_id' => $usuario->id, 'activa' => true];
        MallaCurricular::create($base + ['anio' => 2025, 'version' => 'A']);

        try {
            MallaCurricular::create($base + ['anio' => 2026, 'version' => 'B']);
            $this->fail('Debió lanzar QueryException por violar uq_malla_vigente_por_carrera.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('uq_malla_vigente_por_carrera', $e->getMessage());
        }
    }

    /** Un puente puro se identifica por su par, no por un autonumérico añadido. */
    public function test_las_tablas_puente_de_alcance_no_tienen_id_sustituto(): void
    {
        foreach (['permisos_carrera', 'permisos_facultad'] as $tabla) {
            $this->assertFalse(
                Schema::hasColumn($tabla, 'id'),
                "{$tabla} es un puente puro: su clave es el par, no un id aparte."
            );
        }
    }

    /**
     * Y quitar el id no debilita nada: la clave compuesta sigue impidiendo que
     * un usuario reciba dos veces el mismo alcance. Antes ese trabajo lo hacía
     * un UNIQUE aparte; comprobarlo es lo que distingue haber movido la
     * restricción de haberla perdido.
     */
    public function test_la_clave_compuesta_sigue_impidiendo_alcances_repetidos(): void
    {
        ['user' => $usuario, 'carrera' => $carrera] = $this->crearDependenciasDeSimulacion();
        $alcance = ['usuario_id' => $usuario->id, 'carrera_id' => $carrera->id];

        DB::table('permisos_carrera')->insert($alcance);

        try {
            DB::table('permisos_carrera')->insert($alcance);
            $this->fail('Debió rechazar el alcance repetido por la clave primaria compuesta.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('PRIMARY', $e->getMessage());
        }
    }

    /**
     * La PK compuesta respalda usuario_id por ser su columna líder, pero NO
     * respalda la segunda columna. Si el índice que sostiene esa FK se perdiera
     * al soltar el UNIQUE viejo, InnoDB tendría que recorrer la tabla entera en
     * cada borrado de carrera o facultad, y nada en la suite lo notaría.
     */
    public function test_las_fk_de_los_puentes_conservan_su_indice(): void
    {
        foreach (['permisos_carrera' => 'carrera_id', 'permisos_facultad' => 'facultad_id'] as $tabla => $columna) {
            $indices = DB::select(
                'SELECT DISTINCT INDEX_NAME n FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? AND SEQ_IN_INDEX = 1',
                [$tabla, $columna]
            );

            $this->assertNotEmpty($indices,
                "{$tabla}.{$columna} tiene una FK y se quedó sin índice que la respalde.");
        }
    }

    /**
     * La clave normalizada es un derivado de la palabra clave: el modelo la
     * recalcula siempre, aunque alguien intente pasarla desde fuera.
     *
     * 'Educación Musical' y no 'Educación Física': esta última ya la siembra
     * 2026_08_05_000004_mueve_no_convalidables_del_codigo_a_la_bd como
     * institucional de fábrica y chocaría con uq_no_convalidable_clave_carrera.
     */
    public function test_la_clave_normalizada_no_se_puede_escribir_a_mano(): void
    {
        $regla = CursoNoConvalidable::create([
            'carrera_id' => null,
            'palabra_clave' => 'Educación Musical',
            'clave_normalizada' => 'valor-inventado',
            'motivo' => 'Prueba',
        ]);

        $this->assertSame('educacion musical', $regla->fresh()->clave_normalizada);
    }

    /**
     * El mismo curso externo vale igual venga de la malla 2019 o de la 2023:
     * la versión es procedencia, no identidad. Registrarlo dos veces obligaría
     * al especialista a repetir la evaluación de sílabos por cada versión.
     */
    public function test_un_curso_externo_no_se_repite_en_la_misma_carrera(): void
    {
        $carrera = CarreraExterna::create([
            'institucion_id' => InstitucionExterna::create([
                'tipo_id' => TipoInstitucion::create(['nombre' => 'Instituto'])->id,
                'nombre' => 'Instituto de Prueba', 'pais' => 'Perú',
            ])->id,
            'nombre' => 'Desarrollo de Prueba',
        ]);

        CursoExterno::create(['carrera_externa_id' => $carrera->id, 'nombre' => 'Algoritmia Básica']);

        try {
            // Mismo curso escrito distinto: acentos y mayúsculas no lo hacen otro.
            CursoExterno::create(['carrera_externa_id' => $carrera->id, 'nombre' => 'ALGORITMIA BASICA']);
            $this->fail('Debió rechazar el curso externo repetido en la misma carrera.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('uq_curso_externo_carrera_nombre', $e->getMessage());
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
