<?php

namespace App\Http\Controllers;

use App\Models\Convalidacion;
use App\Models\Postulante;
use App\Models\PostulanteDestino;
use App\Models\Role;
use App\Models\Simulacion;
use App\Models\User;
use App\Services\AlcanceService;
use Illuminate\Http\Request;

/**
 * Panel dinámico según el rol autenticado. Cada perfil ve sus KPIs, su bandeja
 * de pendientes y sus acciones rápidas, siempre dentro de su alcance de datos.
 */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $rol = $user->rol?->nombre;

        // Base de destinos dentro del alcance del usuario.
        $visibles = AlcanceService::carrerasVisibles($user);
        $destinos = PostulanteDestino::query()
            ->when($visibles !== null, fn ($q) => $q->whereIn('carrera_id', $visibles ?: [0]));

        $porEstado = (clone $destinos)->selectRaw('estado_equivalencias, COUNT(*) t')
            ->groupBy('estado_equivalencias')->pluck('t', 'estado_equivalencias');
        $c = fn ($e) => (int) ($porEstado[$e] ?? 0);
        $totalDestinos = (int) $porEstado->sum();

        return inertia('Dashboard', [
            'dashboard' => [
                'rol' => $rol,
                'saludo' => $this->saludo($user),
                'kpis' => $this->kpis($user, $rol, $c, $totalDestinos, $visibles),
                'bandeja' => $this->bandeja($destinos, $rol, $user),
                'acciones' => $this->acciones($user),
            ],
        ]);
    }

    private function saludo(User $user): string
    {
        return "Hola, {$user->nombre}";
    }

    /** KPIs específicos por perfil. */
    private function kpis(User $user, ?string $rol, callable $c, int $total, ?array $visibles): array
    {
        $pendientesAsignar = $c('pendiente');
        $enEvaluacion = $c('asignada') + $c('en_revision') + $c('observada') + $c('devuelta');
        $aprobadas = $c('aprobada');

        $sims = $this->simsScoped($visibles);
        $convs = $this->convsScoped($visibles);

        switch ($rol) {
            case Role::ASESOR:
                $mios = Postulante::where('usuario_id', $user->id);

                return [
                    ['label' => 'Mis solicitudes', 'valor' => (clone $mios)->count(), 'color' => 'blue'],
                    ['label' => 'En revisión', 'valor' => (clone $mios)->where('revision_estado', 'pendiente')->count(), 'color' => 'amber'],
                    ['label' => 'Observadas por corregir', 'valor' => (clone $mios)->where('revision_estado', 'observada')->count(), 'color' => 'orange'],
                    ['label' => 'Aprobadas', 'valor' => (clone $mios)->where('revision_estado', 'aprobada')->count(), 'color' => 'green'],
                ];

            case Role::EJECUTIVO:
                return [
                    ['label' => 'Por revisar', 'valor' => Postulante::where('revision_estado', 'pendiente')->count(), 'color' => 'amber'],
                    ['label' => 'Observadas', 'valor' => Postulante::where('revision_estado', 'observada')->count(), 'color' => 'orange'],
                    ['label' => 'Aprobadas', 'valor' => Postulante::where('revision_estado', 'aprobada')->count(), 'color' => 'green'],
                    ['label' => 'Solicitudes totales', 'valor' => Postulante::count(), 'color' => 'blue'],
                ];

            case Role::ADMINISTRATIVO:
                $asignadasAmi = (clone $this->destinosDe($visibles))->where('asignado_a_id', $user->id)->count();

                return [
                    ['label' => 'Solicitudes asignadas', 'valor' => $asignadasAmi, 'color' => 'blue'],
                    ['label' => 'Evaluaciones pendientes', 'valor' => $enEvaluacion, 'color' => 'amber'],
                    ['label' => 'Aprobadas', 'valor' => $aprobadas, 'color' => 'green'],
                    ['label' => 'Simulaciones generadas', 'valor' => $sims, 'color' => 'violet'],
                ];

            default: // Superusuario (y, por ahora, Especialista: ver informe de la Task A1)
                return [
                    ['label' => 'Usuarios activos', 'valor' => User::where('activo', true)->count(), 'color' => 'blue'],
                    ['label' => 'Solicitudes totales', 'valor' => $total, 'color' => 'indigo'],
                    ['label' => 'En proceso', 'valor' => $enEvaluacion, 'color' => 'amber'],
                    ['label' => 'Aprobadas', 'valor' => $aprobadas, 'color' => 'green'],
                    ['label' => 'Simulaciones', 'valor' => $sims, 'color' => 'violet'],
                    ['label' => 'Convalidaciones', 'valor' => $convs, 'color' => 'teal'],
                ];
        }
    }

    /** Bandeja de pendientes (hasta 6) relevante al rol. */
    private function bandeja($destinos, ?string $rol, User $user): array
    {
        // Admisión: su bandeja se arma sobre postulantes por estado de revisión.
        if ($rol === Role::EJECUTIVO) {
            return $this->bandejaPostulantes(Postulante::where('revision_estado', 'pendiente'));
        }
        if ($rol === Role::ASESOR) {
            return $this->bandejaPostulantes(
                Postulante::where('usuario_id', $user->id)->where('revision_estado', 'observada')
            );
        }

        // Etapas de evaluación: solo expedientes ya aprobados por Admisión.
        return (clone $destinos)
            ->whereHas('postulante', fn ($p) => $p->where('revision_estado', 'aprobada'))
            ->with(['postulante:id,nombres,apellido_paterno,apellido_materno', 'carrera:id,nombre'])
            ->where('estado_equivalencias', '!=', 'aprobada')
            ->orderByDesc('id')->limit(6)->get()
            ->map(fn (PostulanteDestino $d) => [
                'titulo' => $d->postulante
                    ? trim("{$d->postulante->apellido_paterno} {$d->postulante->apellido_materno}, {$d->postulante->nombres}")
                    : '—',
                'subtitulo' => $d->carrera?->nombre,
                'estado' => $d->estado_equivalencias,
                'fecha' => optional($d->created_at)->format('d/m/Y H:i'),
            ])->all();
    }

    /** Bandeja construida sobre postulantes (para los roles de Admisión). */
    private function bandejaPostulantes($query): array
    {
        return $query->with('carreraDestino:id,nombre')->orderByDesc('id')->limit(6)->get()
            ->map(fn (Postulante $p) => [
                'titulo' => $p->nombre_completo,
                'subtitulo' => $p->carreraDestino?->nombre,
                'estado' => $p->revision_estado,
                'fecha' => optional($p->created_at)->format('d/m/Y H:i'),
            ])->all();
    }

    /** Acciones rápidas según permisos. */
    private function acciones(User $user): array
    {
        $posibles = [
            ['label' => 'Solicitudes', 'href' => '/postulantes', 'permiso' => 'solicitudes.ver'],
            ['label' => 'Catálogo de equivalencias', 'href' => '/equivalencias-catalogo', 'permiso' => 'equivalencias.gestionar'],
            ['label' => 'Simulaciones', 'href' => '/simulaciones', 'permiso' => 'evaluacion.ver'],
            ['label' => 'Convalidaciones', 'href' => '/convalidaciones', 'permiso' => 'convalidacion.ver'],
            ['label' => 'Usuarios', 'href' => '/usuarios', 'permiso' => 'usuarios.gestionar'],
        ];

        return array_values(array_filter($posibles, fn ($a) => $user->puede($a['permiso'])));
    }

    private function destinosDe(?array $visibles)
    {
        return PostulanteDestino::query()
            ->when($visibles !== null, fn ($q) => $q->whereIn('carrera_id', $visibles ?: [0]));
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
