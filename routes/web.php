<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordController;
use App\Http\Controllers\CatalogoController;
use App\Http\Controllers\ConfiguracionController;
use App\Http\Controllers\ConvalidacionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EquivalenciaController;
use App\Http\Controllers\Estructura\EstructuraController;
use App\Http\Controllers\Estructura\FacultadController;
use App\Http\Controllers\Estructura\ModalidadController;
use App\Http\Controllers\Estructura\PlanEstudioController;
use App\Http\Controllers\Estructura\ProgramaController;
use App\Http\Controllers\Estructura\SedeController;
use App\Http\Controllers\HistorialEquivalenciasController;
use App\Http\Controllers\InstitucionController;
use App\Http\Controllers\MallaController;
use App\Http\Controllers\MallaExternaController;
use App\Http\Controllers\MallaImportController;
use App\Http\Controllers\MapeoMallasController;
use App\Http\Controllers\Portal\AccesoController as PortalAccesoController;
use App\Http\Controllers\Portal\PasswordController as PortalPasswordController;
use App\Http\Controllers\Portal\PreconvalidacionController as PortalPreconvalidacionController;
use App\Http\Controllers\Portal\SeguimientoController as PortalSeguimientoController;
use App\Http\Controllers\PostulanteController;
use App\Http\Controllers\SimulacionController;
use App\Http\Controllers\UsuarioController;
use Illuminate\Support\Facades\Route;

// --- Invitado ---
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'mostrar'])->name('login');
    // RF-41 protege la cuenta (5 intentos → bloqueo). El throttle protege el
    // servicio: sin él, un mismo origen podía barrer muchas cuentas a la vez.
    Route::post('/login', [LoginController::class, 'login'])->middleware('throttle:10,1');

    // RF-39: recuperación de contraseña ("¿Olvidaste tu contraseña?")
    Route::get('/password/olvide', [PasswordController::class, 'solicitarForm'])->name('password.olvide.form');
    Route::post('/password/olvide', [PasswordController::class, 'enviarEnlace'])->name('password.olvide');
    Route::get('/password/restablecer/{token}', [PasswordController::class, 'restablecerForm'])->name('password.restablecer.form');
    Route::post('/password/restablecer', [PasswordController::class, 'restablecer'])->name('password.restablecer');
});

