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
use App\Models\PostulanteDocumento;
use App\Models\Role;
use App\Models\Simulacion;
use App\Models\SimulacionDetalle;
use App\Models\TipoInstitucion;
use App\Models\UnidadNegocio;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * AUDITORÍA — pruebas end-to-end por rol y sondas de abuso.
 *
 * Escenario: dos facultades (ING / NEG), una carrera en cada una, dos asesores
 * distintos con un postulante propio cada uno, y un coordinador asignado SOLO a
 * la carrera de Ingeniería. Con eso se puede comprobar si el alcance por rol
 * (RF-40) se aplica también sobre el registro individual y no solo al listar.
 */
class AuditoriaE2ETest extends TestCase
{
    use RefreshDatabase;

    private array $c = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);

        $un = UnidadNegocio::create(['nombre' => 'USIL']);
        $facIng = Facultad::create(['unidad_negocio_id' => $un->id, 'nombre' => 'Ingeniería', 'codigo' => 'ING']);
        $facNeg = Facultad::create(['unidad_negocio_id' => $un->id, 'nombre' => 'Negocios', 'codigo' => 'NEG']);

        $carrIng = Carrera::create(['facultad_id' => $facIng->id, 'nombre' => 'Ing. de Software', 'codigo' => 'ISW']);
        $carrNeg = Carrera::create(['facultad_id' => $facNeg->id, 'nombre' => 'Administración', 'codigo' => 'ADM']);

        $admin = $this->usuario(Role::SUPERUSUARIO, 'admin');

        foreach ([[$carrIng, 'ISW'], [$carrNeg, 'ADM']] as [$carr, $cod]) {
            $malla = MallaCurricular::create(['carrera_id' => $carr->id, 'anio' => 2026, 'version' => 'A',
                'origen_carga' => 'manual', 'usuario_id' => $admin->id]);
            $ciclo = Ciclo::create(['malla_id' => $malla->id, 'numero' => 1]);
            $this->c['malla'.$cod] = $malla;
            $this->c['curso'.$cod] = CursoUsil::create(['ciclo_id' => $ciclo->id, 'codigo' => $cod.'1',
                'nombre' => 'Curso '.$cod, 'creditos' => 4]);
        }

        $tipo = TipoInstitucion::create(['nombre' => 'Universidad']);
        $inst = InstitucionExterna::create(['tipo_id' => $tipo->id, 'nombre' => 'UNI']);
        $carExt = CarreraExterna::create(['institucion_id' => $inst->id, 'nombre' => 'Sistemas']);

        // Coordinador con alcance SOLO a la carrera de Ingeniería (RF-40).
        $coord = $this->usuario(Role::COORDINADOR, 'coord');
        $coord->carrerasPermitidas()->attach($carrIng->id);

        // Decano con alcance SOLO a la facultad de Ingeniería.
        $decano = $this->usuario(Role::DECANO, 'decano');
        $decano->facultadesPermitidas()->attach($facIng->id);

        $this->c += [
            'admin' => $admin,
            'asesorA' => $this->usuario(Role::ASESOR, 'asesora'),
            'asesorB' => $this->usuario(Role::ASESOR, 'asesorb'),
            'ejecutivo' => $this->usuario(Role::EJECUTIVO, 'ejecutivo'),
            'coord' => $coord,
            'decano' => $decano,
            'auditor' => $this->usuario(Role::AUDITOR, 'auditor'),
            'consulta' => $this->usuario(Role::CONSULTA, 'consulta'),
            'facIng' => $facIng, 'facNeg' => $facNeg,
            'carrIng' => $carrIng, 'carrNeg' => $carrNeg,
            'inst' => $inst, 'carExt' => $carExt,
        ];
    }

    private function usuario(string $rol, string $slug): User
    {
        return User::create([
            'nombre' => $rol, 'email' => "{$slug}@usil.edu.pe", 'password_hash' => Hash::make('Clave#2026'),
            'rol_id' => Role::where('nombre', $rol)->firstOrFail()->id,
            'activo' => true, 'primer_acceso' => false,
        ]);
    }

    /** Postulante aprobado, con destino en la carrera indicada, registrado por $asesor. */
    private function postulante(User $asesor, Carrera $carrera, string $doc, string $rev = 'aprobada'): Postulante
    {
        $p = Postulante::create([
            'codigo' => 'POST-2026-'.$doc, 'tipo_documento' => 'DNI', 'numero_documento' => $doc,
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'email' => "p{$doc}@ext.com",
            'ciclo_postulacion' => '2026-1', 'institucion_origen_id' => $this->c['inst']->id,
            'carrera_externa_id' => $this->c['carExt']->id, 'carrera_destino_id' => $carrera->id,
            'estado' => 'nuevo', 'usuario_id' => $asesor->id, 'revision_estado' => $rev,
        ]);
        $p->destinos()->create(['carrera_id' => $carrera->id]);

        return $p;
    }

    /** Simulación con una fila convalidable sobre la carrera indicada. */
    private function simulacion(Postulante $p, Carrera $carrera, string $cod): Simulacion
    {
        $sim = Simulacion::create([
            'postulante_id' => $p->id, 'nombres' => $p->nombres, 'apellidos' => $p->apellido_paterno,
            'tipo_documento' => 'DNI', 'numero_documento' => $p->numero_documento, 'email' => $p->email,
            'ciclo_postulacion' => '2026-1', 'carrera_externa_id' => $this->c['carExt']->id,
            'carrera_usil_id' => $carrera->id, 'malla_usil_id' => $this->c['malla'.$cod]->id,
            'estado' => 'generada', 'metodo' => 'manual', 'usuario_id' => $this->c['admin']->id,
        ]);
        SimulacionDetalle::create([
            'simulacion_id' => $sim->id, 'curso_usil_id' => $this->c['curso'.$cod]->id,
            'curso_origen_nombre' => 'Matemática I', 'clasificacion' => 'convalidable',
            'creditos_reconocidos' => 4, 'excluido' => false, 'origen' => 'manual',
        ]);

        return $sim;
    }

    // ==================================================================
    // A. FLUJOS FUNCIONALES END-TO-END POR ROL
    // ==================================================================

    /** E2E completo: Asesor → Ejecutivo → Coordinador → Decano → Postulante. */
    public function test_e2e_flujo_completo_de_convalidacion(): void
    {
        // 1) Asesor registra al postulante (queda 'pendiente' de revisión).
        $this->actingAs($this->c['asesorA'])->post('/postulantes', [
            'nombres' => 'Ana', 'apellido_paterno' => 'Pérez', 'email' => 'ana@ext.com',
            'tipo_documento' => 'DNI', 'numero_documento' => '10000001',
            'carrera_destino_ids' => [$this->c['carrIng']->id], 'ciclo_postulacion' => '2026-1',
            'institucion_origen_id' => $this->c['inst']->id, 'carrera_externa_id' => $this->c['carExt']->id,
        ])->assertRedirect('/postulantes');

        $p = Postulante::where('numero_documento', '10000001')->firstOrFail();
        $this->assertSame('pendiente', $p->revision_estado, 'Debe nacer pendiente de revisión.');

        // 2) Antes de aprobar, el coordinador NO puede simular.
        $this->actingAs($this->c['coord'])->get("/simulaciones/simular/{$p->id}")->assertForbidden();

        // 3) Ejecutivo observa, el asesor reenvía, el ejecutivo aprueba.
        $this->actingAs($this->c['ejecutivo'])
            ->post("/postulantes/{$p->id}/revisar", ['accion' => 'observar', 'observaciones' => 'Falta certificado'])
            ->assertRedirect();
        $this->assertSame('observada', $p->fresh()->revision_estado);

        $this->actingAs($this->c['asesorA'])->post("/postulantes/{$p->id}/reenviar-revision")->assertRedirect();
        $this->actingAs($this->c['ejecutivo'])
            ->post("/postulantes/{$p->id}/revisar", ['accion' => 'aprobar'])->assertRedirect();
        $this->assertSame('aprobada', $p->fresh()->revision_estado);
        $this->assertSame('en_evaluacion', $p->fresh()->estado, 'Aprobar debe avanzar el estado.');

        // 4) Coordinador genera la preconvalidación.
        $this->actingAs($this->c['coord'])->get("/simulaciones/simular/{$p->id}?carrera={$this->c['carrIng']->id}")->assertOk();
        $this->actingAs($this->c['coord'])->postJson('/simulaciones', [
            'postulante_id' => $p->id, 'carrera_usil_id' => $this->c['carrIng']->id, 'metodo' => 'manual',
            'filas' => [[
                'curso_origen_nombre' => 'Matemática I',
                'curso_usil_id' => $this->c['cursoISW']->id, 'clasificacion' => 'convalidable',
            ]],
        ])->assertOk();

        $sim = Simulacion::where('postulante_id', $p->id)->firstOrFail();
        $this->assertSame(4.0, (float) $sim->detalles()->sum('creditos_reconocidos'));

        // 5) Decano confirma → memorándum oficial.
        Storage::fake();
        $this->actingAs($this->c['decano'])->post("/simulaciones/{$sim->id}/confirmar")->assertRedirect('/convalidaciones');
        $conv = Convalidacion::firstOrFail();
        $this->assertSame(Convalidacion::CONFIRMADA, $conv->estado);
        $this->assertNotNull($conv->memorandum_numero);
        $this->actingAs($this->c['decano'])->get("/convalidaciones/{$conv->id}/memorandum")->assertOk();

        // 6) El postulante ve su seguimiento en el portal.
        $p->forceFill(['password_hash' => Hash::make('Portal#2026'), 'acceso_habilitado' => true,
            'debe_cambiar_password' => false])->save();
        $this->post('/portal/login', ['email' => $p->email, 'password' => 'Portal#2026'])
            ->assertRedirect(route('portal.seguimiento'));
        $this->get('/portal')->assertOk();
    }

    /** Auditor y Consulta están activos en Usuarios pero el login los rechaza. */
    public function test_auditor_y_consulta_no_pueden_iniciar_sesion(): void
    {
        foreach (['auditor', 'consulta'] as $slug) {
            $this->post('/login', ['email' => "{$slug}@usil.edu.pe", 'password' => 'Clave#2026'])
                ->assertSessionHasErrors('email');
            $this->assertGuest();
        }
        // ...y sin embargo la BD los marca como cuentas activas.
        $this->assertTrue($this->c['auditor']->activo);
    }

    // ==================================================================
    // B. SONDAS DE ALCANCE (RF-40) SOBRE EL REGISTRO INDIVIDUAL
    // ==================================================================

    /** El coordinador de Ingeniería NO debería leer una simulación de Negocios. */
    public function test_coordinador_lee_simulacion_fuera_de_su_alcance(): void
    {
        $ajena = $this->simulacion($this->postulante($this->c['asesorB'], $this->c['carrNeg'], '20000001'),
            $this->c['carrNeg'], 'ADM');

        // El listado la oculta...
        $this->actingAs($this->c['coord'])->get('/simulaciones')->assertOk();
        // ...y el acceso directo por URL también debe rechazarla.
        $this->actingAs($this->c['coord'])->get("/simulaciones/{$ajena->id}")
            ->assertForbidden('FUGA: el coordinador leyó una simulación fuera de su alcance.');

        // Control positivo: la simulación de SU carrera sí se abre.
        $propia = $this->simulacion($this->postulante($this->c['asesorA'], $this->c['carrIng'], '20000009'),
            $this->c['carrIng'], 'ISW');
        $this->actingAs($this->c['coord'])->get("/simulaciones/{$propia->id}")
            ->assertOk('El alcance no debe bloquear la carrera propia del coordinador.');
    }

    /** Peor: puede EDITAR y ELIMINAR una simulación fuera de su alcance. */
    public function test_coordinador_modifica_simulacion_fuera_de_su_alcance(): void
    {
        $pAjeno = $this->postulante($this->c['asesorB'], $this->c['carrNeg'], '20000002');
        $ajena = $this->simulacion($pAjeno, $this->c['carrNeg'], 'ADM');

        $this->actingAs($this->c['coord'])->putJson("/simulaciones/{$ajena->id}", [
            'postulante_id' => $pAjeno->id, 'carrera_usil_id' => $this->c['carrNeg']->id, 'metodo' => 'manual',
            'filas' => [], 'observaciones' => 'MODIFICADO POR UN ROL SIN ALCANCE',
        ])->assertForbidden('FUGA: el coordinador editó una simulación fuera de su alcance.');

        $this->actingAs($this->c['coord'])->delete("/simulaciones/{$ajena->id}", ['motivo' => 'sondeo de auditoría'])
            ->assertForbidden('FUGA: el coordinador eliminó una simulación fuera de su alcance.');
    }

    /** El coordinador descarga el PDF/Excel de una preconvalidación ajena. */
    public function test_coordinador_descarga_preconvalidacion_ajena(): void
    {
        Storage::fake();
        $ajena = $this->simulacion($this->postulante($this->c['asesorB'], $this->c['carrNeg'], '20000003'),
            $this->c['carrNeg'], 'ADM');

        $this->actingAs($this->c['coord'])->get("/simulaciones/{$ajena->id}/pdf")
            ->assertForbidden('FUGA: descargó el PDF de una preconvalidación fuera de su alcance.');

        // Control positivo: el PDF de su propia carrera sigue descargándose.
        $propia = $this->simulacion($this->postulante($this->c['asesorA'], $this->c['carrIng'], '20000008'),
            $this->c['carrIng'], 'ISW');
        $this->actingAs($this->c['coord'])->get("/simulaciones/{$propia->id}/pdf")
            ->assertOk('El alcance no debe romper la descarga legítima del PDF.');
    }

    /** Descargar el PDF (GET) muta el estado de la simulación: GET no es seguro. */
    public function test_descargar_pdf_muta_el_estado_de_la_simulacion(): void
    {
        Storage::fake();
        $sim = $this->simulacion($this->postulante($this->c['asesorA'], $this->c['carrIng'], '20000004'),
            $this->c['carrIng'], 'ISW');
        $this->assertSame('generada', $sim->estado);

        $this->actingAs($this->c['auditor'])->get("/simulaciones/{$sim->id}/pdf");

        $this->assertSame('generada', $sim->fresh()->estado,
            'Un GET de solo lectura cambió el estado de la simulación a "enviada".');
    }

    /** El récord académico de cualquier postulante se sirve sin comprobar el alcance. */
    public function test_documento_personal_accesible_por_cualquier_evaluador(): void
    {
        Storage::fake();
        $pAjeno = $this->postulante($this->c['asesorB'], $this->c['carrNeg'], '20000005');
        Storage::put('postulantes/x/record.pdf', 'RECORD ACADEMICO CONFIDENCIAL');
        $doc = PostulanteDocumento::create(['postulante_id' => $pAjeno->id, 'tipo' => 'certificado',
            'nombre_original' => 'record.pdf', 'ruta' => 'postulantes/x/record.pdf', 'tamano' => 10]);

        $this->actingAs($this->c['coord'])->get("/documentos/{$doc->id}/ver")
            ->assertForbidden('FUGA: se sirvió el récord académico de un postulante fuera del alcance del usuario.');

        // Control positivo: el récord de un postulante de SU carrera sí se sirve.
        $propio = $this->postulante($this->c['asesorA'], $this->c['carrIng'], '20000007');
        Storage::put('postulantes/y/record.pdf', 'RECORD DE SU CARRERA');
        $suyo = PostulanteDocumento::create(['postulante_id' => $propio->id, 'tipo' => 'certificado',
            'nombre_original' => 'record.pdf', 'ruta' => 'postulantes/y/record.pdf', 'tamano' => 10]);

        $this->actingAs($this->c['coord'])->get("/documentos/{$suyo->id}/ver")
            ->assertOk('El evaluador debe poder abrir el récord de los postulantes de su alcance.');
    }

    // ==================================================================
    // C. SONDAS DE PROPIEDAD ENTRE ASESORES
    // ==================================================================

    /** El asesor A no debería leer el expediente del asesor B. */
    public function test_asesor_lee_expediente_de_otro_asesor(): void
    {
        $ajeno = $this->postulante($this->c['asesorB'], $this->c['carrIng'], '30000001');

        // edit() sí valida propiedad...
        $this->actingAs($this->c['asesorA'])->get("/postulantes/{$ajeno->id}/editar")->assertForbidden();

        // ...pero el endpoint JSON del listado, no.
        $this->actingAs($this->c['asesorA'])->getJson("/postulantes/{$ajeno->id}/preconvalidacion")
            ->assertForbidden('FUGA: el asesor A leyó el expediente del asesor B por el endpoint JSON.');
    }

    /** El asesor A cambia el estado de un postulante del asesor B. */
    public function test_asesor_cambia_estado_de_postulante_ajeno(): void
    {
        $ajeno = $this->postulante($this->c['asesorB'], $this->c['carrIng'], '30000002');

        $this->actingAs($this->c['asesorA'])->patch("/postulantes/{$ajeno->id}/estado", ['estado' => 'rechazado'])
            ->assertForbidden('FUGA: el asesor A cambió el estado de un postulante ajeno.');

        $this->assertSame('nuevo', $ajeno->fresh()->estado);

        // Control positivo: su dueño sí puede cambiarlo.
        $this->actingAs($this->c['asesorB'])->patch("/postulantes/{$ajeno->id}/estado", ['estado' => 'admitido'])
            ->assertRedirect();
        $this->assertSame('admitido', $ajeno->fresh()->estado);
    }

    /** El asesor A descarga la preconvalidación de un postulante del asesor B. */
    public function test_asesor_descarga_preconvalidacion_ajena(): void
    {
        Storage::fake();
        $ajeno = $this->postulante($this->c['asesorB'], $this->c['carrIng'], '30000003');
        $sim = $this->simulacion($ajeno, $this->c['carrIng'], 'ISW');

        $this->actingAs($this->c['asesorA'])->get("/postulantes/{$ajeno->id}/preconvalidacion/{$sim->id}/pdf")
            ->assertForbidden('FUGA: el asesor A descargó el PDF de un postulante ajeno.');
    }

    // ==================================================================
    // D. SONDA: EL BUSCADOR ROMPE EL FILTRO DE ALCANCE (OR sin agrupar)
    // ==================================================================

    /** Buscar por número de memorándum saca a la luz convalidaciones de otra facultad. */
    public function test_buscador_de_convalidaciones_evade_el_alcance(): void
    {
        Storage::fake();
        $ajena = $this->simulacion($this->postulante($this->c['asesorB'], $this->c['carrNeg'], '40000001'),
            $this->c['carrNeg'], 'ADM');
        $this->actingAs($this->c['admin'])->post("/simulaciones/{$ajena->id}/confirmar");
        $memo = Convalidacion::firstOrFail()->memorandum_numero;

        // Sin buscador el decano de Ingeniería no la ve (correcto).
        $sin = $this->actingAs($this->c['decano'])->get('/convalidaciones');
        $this->assertCount(0, $sin->viewData('page')['props']['convalidaciones']['data']);

        // Con el buscador por memorándum, tampoco debe verla.
        $con = $this->actingAs($this->c['decano'])->get('/convalidaciones?q='.urlencode($memo));
        $this->assertCount(0, $con->viewData('page')['props']['convalidaciones']['data'],
            'FUGA: buscar por memorándum saltó el filtro de alcance por facultad.');

        // Control positivo: el buscador sigue encontrando lo que SÍ le corresponde.
        $propia = $this->simulacion($this->postulante($this->c['asesorA'], $this->c['carrIng'], '40000009'),
            $this->c['carrIng'], 'ISW');
        $this->actingAs($this->c['admin'])->post("/simulaciones/{$propia->id}/confirmar");
        $memoPropio = Convalidacion::where('simulacion_id', $propia->id)->firstOrFail()->memorandum_numero;

        $ok = $this->actingAs($this->c['decano'])->get('/convalidaciones?q='.urlencode($memoPropio));
        $this->assertCount(1, $ok->viewData('page')['props']['convalidaciones']['data'],
            'El buscador no debe quedar inutilizado: la convalidación de su facultad sí debe encontrarse.');
    }

    /** Buscar por apellido saca preconvalidaciones de otra facultad. */
    public function test_buscador_de_preconvalidaciones_evade_el_alcance(): void
    {
        $this->simulacion($this->postulante($this->c['asesorB'], $this->c['carrNeg'], '40000002'),
            $this->c['carrNeg'], 'ADM');

        $r = $this->actingAs($this->c['decano'])->get('/convalidaciones?q=Pérez');
        $this->assertCount(0, $r->viewData('page')['props']['preconvalidaciones']['data'],
            'FUGA: buscar por apellido saltó el filtro de alcance por facultad.');
    }

    // ==================================================================
    // E. SONDA: DECANO OPERA FUERA DE SU FACULTAD
    // ==================================================================

    /** El decano de Ingeniería confirma y anula convalidaciones de Negocios. */
    public function test_decano_confirma_convalidacion_de_otra_facultad(): void
    {
        Storage::fake();
        $ajena = $this->simulacion($this->postulante($this->c['asesorB'], $this->c['carrNeg'], '50000001'),
            $this->c['carrNeg'], 'ADM');

        $this->actingAs($this->c['decano'])->post("/simulaciones/{$ajena->id}/confirmar")
            ->assertForbidden('FUGA: el decano emitió un memorándum oficial de otra facultad.');
    }

    // ==================================================================
    // F. SONDA: REPORTES SIN NINGÚN FILTRO DE ALCANCE
    // ==================================================================

    /**
     * Reportes debe filtrar por alcance, no quedarse vacío para todos: se
     * comprueban las dos caras (oculta lo ajeno, muestra lo propio).
     */
    public function test_reportes_ignoran_el_alcance_por_rol(): void
    {
        $this->simulacion($this->postulante($this->c['asesorB'], $this->c['carrNeg'], '80000001'),
            $this->c['carrNeg'], 'ADM');
        $this->simulacion($this->postulante($this->c['asesorA'], $this->c['carrIng'], '80000002'),
            $this->c['carrIng'], 'ISW');

        $props = $this->actingAs($this->c['coord'])->get('/reportes')
            ->assertOk()->viewData('page')['props'];

        $this->assertCount(0, collect($props['convalidados'])->where('carrera', 'Administración'),
            'FUGA: el coordinador de Ingeniería ve en Reportes datos (nombre y documento) de otra facultad.');
        $this->assertCount(1, collect($props['convalidados'])->where('carrera', 'Ing. de Software'),
            'El filtro de alcance no debe vaciar el reporte: la carrera propia sí debe aparecer.');
    }

    // ==================================================================
    // G. SONDAS TÉCNICAS
    // ==================================================================

    /**
     * RF-42 por HTTP: un usuario que NO es Superusuario cambia su contraseña en
     * el primer acceso. Inertia comparte los permisos en cada petición, así que
     * el modelo autenticado ya trae el caché de permisos cuando se guarda.
     */
    public function test_cambio_de_password_en_primer_acceso_por_http(): void
    {
        $u = $this->c['coord'];
        $u->forceFill(['primer_acceso' => true])->save();

        $this->actingAs($u)->post('/password/cambiar', [
            'password' => 'NuevaClave#2026',
            'password_confirmation' => 'NuevaClave#2026',
        ])->assertRedirect(route('dashboard'));

        $this->assertFalse($u->fresh()->primer_acceso);
        $this->assertTrue(Hash::check('NuevaClave#2026', $u->fresh()->password_hash));
    }

    /**
     * El MISMO flujo con el Superusuario sí funciona: `share()` le devuelve ['*']
     * sin tocar `permisosClaves()`. Por eso el defecto anterior pasó inadvertido
     * (todas las pruebas manuales se hicieron con admin.demo).
     */
    public function test_cambio_de_password_del_superusuario_si_funciona(): void
    {
        $u = $this->c['admin'];
        $u->forceFill(['primer_acceso' => true])->save();

        $this->actingAs($u)->post('/password/cambiar', [
            'password' => 'NuevaClave#2026',
            'password_confirmation' => 'NuevaClave#2026',
        ])->assertRedirect(route('dashboard'));

        $this->assertFalse($u->fresh()->primer_acceso);
    }

    /** Guardar un usuario después de consultar sus permisos revienta la consulta SQL. */
    public function test_guardar_usuario_tras_consultar_permisos(): void
    {
        $u = $this->c['coord'];
        $u->puede('evaluacion.ver');          // llena el caché en $attributes
        $u->forceFill(['intentos_fallidos' => 0])->save();

        $this->assertTrue(true, 'Guardar tras consultar permisos no debe lanzar excepción.');
    }

    /** El login no tiene límite de intentos por IP: 30 peticiones y ninguna se frena. */
    public function test_login_del_portal_sin_limite_de_intentos(): void
    {
        $p = $this->postulante($this->c['asesorA'], $this->c['carrIng'], '60000001');
        $p->forceFill(['password_hash' => Hash::make('Portal#2026'), 'acceso_habilitado' => true])->save();

        $bloqueado = false;
        for ($i = 0; $i < 30; $i++) {
            if ($this->post('/portal/login', ['email' => $p->email, 'password' => 'malo'.$i])->status() === 429) {
                $bloqueado = true;
                break;
            }
        }

        $this->assertTrue($bloqueado, 'El portal del postulante admite fuerza bruta sin límite (30 intentos, 0 bloqueos).');
    }

    /**
     * El listado de simulaciones lanzaba una consulta de conteo por cada fila (N+1).
     * Las filas llevan simulaciones reales: con el listado vacío el conteo
     * agrupado nunca se evalúa y la prueba no vería un error en esa consulta.
     */
    public function test_listado_de_simulaciones_sin_n_mas_1(): void
    {
        for ($i = 1; $i <= 6; $i++) {
            $p = $this->postulante($this->c['asesorA'], $this->c['carrIng'], '7000000'.$i);
            $this->simulacion($p, $this->c['carrIng'], 'ISW');
        }

        \DB::enableQueryLog();
        $resp = $this->actingAs($this->c['coord'])->get('/simulaciones')->assertOk();

        // El conteo por fila debe llegar resuelto, no en cero.
        $this->assertSame(1, $resp->viewData('page')['props']['postulantes']['data'][0]['simulaciones_count'],
            'El conteo agrupado de simulaciones no se está resolviendo por fila.');

        $conteos = collect(\DB::getQueryLog())
            ->filter(fn ($q) => str_contains($q['query'], 'from `simulaciones`') && str_contains($q['query'], 'count('))
            ->count();
        \DB::disableQueryLog();

        $this->assertLessThanOrEqual(1, $conteos,
            "N+1: {$conteos} consultas de conteo sobre `simulaciones` para 6 filas del listado.");
    }
}
