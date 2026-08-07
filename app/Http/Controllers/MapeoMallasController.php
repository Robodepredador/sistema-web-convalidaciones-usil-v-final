<?php

namespace App\Http\Controllers;

use App\Models\CursoExterno;
use App\Models\EquivalenciaMalla;
use App\Models\Facultad;
use App\Models\InstitucionExterna;
use App\Services\AlcanceService;
use App\Services\AuditoriaService;
use App\Services\ConvalidacionEngine;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El coordinador declara qué curso de una malla externa equivale a qué curso de una
 * malla USIL, sin esperar a que pasen expedientes.
 *
 * Es criterio declarado, no historia: la historia sigue viviendo en
 * `simulacion_detalle` y la lee `HistorialEquivalenciasService`. Las dos fuentes se
 * mantienen separadas a propósito — es lo que permite ver que se contradicen.
 *
 * Los identificadores de malla NO llegan desde el cliente: se derivan del curso y de
 * la carrera, para que permiso y alcance se comprueben sobre la carrera y no haya un
 * id de malla suelto que volver a autorizar.
 */
class MapeoMallasController extends Controller
{
    public function __construct(private ConvalidacionEngine $engine) {}

    /** Qué pares de mallas llevo mapeados. Puerta de entrada al asistente. */
    public function index(Request $request)
    {
        $permitidas = AlcanceService::carrerasVisibles($request->user());

        $mapeos = EquivalenciaMalla::query()
            ->join('mallas_externas as me', 'me.id', '=', 'equivalencias_malla.malla_externa_id')
            ->join('carreras_externas as ce', 'ce.id', '=', 'me.carrera_externa_id')
            ->join('instituciones_externas as ie', 'ie.id', '=', 'ce.institucion_id')
            ->join('mallas_curriculares as mu', 'mu.id', '=', 'equivalencias_malla.malla_usil_id')
            ->join('carreras as c', 'c.id', '=', 'mu.carrera_id')
            ->when($permitidas !== null, fn ($q) => $q->whereIn('mu.carrera_id', $permitidas ?: [0]))
            ->selectRaw('me.id as malla_externa_id, c.id as carrera_usil_id,
                ie.nombre as institucion, ce.nombre as carrera_externa,
                me.anio as anio_externa, me.activa as malla_externa_activa,
                c.nombre as carrera_usil, mu.anio as anio_usil, mu.version as version_usil,
                COUNT(*) as equivalencias, MAX(equivalencias_malla.updated_at) as ultima')
            ->groupBy('me.id', 'c.id', 'ie.nombre', 'ce.nombre', 'me.anio', 'me.activa',
                'c.nombre', 'mu.anio', 'mu.version')
            ->orderByDesc('ultima')
            ->get()
            ->map(fn ($m) => [
                'malla_externa_id' => (int) $m->malla_externa_id,
                'carrera_usil_id' => (int) $m->carrera_usil_id,
                'institucion' => $m->institucion,
                'carrera_externa' => $m->carrera_externa,
                'anio_externa' => $m->anio_externa,
                // Una malla externa desactivada significa que se registró una versión
                // nueva: estos pares quedaron atados al plan anterior.
                'malla_externa_vigente' => (bool) $m->malla_externa_activa,
                'carrera_usil' => $m->carrera_usil,
                'plan_usil' => $m->anio_usil.' · '.$m->version_usil,
                'equivalencias' => (int) $m->equivalencias,
            ])
            ->all();

        return inertia('MapeoMallas/Index', ['mapeos' => $mapeos]);
    }

    /** Asistente: origen → destino → malla → mapeo. */
    public function crear(Request $request)
    {
        $permitidas = AlcanceService::carrerasVisibles($request->user());

        return inertia('MapeoMallas/Crear', [
            // Se carga la malla vigente de cada carrera para que el paso 3 sepa si hay
            // que pedir el archivo o basta con reutilizar la ya registrada. Sin esto el
            // asistente exigiría subirla siempre, y al mapear la misma malla externa
            // contra una segunda carrera USIL se registraría una versión nueva que
            // desactivaría la anterior, dejando el primer mapeo apuntando a una malla
            // desactivada.
            'instituciones' => InstitucionExterna::with([
                'carreras:id,institucion_id,nombre',
                'carreras.mallas' => fn ($q) => $q->where('activa', true)
                    ->select('id', 'carrera_externa_id', 'anio', 'version'),
            ])->orderBy('nombre')->get(['id', 'nombre']),
            'facultades' => Facultad::with([
                'carreras' => fn ($q) => $q
                    ->when($permitidas !== null, fn ($qq) => $qq->whereIn('id', $permitidas ?: [0]))
                    ->where('activo', true)->orderBy('nombre')->select('id', 'facultad_id', 'nombre'),
            ])->orderBy('nombre')->get(['id', 'nombre'])
                // Una facultad sin carreras visibles solo sería ruido en el desplegable.
                ->filter(fn ($f) => $f->carreras->isNotEmpty())->values(),
            'preseleccion' => $request->only(['malla_externa_id', 'carrera_usil_id']),
        ]);
    }

    /**
     * Las dos listas del paso de mapeo y los pares ya guardados.
     *
     * Responde JSON: se pide al elegir las mallas, no al cargar la pantalla.
     */
    public function cursos(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'malla_externa_id' => ['required', 'integer', 'exists:mallas_externas,id'],
            'carrera_usil_id' => ['required', 'integer', 'exists:carreras,id'],
        ]);

