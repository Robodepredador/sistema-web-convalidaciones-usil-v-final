<?php

namespace App\Http\Controllers;

use App\Models\Carrera;
use App\Models\Convalidacion;
use App\Models\Equivalencia;
use App\Models\InstitucionExterna;
use App\Models\MallaCurricular;
use App\Models\Postulante;
use App\Models\Role;
use App\Models\Simulacion;
use App\Models\User;
use App\Services\AlcanceService;
use Illuminate\Http\Request;

/**
 * Panel de Inicio dinámico adaptado y especializado según el rol autenticado.
 * Cada perfil recibe sus KPIs clave, su bandeja de trabajo prioritaria y sus accesos directos de acción.
 */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $rol = $user->rol?->nombre;
        $visibles = AlcanceService::carrerasVisibles($user);

        return inertia('Dashboard', [
            'dashboard' => [
                'rol' => $rol,
                'nombre_usuario' => $user->nombre,
                'saludo' => $this->saludo($user),
                'subtitulo' => $this->subtitulo($rol),
                'kpis' => $this->kpis($user, $rol, $visibles),
                'bandeja' => $this->bandeja($user, $rol, $visibles),
                'acciones' => $this->acciones($user, $rol),
            ],
        ]);
    }

    private function saludo(User $user): string
    {
        $hora = (int) now()->format('H');
        $momento = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');

        return "{$momento}, {$user->nombre}";
    }

    private function subtitulo(?string $rol): string
    {
        return match ($rol) {
            Role::ASESOR => 'Gestión de Postulantes, Registro y Subsanación de Expedientes',
            Role::EJECUTIVO => 'Mesa de Dictamen, Aprobación y Observación de Expedientes',
            Role::ESPECIALISTA => 'Gestión de Mallas Curriculares y Catálogo de Equivalencias',
            Role::ADMINISTRATIVO => 'Simulación y Dictámenes de Convalidación Académica',
            default => 'Panel Ejecutivo y Control General del Sistema de Convalidaciones',
        };
    }

    /** KPIs específicos y de alto valor por perfil. */
    private function kpis(User $user, ?string $rol, ?array $visibles): array
    {
        $sims = $this->simsScoped($visibles);
        $convs = $this->convsScoped($visibles);

        return match ($rol) {
            Role::ASESOR => [
                [
                    'label' => 'Mis Postulantes',
                    'valor' => Postulante::where('usuario_id', $user->id)->count(),
                    'detalle' => 'Total registrados por ti',
                    'color' => 'blue',
                    'icono' => 'users',
                ],
                [
                    'label' => 'En Revisión',
                    'valor' => Postulante::where('usuario_id', $user->id)->where('revision_estado', 'pendiente')->count(),
                    'detalle' => 'Pendientes de dictamen ejecutivo',
                    'color' => 'amber',
                    'icono' => 'clock',
                ],
                [
                    'label' => 'Observadas',
                    'valor' => Postulante::where('usuario_id', $user->id)->where('revision_estado', 'observada')->count(),
                    'detalle' => 'Requieren tu subsanación',
                    'color' => 'rose',
                    'icono' => 'alert',
                    'destacado' => true,
                ],
                [
                    'label' => 'Aprobadas',
                    'valor' => Postulante::where('usuario_id', $user->id)->where('revision_estado', 'aprobada')->count(),
                    'detalle' => 'Enviadas a facultad',
                    'color' => 'emerald',
                    'icono' => 'check',
                ],
            ],

            Role::EJECUTIVO => [
                [
                    'label' => 'Pendientes de Revisión',
                    'valor' => Postulante::where('revision_estado', 'pendiente')->count(),
                    'detalle' => 'Esperando tu dictamen',
                    'color' => 'amber',
                    'icono' => 'clock',
                    'destacado' => true,
                ],
                [
                    'label' => 'Expedientes Observados',
                    'valor' => Postulante::where('revision_estado', 'observada')->count(),
                    'detalle' => 'En subsanación por asesores',
                    'color' => 'rose',
                    'icono' => 'alert',
                ],
                [
                    'label' => 'Expedientes Aprobados',
                    'valor' => Postulante::where('revision_estado', 'aprobada')->count(),
                    'detalle' => 'Listos para simulación en facultad',
                    'color' => 'emerald',
                    'icono' => 'check',
                ],
                [
                    'label' => 'Total de Expedientes',
                    'valor' => Postulante::count(),
                    'detalle' => 'Bandeja histórica de admisión',
                    'color' => 'blue',
                    'icono' => 'folder',
                ],
            ],

            Role::ESPECIALISTA => [
                [
                    'label' => 'Equivalencias Catalogadas',
                    'valor' => Equivalencia::count(),
                    'detalle' => 'Mapeos oficiales activos',
                    'color' => 'indigo',
                    'icono' => 'arrows',
                ],
                [
                    'label' => 'Instituciones Externas',
                    'valor' => InstitucionExterna::where('activa', true)->count(),
                    'detalle' => 'Universidades e institutos',
                    'color' => 'blue',
                    'icono' => 'building',
                ],
                [
                    'label' => 'Mallas Curriculares',
                    'valor' => MallaCurricular::where('activa', true)->count(),
                    'detalle' => 'Planes USIL homologables',
                    'color' => 'emerald',
                    'icono' => 'academic',
                ],
                [
                    'label' => 'Carreras Mapeadas',
                    'valor' => Carrera::whereHas('mallas')->count(),
                    'detalle' => 'Con planes curriculares registrados',
                    'color' => 'violet',
                    'icono' => 'check',
                ],
            ],

            Role::ADMINISTRATIVO => [
                [
                    'label' => 'Listos para Simulación',
                    'valor' => Postulante::where('revision_estado', 'aprobada')
                        ->whereDoesntHave('simulaciones')
                        ->when($visibles !== null, fn ($q) => $q->whereIn('carrera_destino_id', $visibles ?: [0]))
                        ->count(),
                    'detalle' => 'Aprobados por admisión',
                    'color' => 'amber',
                    'icono' => 'clock',
                    'destacado' => true,
                ],
                [
                    'label' => 'Simulaciones Realizadas',
                    'valor' => $sims,
                    'detalle' => 'En tus carreras asignadas',
                    'color' => 'violet',
                    'icono' => 'beaker',
                ],
                [
                    'label' => 'Dictámenes Oficiales',
                    'valor' => $convs,
                    'detalle' => 'Pre-convalidaciones generadas',
                    'color' => 'emerald',
                    'icono' => 'document-check',
                ],
                [
                    'label' => 'Carreras Asignadas',
                    'valor' => $visibles === null ? Carrera::count() : count($visibles),
                    'detalle' => 'Alcance de tu facultad',
                    'color' => 'blue',
                    'icono' => 'academic',
                ],
            ],

            default => [ // Superusuario
                [
                    'label' => 'Postulantes Totales',
                    'valor' => Postulante::count(),
                    'detalle' => 'Expedientes registrados',
                    'color' => 'blue',
                    'icono' => 'users',
                ],
                [
                    'label' => 'En Evaluación',
                    'valor' => Postulante::where('revision_estado', 'aprobada')->whereDoesntHave('simulaciones')->count(),
                    'detalle' => 'Listos para simulación en facultad',
                    'color' => 'amber',
                    'icono' => 'clock',
                ],
                [
                    'label' => 'Simulaciones Generadas',
                    'valor' => $sims,
                    'detalle' => 'Sesiones de homologación',
                    'color' => 'violet',
                    'icono' => 'beaker',
                ],
                [
                    'label' => 'Dictámenes Oficiales',
                    'valor' => $convs,
                    'detalle' => 'Pre-convalidaciones cerradas',
                    'color' => 'emerald',
                    'icono' => 'document-check',
                ],
                [
                    'label' => 'Equivalencias Catalogadas',
                    'valor' => Equivalencia::count(),
                    'detalle' => 'Cursos homologados',
                    'color' => 'indigo',
                    'icono' => 'arrows',
                ],
                [
                    'label' => 'Usuarios Activos',
                    'valor' => User::where('activo', true)->count(),
                    'detalle' => 'Personal con acceso al sistema',
                    'color' => 'teal',
                    'icono' => 'user-group',
                ],
            ],
        };
    }

    /** Bandeja de trabajo altamente contextual para cada rol. */
    private function bandeja(User $user, ?string $rol, ?array $visibles): array
    {
        if ($rol === Role::ASESOR) {
            // Prioridad 1: Observadas que requieren subsanación inmediata
            $observadas = Postulante::where('usuario_id', $user->id)
                ->where('revision_estado', 'observada')
                ->with('carreraDestino:id,nombre')
                ->orderByDesc('updated_at')
                ->limit(6)->get();

            if ($observadas->isNotEmpty()) {
                return [
                    'titulo_seccion' => 'Expedientes Observados que Requieren tu Subsanación',
                    'tipo' => 'observadas_asesor',
                    'items' => $observadas->map(fn (Postulante $p) => [
                        'id' => $p->id,
                        'titulo' => $p->nombre_completo,
                        'subtitulo' => $p->carreraDestino?->nombre ?? 'Sin carrera destino',
                        'observacion' => $p->revision_observaciones,
                        'estado' => 'observada',
                        'fecha' => $p->updated_at?->format('d/m/Y H:i'),
                        'accion_url' => "/postulantes/{$p->id}/editar",
                        'accion_texto' => 'Subsanar Expediente',
                    ])->all(),
                ];
            }

            // Prioridad 2: Últimos postulantes registrados
            $ultimos = Postulante::where('usuario_id', $user->id)
                ->with('carreraDestino:id,nombre')
                ->orderByDesc('id')
                ->limit(6)->get();

            return [
                'titulo_seccion' => 'Tus Expedientes Registrados Recientemente',
                'tipo' => 'recientes_asesor',
                'items' => $ultimos->map(fn (Postulante $p) => [
                    'id' => $p->id,
                    'titulo' => $p->nombre_completo,
                    'subtitulo' => $p->carreraDestino?->nombre ?? 'Sin carrera destino',
                    'documento' => $p->numero_documento,
                    'estado' => $p->revision_estado,
                    'fecha' => $p->created_at?->format('d/m/Y H:i'),
                    'accion_url' => "/postulantes/{$p->id}/editar",
                    'accion_texto' => 'Ver Detalle',
                ])->all(),
            ];
        }

        if ($rol === Role::EJECUTIVO) {
            // Bandeja de dictamen: postulantes pendientes de revisión
            $pendientes = Postulante::where('revision_estado', 'pendiente')
                ->with(['carreraDestino:id,nombre', 'usuario:id,nombre'])
                ->orderBy('created_at')
                ->limit(6)->get();

            return [
                'titulo_seccion' => 'Fila de Expedientes Pendientes de Dictamen',
                'tipo' => 'pendientes_ejecutivo',
                'items' => $pendientes->map(fn (Postulante $p) => [
                    'id' => $p->id,
                    'titulo' => $p->nombre_completo,
                    'subtitulo' => $p->carreraDestino?->nombre ?? 'Sin carrera destino',
                    'asesor' => $p->usuario?->nombre,
                    'documentos_count' => $p->documentos()->count(),
                    'estado' => 'pendiente',
                    'fecha' => $p->created_at?->format('d/m/Y H:i'),
                    'accion_url' => "/postulantes/{$p->id}/editar",
                    'accion_texto' => 'Revisar y Dictaminar',
                ])->all(),
            ];
        }

        if ($rol === Role::ESPECIALISTA) {
            // Bandeja de equivalencias recientes catalogadas
            $recientes = Equivalencia::with(['cursoUsil:id,nombre,codigo', 'cursoExterno.carreraExterna.institucion:id,nombre'])
                ->orderByDesc('created_at')
                ->limit(6)->get();

            return [
                'titulo_seccion' => 'Últimas Equivalencias Registradas en el Catálogo',
                'tipo' => 'catalogo_especialista',
                'items' => $recientes->map(fn (Equivalencia $eq) => [
                    'id' => $eq->curso_usil_id.'-'.$eq->curso_externo_id,
                    'titulo' => "{$eq->cursoExterno?->nombre} → {$eq->cursoUsil?->nombre}",
                    'subtitulo' => $eq->cursoExterno?->carreraExterna?->institucion?->nombre ?? 'Institución externa',
                    'codigo_usil' => $eq->cursoUsil?->codigo,
                    'fecha' => $eq->created_at?->format('d/m/Y H:i'),
                    'accion_url' => '/equivalencias-catalogo',
                    'accion_texto' => 'Ir al Catálogo',
                ])->all(),
            ];
        }

        if ($rol === Role::ADMINISTRATIVO) {
            // Expedientes aprobados por admisión listos para simular (pendientes de simulación)
            $aprobados = Postulante::where('revision_estado', 'aprobada')
                ->whereDoesntHave('simulaciones')
                ->when($visibles !== null, fn ($q) => $q->whereIn('carrera_destino_id', $visibles ?: [0]))
                ->with(['carreraDestino:id,nombre', 'institucionOrigen:id,nombre'])
                ->orderByDesc('revisado_en')
                ->limit(6)->get();

            return [
                'titulo_seccion' => 'Expedientes Aprobados Listos para Simulación en Facultad',
                'tipo' => 'simulables_administrativo',
                'items' => $aprobados->map(fn (Postulante $p) => [
                    'id' => $p->id,
                    'titulo' => $p->nombre_completo,
                    'subtitulo' => $p->carreraDestino?->nombre ?? 'Carrera destino',
                    'origen' => $p->institucionOrigen?->nombre ?? 'Origen externo',
                    'estado' => $p->estado,
                    'fecha' => optional($p->revisado_en ?? $p->updated_at)->format('d/m/Y H:i'),
                    'accion_url' => "/simulaciones/simular/{$p->id}?carrera={$p->carrera_destino_id}",
                    'accion_texto' => 'Iniciar Simulación',
                ])->all(),
            ];
        }

        // Superusuario / Otros: Últimos expedientes activos del sistema
        $global = Postulante::with(['carreraDestino:id,nombre', 'usuario:id,nombre'])
            ->orderByDesc('id')
            ->limit(6)->get();

        return [
            'titulo_seccion' => 'Actividad Reciente de Expedientes en el Sistema',
            'tipo' => 'global_superusuario',
            'items' => $global->map(fn (Postulante $p) => [
                'id' => $p->id,
                'titulo' => $p->nombre_completo,
                'subtitulo' => $p->carreraDestino?->nombre ?? 'Sin carrera destino',
                'asesor' => $p->usuario?->nombre,
                'estado' => $p->revision_estado,
                'fecha' => $p->created_at?->format('d/m/Y H:i'),
                'accion_url' => "/postulantes/{$p->id}/editar",
                'accion_texto' => 'Ver Expediente',
            ])->all(),
        ];
    }

    /** Acciones rápidas enriquecidas con iconos, descripciones y destinos precisos. */
    private function acciones(User $user, ?string $rol): array
    {
        $lista = [];

        if ($rol === Role::ASESOR) {
            $lista[] = [
                'titulo' => 'Registrar Postulante',
                'descripcion' => 'Crear nuevo expediente de traslado externo',
                'href' => '/postulantes/crear',
                'icono' => 'user-plus',
                'color' => 'blue',
            ];
            $lista[] = [
                'titulo' => 'Mis Expedientes',
                'descripcion' => 'Consultar y subsanar postulantes registrados',
                'href' => '/postulantes',
                'icono' => 'folder',
                'color' => 'slate',
            ];
        } elseif ($rol === Role::EJECUTIVO) {
            $lista[] = [
                'titulo' => 'Revisar Pendientes',
                'descripcion' => 'Evaluar expedientes pendientes de dictamen',
                'href' => '/postulantes?revision=pendiente',
                'icono' => 'clipboard-check',
                'color' => 'amber',
            ];
            $lista[] = [
                'titulo' => 'Bandeja de Postulantes',
                'descripcion' => 'Listado general de expedientes de admisión',
                'href' => '/postulantes',
                'icono' => 'users',
                'color' => 'blue',
            ];
        } elseif ($rol === Role::ESPECIALISTA) {
            $lista[] = [
                'titulo' => 'Catálogo de Equivalencias',
                'descripcion' => 'Mapear cursos externos con asignaturas USIL',
                'href' => '/equivalencias-catalogo',
                'icono' => 'arrows',
                'color' => 'indigo',
            ];
            $lista[] = [
                'titulo' => 'Mallas Curriculares',
                'descripcion' => 'Cargar e importar planes de estudio oficiales',
                'href' => '/mallas',
                'icono' => 'academic',
                'color' => 'emerald',
            ];
            $lista[] = [
                'titulo' => 'Instituciones Externas',
                'descripcion' => 'Administrar universidades e institutos de origen',
                'href' => '/instituciones',
                'icono' => 'building',
                'color' => 'blue',
            ];
        } elseif ($rol === Role::ADMINISTRATIVO) {
            $lista[] = [
                'titulo' => 'Módulo de Simulaciones',
                'descripcion' => 'Realizar simulaciones de convalidación',
                'href' => '/simulaciones',
                'icono' => 'beaker',
                'color' => 'violet',
            ];
            $lista[] = [
                'titulo' => 'Pre-Convalidaciones Oficiales',
                'descripcion' => 'Consultar y descargar dictámenes emitidos',
                'href' => '/convalidaciones',
                'icono' => 'document-check',
                'color' => 'emerald',
            ];
        } else { // Superusuario
            $lista[] = [
                'titulo' => 'Bandeja de Postulantes',
                'descripcion' => 'Supervisar todos los expedientes de admisión',
                'href' => '/postulantes',
                'icono' => 'users',
                'color' => 'blue',
            ];
            $lista[] = [
                'titulo' => 'Módulo de Simulaciones',
                'descripcion' => 'Simulación y homologación de cursos',
                'href' => '/simulaciones',
                'icono' => 'beaker',
                'color' => 'violet',
            ];
            $lista[] = [
                'titulo' => 'Pre-Convalidaciones',
                'descripcion' => 'Historial de dictámenes y actas emitidas',
                'href' => '/convalidaciones',
                'icono' => 'document-check',
                'color' => 'emerald',
            ];
            $lista[] = [
                'titulo' => 'Catálogo de Equivalencias',
                'descripcion' => 'Matriz de asignaturas y convalidaciones',
                'href' => '/equivalencias-catalogo',
                'icono' => 'arrows',
                'color' => 'indigo',
            ];
            $lista[] = [
                'titulo' => 'Estructura Institucional',
                'descripcion' => 'Facultades, carreras y planes de estudio',
                'href' => '/estructura',
                'icono' => 'building',
                'color' => 'slate',
            ];
            $lista[] = [
                'titulo' => 'Gestión de Usuarios',
                'descripcion' => 'Cuentas de personal, roles y permisos',
                'href' => '/usuarios',
                'icono' => 'user-group',
                'color' => 'teal',
            ];
        }

        return $lista;
    }

    private function simsScoped(?array $visibles): int
    {
        return Simulacion::query()
            ->when($visibles !== null, fn ($q) => $q->whereIn('carrera_usil_id', $visibles ?: [0]))
            ->count();
    }

    private function convsScoped(?array $visibles): int
    {
        return Convalidacion::query()
            ->when($visibles !== null, fn ($q) => $q->whereHas('simulacion',
                fn ($s) => $s->whereIn('carrera_usil_id', $visibles ?: [0])))
            ->count();
    }
}
