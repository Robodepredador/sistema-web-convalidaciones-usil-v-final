<?php

namespace App\Http\Controllers;

use App\Exports\HistorialEquivalenciasExport;
use App\Models\Carrera;
use App\Models\EquivalenciaMalla;
use App\Services\AlcanceService;
use App\Services\ConvalidacionEngine;
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
    public function __construct(
        private HistorialEquivalenciasService $historial,
        private ConvalidacionEngine $engine,
    ) {}

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
            // Solo viene si el evaluador eligió el curso de la malla de origen. Sin él
            // no hay con qué buscar el criterio declarado.
            'curso_externo_id' => ['nullable', 'integer'],
        ]);

        // La carrera destino llega por la petición: si el usuario la pide fuera de
        // su alcance, se corta aquí. El filtro de `carrerasVisibles` de más abajo
        // ya acotaría el resultado, pero esto además impide sondear qué existe.
        if (! empty($datos['carrera_usil_id'])) {
            AlcanceService::autorizarCarrera($request->user(), (int) $datos['carrera_usil_id']);
        }

        $resultado = $this->historial->antecedentes(
            $datos['curso'],
            $datos['carrera_usil_id'] ?? null,
            $datos['carrera_externa_id'] ?? null,
            AlcanceService::carrerasVisibles($request->user()),
        );

        // Las dos fuentes se juntan AQUÍ y no dentro del servicio: el histórico sigue
        // siendo puramente derivado de `simulacion_detalle` y el catálogo es criterio
        // declarado. Mantenerlos separados es lo que permite ver que se contradicen;
        // si uno absorbiera al otro, esa señal dejaría de existir.
        $resultado['catalogo'] = $this->catalogoDeclarado($datos, $resultado['antecedentes']);

        return response()->json($resultado);
    }

    /**
     * Lo que el coordinador declaró para este curso en este plan de estudios.
     *
     * Se busca por `curso_externo_id`, que solo existe si el evaluador eligió el curso
     * de la malla de origen. No se cae a buscar por nombre: sería volver al
     * emparejamiento difuso justo donde hay un identificador exacto disponible.
     *
     * @param  array<int,array<string,mixed>>  $antecedentes  ya ordenados por afinidad y frecuencia
     */
    private function catalogoDeclarado(array $datos, array $antecedentes): ?array
    {
        if (empty($datos['curso_externo_id']) || empty($datos['carrera_usil_id'])) {
            return null;
        }

        $mallaUsil = $this->engine->mallaDeCarrera((int) $datos['carrera_usil_id']);
        if (! $mallaUsil) {
            return null;
        }

        $par = EquivalenciaMalla::with('cursoUsil:id,nombre,codigo')
            ->where('curso_externo_id', $datos['curso_externo_id'])
            ->where('malla_usil_id', $mallaUsil->id)
            ->first();

        if (! $par?->cursoUsil) {
            return null;
        }

        // Lo más practicado en esta misma carrera destino. Los antecedentes ya llegan
        // ordenados, así que el primero con `mismo_destino` es el de mayor frecuencia.
        $masPracticado = collect($antecedentes)->firstWhere('mismo_destino', true);

        return [
            'curso_usil_id' => (int) $par->curso_usil_id,
            'curso_usil' => $par->cursoUsil->nombre,
            'codigo_usil' => $par->cursoUsil->codigo,
            // Lo declarado y lo practicado pueden discrepar: es criterio dividido otra
            // vez, ahora entre el coordinador y los expedientes.
            'contradice' => $masPracticado !== null
                && (int) $masPracticado['curso_usil_id'] !== (int) $par->curso_usil_id,
        ];
    }

    /** Pantalla de consulta del histórico. */
    public function index(Request $request)
    {
        $permitidas = AlcanceService::carrerasVisibles($request->user());
        $filtros = $this->filtros($request);

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
                // Solo viene con el toggle activo; sin él la consulta ni hace el join.
                'criterios' => isset($f->criterios) ? (int) $f->criterios : null,
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

    /**
     * Filtros de la pantalla.
     *
     * `solo_divergentes` NO puede salir de `only()`: llega por query string y la vista
     * manda `false`, que viaja como la cadena "false" — truthy en PHP, donde solo `""`
     * y `"0"` son cadenas falsas. Con `only()` el filtro se activaría justo al
     * desmarcar la casilla.
     */
    private function filtros(Request $request): array
    {
        return $request->only(['q', 'institucion_id', 'carrera_usil_id'])
            + ['solo_divergentes' => $request->boolean('solo_divergentes')];
    }

    /** Descarga del histórico consultado (mismos filtros que la pantalla). */
    public function exportar(Request $request)
    {
        $filas = $this->historial
            ->consulta($this->filtros($request), AlcanceService::carrerasVisibles($request->user()))
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