        AlcanceService::autorizarCarrera($request->user(), (int) $datos['carrera_usil_id']);

        $mallaUsil = $this->engine->mallaDeCarrera((int) $datos['carrera_usil_id']);
        abort_if(! $mallaUsil, 422, 'La carrera destino no tiene un plan de estudios (malla) cargado.');

        return response()->json([
            'cursosExternos' => CursoExterno::where('malla_externa_id', $datos['malla_externa_id'])
                ->orderBy('nombre')->get(['id', 'codigo', 'nombre', 'creditos']),
            // El mismo pool que ve la simulación: se declara solo lo que luego se podrá confirmar.
            'cursosUsil' => $this->engine->poolCursosUsil((int) $datos['carrera_usil_id']),
            'pares' => EquivalenciaMalla::where('malla_externa_id', $datos['malla_externa_id'])
                ->where('malla_usil_id', $mallaUsil->id)
                ->get(['id', 'curso_externo_id', 'curso_usil_id']),
        ]);
    }

    /** Guarda UN par. Par a par y no al final: mapear 40 cursos y perderlos no es opción. */
    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'carrera_usil_id' => ['required', 'integer', 'exists:carreras,id'],
            'curso_externo_id' => ['required', 'integer', 'exists:cursos_externos,id'],
            'curso_usil_id' => ['required', 'integer', 'exists:cursos_usil,id'],
        ]);

        AlcanceService::autorizarCarrera($request->user(), (int) $datos['carrera_usil_id']);

        $mallaUsil = $this->engine->mallaDeCarrera((int) $datos['carrera_usil_id']);
        abort_if(! $mallaUsil, 422, 'La carrera destino no tiene un plan de estudios (malla) cargado.');

        // Solo se declara hacia cursos del plan destino, igual que en la simulación.
        $pool = array_flip(array_column($this->engine->poolCursosUsil((int) $datos['carrera_usil_id']), 'id'));
        abort_if(! isset($pool[$datos['curso_usil_id']]), 422,
            'Ese curso USIL no pertenece al plan de estudios de la carrera destino.');

        $cursoExterno = CursoExterno::findOrFail($datos['curso_externo_id']);

        try {
            $par = EquivalenciaMalla::create([
                'curso_externo_id' => $cursoExterno->id,
                'curso_usil_id' => $datos['curso_usil_id'],
                'malla_externa_id' => $cursoExterno->malla_externa_id,
                'malla_usil_id' => $mallaUsil->id,
                'usuario_id' => $request->user()->id,
            ]);
        } catch (QueryException $e) {
            // 1 a 1: la interfaz ya lo impide, pero dos coordinadores a la vez no.
            abort_if($e->getCode() === '23000', 422,
                'Ya existe una equivalencia para ese curso en este par de mallas. '
                .'La convalidación es 1 a 1: quite la anterior antes de declarar otra.');
            throw $e;
        }

        AuditoriaService::registrar('crear', 'equivalencias_malla', $par->id, null, $par->toArray());

        return response()->json(['id' => $par->id]);
    }

    /** Quita un par para poder reasignarlo. */
    public function destroy(Request $request, EquivalenciaMalla $equivalenciaMalla): JsonResponse
    {
        AlcanceService::autorizarCarrera($request->user(), $equivalenciaMalla->mallaUsil->carrera_id);

        $anterior = $equivalenciaMalla->toArray();
        $equivalenciaMalla->delete();

        // La tabla no tiene borrado lógico: la traza del borrado vive aquí.
        AuditoriaService::registrar('eliminar', 'equivalencias_malla', $anterior['id'], $anterior, null);

        return response()->json(['ok' => true]);
    }
}