// --- Autenticado ---
Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // RF-42: cambio de contraseña en primer acceso
    Route::get('/password/cambiar', [PasswordController::class, 'mostrar'])->name('password.cambiar.form');
    Route::post('/password/cambiar', [PasswordController::class, 'actualizar'])->name('password.cambiar');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // --- Administración (Superusuario) — CU-10 ---
    //
    // Cada submódulo exige SU permiso. Antes los tres compartían un único grupo
    // `permission:usuarios.gestionar,configuracion.gestionar,estructura.gestionar`,
    // y como el middleware pasa con UNO cualquiera de los permisos indicados,
    // conceder 'estructura.gestionar' a un rol le habría entregado además la
    // gestión de usuarios y la configuración del sistema.
    Route::middleware('permission:usuarios.gestionar')->group(function () {
        Route::resource('usuarios', UsuarioController::class)
            ->except(['show'])
            ->parameters(['usuarios' => 'usuario']);
        Route::patch('usuarios/{usuario}/estado', [UsuarioController::class, 'estado'])->name('usuarios.estado');
        Route::patch('usuarios/{usuario}/reset-password', [UsuarioController::class, 'resetPassword'])->name('usuarios.reset');
    });

    Route::middleware('permission:configuracion.gestionar')->group(function () {
        // Configuración del sistema (motor de IA, etc.)
        Route::get('configuracion', [ConfiguracionController::class, 'index'])->name('configuracion.index');
        Route::put('configuracion', [ConfiguracionController::class, 'update'])->name('configuracion.update');
        Route::post('configuracion/probar', [ConfiguracionController::class, 'probar'])->name('configuracion.probar');
        Route::post('configuracion/no-convalidables', [ConfiguracionController::class, 'agregarNoConvalidable'])->name('configuracion.no-convalidables.store');
        Route::patch('configuracion/no-convalidables/{noConvalidable}', [ConfiguracionController::class, 'actualizarNoConvalidable'])->name('configuracion.no-convalidables.update');
    });

    Route::middleware('permission:estructura.gestionar')->group(function () {
        // --- Gestión de la Estructura Institucional ---
        Route::prefix('estructura')->name('estructura.')->group(function () {
            Route::get('/', [EstructuraController::class, 'index'])->name('index');

            // Cada submódulo: listar, crear, editar, actualizar, eliminar (lógico) y activar/inactivar.
            $recursos = [
                ['sedes', SedeController::class, 'sede'],
                ['facultades', FacultadController::class, 'facultad'],
                ['programas', ProgramaController::class, 'programa'],
                ['modalidades', ModalidadController::class, 'modalidad'],
                ['planes', PlanEstudioController::class, 'plan'],
            ];
            foreach ($recursos as [$ruta, $ctrl, $param]) {
                Route::get($ruta, [$ctrl, 'index'])->name("{$ruta}.index");
                Route::get("{$ruta}/crear", [$ctrl, 'create'])->name("{$ruta}.create");
                Route::post($ruta, [$ctrl, 'store'])->name("{$ruta}.store");
                Route::get("{$ruta}/{{$param}}/editar", [$ctrl, 'edit'])->name("{$ruta}.edit");
                Route::put("{$ruta}/{{$param}}", [$ctrl, 'update'])->name("{$ruta}.update");
                Route::patch("{$ruta}/{{$param}}/estado", [$ctrl, 'estado'])->name("{$ruta}.estado");
                Route::delete("{$ruta}/{{$param}}", [$ctrl, 'destroy'])->name("{$ruta}.destroy");
            }
        });
    });

    // --- Operación del proceso (gating por permiso de pantalla) — CU-01..08 ---
    Route::group([], function () {
        // Catálogos maestros: Mallas e Instituciones (permiso catalogos.gestionar)
        Route::middleware('permission:catalogos.gestionar')->group(function () {
            // CU-01: Mallas (alta manual)
            Route::get('mallas', [MallaController::class, 'index'])->name('mallas.index');
            Route::get('mallas/crear', [MallaController::class, 'create'])->name('mallas.create');
            Route::post('mallas', [MallaController::class, 'store'])->name('mallas.store');

            // Carga masiva por Excel (RF-08..12)
            Route::get('mallas/importar', [MallaImportController::class, 'create'])->name('mallas.importar.create');
            Route::post('mallas/importar', [MallaImportController::class, 'store'])->name('mallas.importar.store');
            // Importación con revisión previa: leer el Excel, corregir y luego registrar.
            Route::post('mallas/importar/previsualizar', [MallaImportController::class, 'previsualizar'])->name('mallas.importar.previsualizar');
            Route::post('mallas/importar/guardar', [MallaImportController::class, 'guardarRevisada'])->name('mallas.importar.guardar');
            Route::get('mallas/importar/{carga}/estado', [MallaImportController::class, 'estado'])->name('mallas.importar.estado');
            Route::get('mallas/importar/{carga}/progreso', [MallaImportController::class, 'progreso'])->name('mallas.importar.progreso');

            // Plantilla Excel de importación (ruta literal, antes del comodín {malla})
            Route::get('mallas/plantilla', [MallaController::class, 'plantilla'])->name('mallas.plantilla');

            // RF-05: edición de los datos generales de la malla
            Route::get('mallas/{malla}/editar', [MallaController::class, 'edit'])->name('mallas.edit');
            Route::put('mallas/{malla}', [MallaController::class, 'update'])->name('mallas.update');
            Route::delete('mallas/{malla}', [MallaController::class, 'destroy'])->name('mallas.destroy');

            // CU-01 / RF-05..07: mantenimiento del currículo (ciclos y cursos)
            Route::get('mallas/{malla}', [MallaController::class, 'show'])->name('mallas.show');
            Route::post('mallas/{malla}/ciclos', [MallaController::class, 'agregarCiclo'])->name('mallas.ciclos.store');
            Route::delete('mallas/{malla}/ciclos/{ciclo}', [MallaController::class, 'eliminarCiclo'])->name('mallas.ciclos.destroy');
            Route::post('mallas/{malla}/ciclos/{ciclo}/cursos', [MallaController::class, 'agregarCurso'])->name('mallas.cursos.store');
            Route::put('mallas/{malla}/cursos/{curso}', [MallaController::class, 'actualizarCurso'])->name('mallas.cursos.update');
            Route::delete('mallas/{malla}/cursos/{curso}', [MallaController::class, 'eliminarCurso'])->name('mallas.cursos.destroy');
            // Materias de ORIGEN que esta carrera no convalida. Van aquí y no en
            // Configuración porque son de la carrera: el alcance por rol de
            // MallaController es el que decide quién puede tocarlas.
            Route::post('mallas/{malla}/no-convalidables', [MallaController::class, 'agregarNoConvalidable'])->name('mallas.no-convalidables.store');
            Route::patch('mallas/{malla}/no-convalidables/{noConvalidable}', [MallaController::class, 'actualizarNoConvalidable'])->name('mallas.no-convalidables.update');
            Route::delete('mallas/{malla}/no-convalidables/{noConvalidable}', [MallaController::class, 'eliminarNoConvalidable'])->name('mallas.no-convalidables.destroy');

            // RF-08..12 / RF-37: importar y exportar cursos de la malla
            Route::get('mallas/{malla}/exportar', [MallaController::class, 'exportarCursos'])->name('mallas.exportar');
            Route::post('mallas/{malla}/importar-cursos', [MallaController::class, 'importarCursos'])->name('mallas.cursos.importar');

            // CU-02: Instituciones externas
            Route::get('instituciones', [InstitucionController::class, 'index'])->name('instituciones.index');
            Route::get('instituciones/crear', [InstitucionController::class, 'create'])->name('instituciones.create');
            Route::post('instituciones', [InstitucionController::class, 'store'])->name('instituciones.store');
            Route::get('instituciones/{institucion}/editar', [InstitucionController::class, 'edit'])->name('instituciones.edit');
            Route::put('instituciones/{institucion}', [InstitucionController::class, 'update'])->name('instituciones.update');
            Route::patch('instituciones/{institucion}/activar', [InstitucionController::class, 'activar'])->name('instituciones.activar');
            Route::delete('instituciones/{institucion}', [InstitucionController::class, 'destroy'])->name('instituciones.destroy');
        }); // fin catalogos.gestionar

        // CU-03: Mallas Externas (permiso mallas_externas.gestionar).
        //
        // El catálogo de equivalencias curso↔curso está DESACTIVADO: se retiraron
        // sus rutas de escritura (sugerencias.*, simulaciones.sugerir-catalogo),
        // la UI de mapeo, la tabla `equivalencias` (migración 2026_08_02_000003)
        // y los métodos store/destroy de este controlador.
        //
        // Lo que sigue vivo bajo el prefijo `equivalencias` es el registro de las
        // mallas oficiales de las instituciones de origen: el nombre de la ruta
        // quedó del módulo anterior, la pantalla se titula "Mallas Externas".
        Route::middleware('permission:mallas_externas.gestionar')->group(function () {
            Route::get('equivalencias', [EquivalenciaController::class, 'index'])->name('equivalencias.index');
            Route::get('equivalencias/crear', [EquivalenciaController::class, 'create'])->name('equivalencias.create');
            // Carga sin IA: se baja la plantilla, se transcribe la malla oficial y se
            // sube. `previsualizar` devuelve la misma forma que `extraer-ia`, así que
            // la revisión en pantalla y el guardado son los mismos.
            Route::get('mallas-externas/plantilla', [MallaExternaController::class, 'plantilla'])->name('mallas-externas.plantilla');
            Route::post('mallas-externas/previsualizar', [MallaExternaController::class, 'previsualizarExcel'])->name('mallas-externas.previsualizar');
            Route::post('mallas-externas/extraer-ia', [MallaExternaController::class, 'extraerIA'])->name('mallas-externas.extraer-ia');
            Route::post('mallas-externas', [MallaExternaController::class, 'store'])->name('mallas-externas.store');
            // Récord académico externo: cursos de la carrera de origen
            Route::post('carreras-externas/{carreraExterna}/cursos', [CatalogoController::class, 'agregarCursoExterno'])->name('cursos-externos.store');
            Route::put('cursos-externos/{cursoExterno}', [CatalogoController::class, 'actualizarCursoExterno'])->name('cursos-externos.update');
            Route::delete('cursos-externos/{cursoExterno}', [CatalogoController::class, 'eliminarCursoExterno'])->name('cursos-externos.destroy');
        }); // fin mallas_externas.gestionar

        // Postulantes / Solicitudes — lectura (permiso solicitudes.ver)
        Route::middleware('permission:solicitudes.ver')->group(function () {
            Route::get('postulantes', [PostulanteController::class, 'index'])->name('postulantes.index');
            Route::get('postulantes/{postulante}/editar', [PostulanteController::class, 'edit'])->name('postulantes.edit');
            Route::get('postulantes/{postulante}/preconvalidacion', [PostulanteController::class, 'preconvalidacion'])->name('postulantes.preconvalidacion');
            // Admisión consulta el resultado de la preconvalidación de SU postulante.
            Route::get('postulantes/{postulante}/preconvalidacion/{simulacion}/pdf', [PostulanteController::class, 'preconvalidacionPdf'])
                ->name('postulantes.preconvalidacion.pdf')->whereNumber('postulante')->whereNumber('simulacion');
            Route::get('postulantes/{postulante}/preconvalidacion/{simulacion}/excel', [PostulanteController::class, 'preconvalidacionExcel'])
                ->name('postulantes.preconvalidacion.excel')->whereNumber('postulante')->whereNumber('simulacion');
        }); // fin solicitudes.ver (postulantes)

        // Catálogo en cascada (compartido por Postulantes, Equivalencias, Simulaciones)
        Route::middleware('permission:solicitudes.ver,evaluacion.ver')->group(function () {
            Route::get('catalogo/carreras-externas', [CatalogoController::class, 'carrerasExternas'])->name('catalogo.carreras-externas');
        });

        // Postulantes / Solicitudes — escritura (permisos solicitudes.crear / solicitudes.editar)
        // Acciones de registro (solo el Asesor que da de alta): crear, resetear acceso, eliminar.
        Route::middleware('permission:solicitudes.crear')->group(function () {
            Route::get('postulantes/crear', [PostulanteController::class, 'create'])->name('postulantes.create');
            Route::post('postulantes', [PostulanteController::class, 'store'])->name('postulantes.store');
            Route::patch('postulantes/{postulante}/reset-acceso', [PostulanteController::class, 'resetAcceso'])->name('postulantes.reset-acceso');
            Route::delete('postulantes/{postulante}', [PostulanteController::class, 'destroy'])->name('postulantes.destroy');
        });
        Route::middleware('permission:solicitudes.crear,solicitudes.editar,evaluacion.editar')->group(function () {
            Route::post('catalogo/carreras-externas', [CatalogoController::class, 'crearCarreraExterna'])->name('catalogo.carreras-externas.store');
        });
        // Edición de datos del expediente (Asesor dueño y Ejecutivo revisor).
        Route::middleware('permission:solicitudes.editar')->group(function () {
            Route::put('postulantes/{postulante}', [PostulanteController::class, 'update'])->name('postulantes.update');
            Route::patch('postulantes/{postulante}/estado', [PostulanteController::class, 'estado'])->name('postulantes.estado');
        }); // fin solicitudes.editar (postulantes)

        // Revisión de admisión: el Ejecutivo Comercial aprueba u observa.
        Route::middleware('permission:solicitudes.validar')->group(function () {
            Route::post('postulantes/{postulante}/revisar', [PostulanteController::class, 'revisar'])
                ->name('postulantes.revisar');
        });
        // El Asesor reenvía a revisión un expediente observado (tras corregirlo).
        Route::middleware('permission:solicitudes.editar')->group(function () {
            Route::post('postulantes/{postulante}/reenviar-revision', [PostulanteController::class, 'reenviarRevision'])
                ->name('postulantes.reenviar-revision');
        });

        // CU-04 / CU-05: Simulación — lectura y trazabilidad (permiso evaluacion.ver)
        Route::middleware('permission:evaluacion.ver')->group(function () {
            Route::get('simulaciones', [SimulacionController::class, 'index'])->name('simulaciones.index');
            // Base de conocimiento histórica. ANTES de las rutas con {simulacion}:
            // 'simulaciones/{simulacion}/excel' no exige número, así que
            // 'simulaciones/historico/excel' la capturaría si fuera al revés.
            Route::get('simulaciones/historico', [HistorialEquivalenciasController::class, 'index'])->name('simulaciones.historico');
            Route::get('simulaciones/historico/excel', [HistorialEquivalenciasController::class, 'exportar'])->name('simulaciones.historico.excel');
            Route::get('simulaciones/antecedentes', [HistorialEquivalenciasController::class, 'antecedentes'])->name('simulaciones.antecedentes');
            Route::get('simulaciones/{simulacion}', [SimulacionController::class, 'show'])->name('simulaciones.show')->whereNumber('simulacion');
            Route::get('documentos/{documento}/ver', [SimulacionController::class, 'verDocumento'])->name('documentos.ver')->whereNumber('documento');
            Route::get('simulaciones/{simulacion}/pdf', [SimulacionController::class, 'generarPdf'])->name('simulaciones.pdf');
            Route::get('simulaciones/{simulacion}/excel', [SimulacionController::class, 'exportarExcel'])->name('simulaciones.excel');
        }); // fin evaluacion.ver (simulaciones)

        // Mapeo de mallas: el criterio del coordinador, declarado antes de que existan
        // expedientes. Va con `evaluacion.editar` —cuya descripción en el catálogo de
        // permisos es literalmente «Registrar/editar equivalencias y mapeo»— y el
        // alcance por carrera destino se comprueba dentro del controlador.
        Route::middleware('permission:evaluacion.editar')->group(function () {
            Route::get('mapeo-mallas', [MapeoMallasController::class, 'index'])->name('mapeo-mallas.index');
            Route::get('mapeo-mallas/crear', [MapeoMallasController::class, 'crear'])->name('mapeo-mallas.crear');
            Route::get('mapeo-mallas/cursos', [MapeoMallasController::class, 'cursos'])->name('mapeo-mallas.cursos');
            Route::post('mapeo-mallas', [MapeoMallasController::class, 'store'])->name('mapeo-mallas.store');
            Route::delete('mapeo-mallas/{equivalenciaMalla}', [MapeoMallasController::class, 'destroy'])
                ->name('mapeo-mallas.destroy')->whereNumber('equivalenciaMalla');
        });

        // CU-04 / CU-05: Simulación — creación y edición (permisos evaluacion.editar / evaluacion.proponer)
        Route::middleware('permission:evaluacion.editar,evaluacion.proponer')->group(function () {
            Route::get('simulaciones/simular/{postulante}', [SimulacionController::class, 'crear'])->name('simulaciones.crear');
            // Endpoints AJAX del motor de convalidación
            Route::post('simulaciones/sugerir-similitud', [SimulacionController::class, 'sugerirSimilitud'])->name('simulaciones.sugerir-similitud');
            Route::post('simulaciones/sugerir-ia', [SimulacionController::class, 'sugerirIA'])->name('simulaciones.sugerir-ia');
            Route::post('simulaciones/extraer-ia', [SimulacionController::class, 'extraerIA'])->name('simulaciones.extraer-ia');
            Route::post('simulaciones', [SimulacionController::class, 'store'])->name('simulaciones.store');
            Route::get('simulaciones/{simulacion}/editar', [SimulacionController::class, 'editar'])->name('simulaciones.editar');
            Route::put('simulaciones/{simulacion}', [SimulacionController::class, 'update'])->name('simulaciones.update');
            Route::delete('simulaciones/{simulacion}', [SimulacionController::class, 'destroy'])->name('simulaciones.destroy');
            Route::patch('simulaciones/{simulacion}/detalle/{detalle}', [SimulacionController::class, 'toggleDetalle'])->name('simulaciones.detalle.toggle');
        }); // fin evaluacion.editar (simulaciones)

        // CU-06 / RF-46: Convalidación — lectura (permiso convalidacion.ver)
        Route::middleware('permission:convalidacion.ver')->group(function () {
            Route::get('convalidaciones', [ConvalidacionController::class, 'index'])->name('convalidaciones.index');
            // Descarga de la preconvalidación (outputs de la simulación) desde el módulo Convalidaciones.
            Route::get('convalidaciones/preconvalidacion/{simulacion}/pdf', [SimulacionController::class, 'generarPdf'])->name('convalidaciones.preconvalidacion.pdf')->whereNumber('simulacion');
            Route::get('convalidaciones/preconvalidacion/{simulacion}/excel', [SimulacionController::class, 'exportarExcel'])->name('convalidaciones.preconvalidacion.excel')->whereNumber('simulacion');
        }); // fin convalidacion.ver

        // CU-11 / CU-12 / RF-43..45: el asistente de sugerencias por curso está
        // DESACTIVADO junto con el catálogo de equivalencias (era su única UI).
        // La IA del flujo de simulación (sugerir-ia, extraer-ia) sigue activa.
    }); // fin operación del proceso
});

