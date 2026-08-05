<?php

namespace App\Http\Controllers;

use App\Exports\HistorialEquivalenciasExport;
use App\Models\Carrera;
use App\Services\AlcanceService;
use App\Services\HistorialEquivalenciasService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Base de conocimiento: qué se convalidó con qué en expedientes anteriores.
 *
 * Es material de consulta, no un motor de decisión. Ninguna acción de aquí
 * modifica una simulación; el emparejamiento sigue siendo manual.
 *
 * Vive fuera de SimulacionController a propósito: aquél ya carga el ciclo de
 * vida completo de la simulación (crear, editar, IA, PDF, Excel) y meterle una
 * tercera responsabilidad lo haría aún más difícil de seguir.
 */
class HistorialEquivalenciasController extends Controller
{
    public function __construct(private HistorialEquivalenciasService $historial) {}

    /**
     * Antecedentes de un curso de origen concreto (panel del espacio de trabajo).
     *
     * Se consulta al seleccionar un curso, así que responde JSON y no Inertia.
     */
    public function antecedentes(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'curso' => ['required', 'string', 'max:200'],
            'carrera_usil_id' => ['nullable', 'integer'],
            'carrera_externa_id' => ['nullable', 'integer'],
        ]);

        // La carrera destino llega por la petición: si el usuario la pide fuera de
        // su alcance, se corta aquí. El filtro de `carrerasVisibles` de más abajo
        // ya acotaría el resultado, pero esto además impide sondear qué existe.
        if (! empty($datos['carrera_usil_id'])) {
            AlcanceService::autorizarCarrera($request->user(), (int) $datos['carrera_usil_id']);
        }

        return response()->json([
            'antecedentes' => $this->historial->antecedentes(
                $datos['curso'],
                $datos['carrera_usil_id'] ?? null,
                $datos['carrera_externa_id'] ?? null,
                AlcanceService::carrerasVisibles($request->user()),
            ),
        ]);
    }

    /** Pantalla de consulta del histórico. */
    public function index(Request $request)
    {
        $permitidas = AlcanceService::carrerasVisibles($request->user());
        $filtros = $request->only(['q', 'institucion_id', 'carrera_usil_id']);

        $filas = $this->historial->consulta($filtros, $permitidas)
            ->paginate(20)
            ->withQueryString()
            ->through(fn ($f) => [
                'origen_nombre' => $f->origen_nombre,
                'institucion' => $f->institucion,
                'carrera_externa' => $f->carrera_externa,
                'curso_usil' => $f->curso_usil,
                'codigo_usil' => $f->codigo_usil,
                'carrera_usil' => $f->carrera_usil,
                'veces' => (int) $f->veces,
                'confirmadas' => (int) $f->confirmadas,
            ]);

        return inertia('Simulaciones/Historico', [
            'filas' => $filas,
            'filtros' => $filtros,
            'carreras' => Carrera::query()
                ->when($permitidas !== null, fn ($q) => $q->whereIn('id', $permitidas ?: [0]))
                ->where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            // Solo instituciones que aparecen en el histórico visible: un desplegable
            // con el padrón SUNEDU entero sería ruido, casi todo sin un solo caso.
            'instituciones' => $this->institucionesConHistorial($permitidas),
        ]);
    }

    /** Descarga del histórico consultado (mismos filtros que la pantalla). */
    public function exportar(Request $request)
    {
        $filas = $this->historial
            ->consulta($request->only(['q', 'institucion_id', 'carrera_usil_id']),
                AlcanceService::carrerasVisibles($request->user()))
            ->get();

        return Excel::download(
            new HistorialEquivalenciasExport($filas),
            'Historico de equivalencias.xlsx',
        );
    }

    /** Instituciones de origen que tienen al menos una equivalencia registrada. */
    private function institucionesConHistorial(?array $permitidas): array
    {
        return DB::table('simulaciones as s')
            ->join('carreras_externas as ce', 'ce.id', '=', 's.carrera_externa_id')
            ->join('instituciones_externas as ie', 'ie.id', '=', 'ce.institucion_id')
            ->whereNull('s.deleted_at')
            ->when($permitidas !== null, fn ($q) => $q->whereIn('s.carrera_usil_id', $permitidas ?: [0]))
            ->distinct()
            ->orderBy('ie.nombre')
            ->get(['ie.id', 'ie.nombre'])
            ->map(fn ($i) => ['id' => $i->id, 'nombre' => $i->nombre])
            ->all();
    }
}
