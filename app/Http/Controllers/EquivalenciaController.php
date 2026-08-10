<?php

namespace App\Http\Controllers;

use App\Models\CursoExterno;
use App\Models\InstitucionExterna;
use App\Models\MallaExterna;
use Illuminate\Http\Request;

/**
 * CU-03: Mallas Externas (RF-18..23).
 *
 * Registra y lista las mallas curriculares oficiales de las instituciones de
 * origen. El catálogo de equivalencias curso↔curso quedó descartado por
 * decisión de TI (auditoría 2026-08-02); el mapeo curso externo ↔ curso USIL
 * vive ahora en `simulacion_detalle`, por convalidación.
 */
class EquivalenciaController extends Controller
{
    public function index(Request $request)
    {
        $q = $request->query('q');

        $mallas = MallaExterna::with([
            'carreraExterna:id,institucion_id,nombre',
            'carreraExterna.institucion:id,nombre',
            'cursos:id,malla_externa_id',
        ])
            ->when($q, function ($query) use ($q) {
                $query->whereHas('carreraExterna', function ($qce) use ($q) {
                    $qce->where('nombre', 'like', "%{$q}%")
                        ->orWhereHas('institucion', fn ($qi) => $qi->where('nombre', 'like', "%{$q}%"));
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (MallaExterna $m) => [
                'id' => $m->id,
                'institucion' => $m->carreraExterna?->institucion?->nombre ?? '—',
                'carrera' => $m->carreraExterna?->nombre ?? '—',
                'anio' => $m->anio,
                'version' => $m->version,
                'activa' => $m->activa,
                'pdf_path' => $m->pdf_path ? route('mallas-externas.pdf', $m->id) : null,
                'total_cursos' => $m->cursos->count(),
            ]);

        return inertia('Equivalencias/Index', [
            'mallas' => $mallas,
            'filtros' => ['q' => $q],
            'kpis' => [
                'total_mallas' => MallaExterna::count(),
                'activas' => MallaExterna::where('activa', true)->count(),
                'total_cursos' => CursoExterno::count(),
            ],
        ]);
    }

    public function create(Request $request)
    {
        $malla = null;
        if ($request->malla_id) {
            $malla = MallaExterna::with([
                'carreraExterna:id,nombre,institucion_id',
                'carreraExterna.institucion:id,nombre',
                'cursos',
            ])->findOrFail($request->malla_id);
        }

        return inertia('Equivalencias/Form', [
            'malla' => $malla ? [
                'id' => $malla->id,
                'institucion' => $malla->carreraExterna?->institucion?->nombre,
                'carrera' => $malla->carreraExterna?->nombre,
                'anio' => $malla->anio,
                'version' => $malla->version,
                'pdf_url' => $malla->pdf_path ? route('mallas-externas.pdf', $malla->id) : null,
                'cursos' => $malla->cursos,
            ] : null,
            'instituciones' => InstitucionExterna::with('carreras:id,institucion_id,nombre')->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }
}
