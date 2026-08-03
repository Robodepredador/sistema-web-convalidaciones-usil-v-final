<?php

namespace App\Http\Controllers;

use App\Exports\ConvalidacionesExport;
use App\Models\Carrera;
use App\Models\Convalidacion;
use App\Models\Facultad;
use App\Models\SimulacionDetalle;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;

/**
 * CU-08: Generar Reportes. RF-36 (estadísticas por facultad/carrera/fechas), RF-37 (exportar Excel).
 */
class ReporteController extends Controller
{
    public function index(Request $request)
    {
        $resumen = $this->consultarResumen($request);

        return inertia('Reportes/Index', [
            'resumen' => $resumen,
            'convalidados' => $this->consultarCursos($request, true),
            'noConvalidados' => $this->consultarCursos($request, false),
            'facultades' => Facultad::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'carreras' => Carrera::where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'facultad_id']),
            'filtros' => $request->only(['facultad_id', 'carrera_id', 'desde', 'hasta']),
        ]);
    }

    /** Cursos convalidados / no convalidados a partir del detalle de simulaciones. */
    private function consultarCursos(Request $request, bool $convalidados): Collection
    {
        $motivo = ['no_convalidable' => 'No convalidable', 'desaprobado' => 'Desaprobado'];

        return SimulacionDetalle::with(['simulacion.carreraUsil.facultad', 'simulacion.carreraExterna', 'cursoUsil'])
            ->when($convalidados,
                fn ($q) => $q->whereNotNull('curso_usil_id')->where('clasificacion', 'convalidable')->where('excluido', false),
                fn ($q) => $q->whereIn('clasificacion', ['no_convalidable', 'desaprobado']))
            ->whereHas('simulacion', function ($s) use ($request) {
                $s->when($request->carrera_id, fn ($q, $v) => $q->where('carrera_usil_id', $v))
                    ->when($request->facultad_id, fn ($q, $v) => $q->whereHas('carreraUsil', fn ($c) => $c->where('facultad_id', $v)))
                    ->when($request->desde, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
                    ->when($request->hasta, fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
            })
            ->orderByDesc('id')
            ->get()
            ->map(fn (SimulacionDetalle $d) => [
                'estudiante' => $d->simulacion ? "{$d->simulacion->nombres} {$d->simulacion->apellidos}" : '—',
                'documento' => $d->simulacion?->numero_documento ?? '—',
                'carrera' => $d->simulacion?->carreraUsil?->nombre ?? '—',
                'origen' => $d->simulacion?->universidad_origen ?? $d->simulacion?->carreraExterna?->nombre,
                'curso_origen' => $d->nombre_origen,
                'curso_usil' => $d->cursoUsil?->nombre,
                'creditos' => $d->creditos_reconocidos,
                'nota' => $d->nota_origen,
                'motivo' => $motivo[$d->clasificacion] ?? null,
            ])->values();
    }

    public function exportar(Request $request)
    {
        $filas = $this->consultarDetalle($request);

        AuditoriaService::registrar('crear', 'convalidaciones', null, null, ['reporte' => 'export', 'filas' => $filas->count()]);

        return Excel::download(new ConvalidacionesExport($filas), 'reporte_convalidaciones.xlsx');
    }

    /** RF-36: agregado de convalidaciones por facultad y carrera. */
    private function consultarResumen(Request $request): Collection
    {
        return $this->baseQuery($request)
            ->get()
            ->groupBy(fn ($c) => $c->simulacion?->carreraUsil?->facultad?->nombre ?? '—')
            ->map(function ($grupo, $facultad) {
                return [
                    'facultad' => $facultad,
                    'total' => $grupo->count(),
                    'confirmadas' => $grupo->where('estado', 'confirmada')->count(),
                    'anuladas' => $grupo->where('estado', 'anulada')->count(),
                ];
            })->values();
    }

    private function consultarDetalle(Request $request): Collection
    {
        return $this->baseQuery($request)->get()->map(fn ($c) => [
            'facultad' => $c->simulacion?->carreraUsil?->facultad?->nombre ?? '—',
            'carrera' => $c->simulacion?->carreraUsil?->nombre ?? '—',
            'estudiante' => $c->simulacion ? "{$c->simulacion->nombres} {$c->simulacion->apellidos}" : '—',
            'documento' => $c->simulacion?->numero_documento ?? '—',
            'memorandum' => $c->memorandum_numero,
            'fecha' => optional($c->fecha_confirmacion)->format('d/m/Y'),
            'estado' => $c->estado,
        ]);
    }

    private function baseQuery(Request $request)
    {
        return Convalidacion::with(['simulacion.carreraUsil.facultad'])
            ->when($request->desde, fn ($q, $v) => $q->whereDate('fecha_confirmacion', '>=', $v))
            ->when($request->hasta, fn ($q, $v) => $q->whereDate('fecha_confirmacion', '<=', $v))
            ->when($request->carrera_id, fn ($q, $v) => $q->whereHas('simulacion', fn ($s) => $s->where('carrera_usil_id', $v)))
            ->when($request->facultad_id, fn ($q, $v) => $q->whereHas('simulacion.carreraUsil', fn ($c) => $c->where('facultad_id', $v)));
    }
}