// --- Portal del Postulante (guard 'postulante') ---
Route::prefix('portal')->group(function () {
    Route::middleware('guest:postulante')->group(function () {
        Route::get('/login', [PortalAccesoController::class, 'mostrar'])->name('portal.login');
        // El portal no tiene bloqueo por cuenta (RF-41 es del personal), así que
        // el límite por origen es aquí la única defensa contra la fuerza bruta.
        Route::post('/login', [PortalAccesoController::class, 'login'])->middleware('throttle:10,1');
    });

    Route::middleware('auth:postulante')->group(function () {
        // Cambio de contraseña (sin el gate 'postulante.cambiar' para evitar bucle).
        Route::get('/password/cambiar', [PortalPasswordController::class, 'mostrar'])->name('portal.password.cambiar.form');
        Route::post('/password/cambiar', [PortalPasswordController::class, 'actualizar'])->name('portal.password.cambiar');

        // El seguimiento exige tener la contraseña ya cambiada.
        Route::middleware('postulante.cambiar')->group(function () {
            Route::get('/', [PortalSeguimientoController::class, 'index'])->name('portal.seguimiento');
            Route::get('/preconvalidacion/{simulacion}', [PortalPreconvalidacionController::class, 'ver'])
                ->name('portal.preconvalidacion')->whereNumber('simulacion');
        });

        Route::post('/logout', [PortalAccesoController::class, 'logout'])->name('portal.logout');
    });
});
