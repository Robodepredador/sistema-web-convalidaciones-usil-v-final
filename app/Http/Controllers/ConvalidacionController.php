<?php

namespace App\Http\Controllers;

use App\Models\Simulacion;
use App\Models\SimulacionDetalle;
use App\Services\AlcanceService;
use Illuminate\Http\Request;

/**
 * Módulo de Convalidaciones (Historial de Simulaciones).
 */
class ConvalidacionController extends Controller
{
    public function index(Request $request)
    {
        // Alcance por rol: simulaciones de una carrera visible.
        $visibles = AlcanceService::carrerasVisibles($request->user());

        $q = $request->query('q');
        $desde = $request->query('desde');
        $hasta = $request->query('hasta');

        // --- HISTORIAL DE SIMULACIONES ---
        $simulacionesQuery = Simulacion::with([
            'carreraUsil',
            'detalles' => fn ($query) => $query->where('excluido', false)->whereNotNull('curso_usil_id'),
            'detalles.cursoUsil',
        ])
            ->when($visibles !== null, fn ($query) => $query->whereIn('carrera_usil_id', $visibles ?: [0]))
            ->when($q, fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('nombres', 'like', "%{$q}%")
                    ->orWhere('apellidos', 'like', "%{$q}%")
                    ->orWhere('numero_documento', 'like', "%{$q}%");
            }))
            ->when($desde, fn ($query, $v) => $query->whereDate('created_at', '>=', $v))
            ->when($hasta, fn ($query, $v) => $query->whereDate('created_at', '<=', $v));

        $simulaciones = $simulacionesQuery->orderByDesc('id')
            ->paginate(15, ['*'], 'page')
            ->withQueryString()
            ->through(fn (Simulacion $s) => [
                'id' => $s->id,
                'estudiante' => trim("{$s->nombres} {$s->apellidos}") ?: '—',
                'documento' => $s->numero_documento,
                'carrera' => $s->carreraUsil?->nombre,
                'origen' => $s->universidad_origen,
                'metodo' => $s->metodo,
                'fecha' => optional($s->created_at)->format('d/m/Y H:i'),
                'estado' => $s->estado,
                'convalidados' => $s->detalles->count(),
                'creditos' => (float) $s->detalles->sum('creditos_reconocidos'),
                'pdf_preconv' => route('convalidaciones.preconvalidacion.pdf', $s->id),
                'excel_preconv' => route('convalidaciones.preconvalidacion.excel', $s->id),
                'excel_oficial_preconv' => route('convalidaciones.preconvalidacion.excel-oficial', $s->id),
                'cursos' => $s->detalles->map(fn ($d) => [
                    'origen' => $d->curso_origen_nombre,
                    'usil' => $d->cursoUsil?->nombre,
                    'creditos' => (float) $d->creditos_reconocidos,
                ])->values(),
            ]);

        // --- KPIs ---
        $baseSimQuery = Simulacion::when($visibles !== null, fn ($q) => $q->whereIn('carrera_usil_id', $visibles ?: [0]));

        $totalSimulaciones = (clone $baseSimQuery)->count();

        // Calcular créditos promedio
        $creditosTotales = SimulacionDetalle::whereIn('simulacion_id', (clone $baseSimQuery)->pluck('id'))
            ->where('excluido', false)
            ->whereNotNull('curso_usil_id')
            ->sum('creditos_reconocidos');

        $creditosPromedio = $totalSimulaciones > 0 ? round($creditosTotales / $totalSimulaciones, 1) : 0;

        return inertia('Convalidaciones/Index', [
            'simulaciones' => $simulaciones,
            'filtros' => ['q' => $q, 'desde' => $desde, 'hasta' => $hasta],
            'kpis' => [
                'total_simulaciones' => $totalSimulaciones,
                'creditos_promedio' => $creditosPromedio,
            ],
        ]);
    }
}
