<?php

namespace App\Http\Controllers;

use App\Models\CursoExterno;
use App\Models\CursoUsil;
use App\Models\Equivalencia;
use App\Models\Facultad;
use App\Models\InstitucionExterna;
use App\Services\AlcanceService;
use App\Services\AuditoriaService;
use App\Services\ConvalidacionEngine;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MapeoMallasController extends Controller
{
    public function __construct(private ConvalidacionEngine $engine) {}

    public function index(Request $request)
    {
        $permitidas = AlcanceService::carrerasVisibles($request->user());

        $subCursosMalla = \DB::table('cursos_usil')
            ->join('ciclos', 'ciclos.id', '=', 'cursos_usil.ciclo_id')
            ->select('ciclos.malla_id', \DB::raw('COUNT(*) as total_cursos'))
            ->groupBy('ciclos.malla_id');

        $mapeos = Equivalencia::query()
            ->join('carreras_externas as ce', 'ce.id', '=', 'equivalencias.carrera_externa_id')
            ->join('instituciones_externas as ie', 'ie.id', '=', 'ce.institucion_id')
            ->leftJoin('tipos_institucion as ti', 'ti.id', '=', 'ie.tipo_id')
            ->join('cursos_usil as cu', 'cu.id', '=', 'equivalencias.curso_usil_id')
            ->join('ciclos as ci', 'ci.id', '=', 'cu.ciclo_id')
            ->join('mallas_curriculares as mu', 'mu.id', '=', 'ci.malla_id')
            ->join('carreras as c', 'c.id', '=', 'mu.carrera_id')
            ->leftJoin('facultades as f', 'f.id', '=', 'c.facultad_id')
            ->leftJoinSub($subCursosMalla, 'tcm', 'tcm.malla_id', '=', 'mu.id')
            ->when($permitidas !== null, fn ($q) => $q->whereIn('c.id', $permitidas ?: [0]))
            ->selectRaw('ce.id as carrera_externa_id, c.id as carrera_usil_id,
                ie.nombre as institucion, ie.pais as pais_origen, ie.gestion as gestion_origen,
                ti.nombre as tipo_institucion, ce.nombre as carrera_externa,
                f.nombre as facultad_usil, c.nombre as carrera_usil, 
                mu.anio as anio_usil, mu.version as version_usil,
                COALESCE(MAX(tcm.total_cursos), 0) as total_cursos_malla,
                COUNT(DISTINCT cu.id) as cursos_con_equivalencia, 
                COUNT(*) as total_opciones, MAX(equivalencias.updated_at) as ultima')
            ->groupBy('ce.id', 'c.id', 'ie.nombre', 'ie.pais', 'ie.gestion', 'ti.nombre', 'ce.nombre', 'f.nombre', 'c.nombre', 'mu.anio', 'mu.version')
            ->orderByDesc('ultima')
            ->get()
            ->map(fn ($m) => [
                'carrera_externa_id' => (int) $m->carrera_externa_id,
                'carrera_usil_id' => (int) $m->carrera_usil_id,
                'institucion' => $m->institucion,
                'pais_origen' => $m->pais_origen ?? 'Perú',
                'gestion_origen' => $m->gestion_origen,
                'tipo_institucion' => $m->tipo_institucion ?? 'Universidad',
                'carrera_externa' => $m->carrera_externa,
                'facultad_usil' => $m->facultad_usil,
                'carrera_usil' => $m->carrera_usil,
                'plan_usil' => $m->anio_usil.' · '.$m->version_usil,
                'total_cursos_malla' => (int) $m->total_cursos_malla,
                'cursos_con_equivalencia' => (int) $m->cursos_con_equivalencia,
                'total_opciones' => (int) $m->total_opciones,
                'ultima_actualizacion' => $m->ultima ? date('d/m/Y', strtotime($m->ultima)) : null,
            ])
            ->all();

        $stats = [
            'total_mapeos' => count($mapeos),
            'total_equivalencias' => array_sum(array_column($mapeos, 'total_opciones')),
            'instituciones_unicas' => count(array_unique(array_column($mapeos, 'institucion'))),
            'carreras_usil_unicas' => count(array_unique(array_column($mapeos, 'carrera_usil'))),
        ];

        return inertia('MapeoMallas/Index', [
            'mapeos' => $mapeos,
            'stats' => $stats,
        ]);
    }

    public function crear(Request $request)
    {
        $permitidas = AlcanceService::carrerasVisibles($request->user());

        return inertia('MapeoMallas/Crear', [
            'instituciones' => InstitucionExterna::with([
                'carreras:id,institucion_id,nombre',
            ])->orderBy('nombre')->get(['id', 'nombre']),
            'facultades' => Facultad::with([
                'carreras' => fn ($q) => $q
                    ->when($permitidas !== null, fn ($qq) => $qq->whereIn('id', $permitidas ?: [0]))
                    ->where('activo', true)->orderBy('nombre')->select('id', 'facultad_id', 'nombre'),
            ])->orderBy('nombre')->get(['id', 'nombre'])
                ->filter(fn ($f) => $f->carreras->isNotEmpty())->values(),
            'preseleccion' => $request->only(['carrera_externa_id', 'carrera_usil_id']),
        ]);
    }

    public function cursos(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'carrera_externa_id' => ['required', 'integer', 'exists:carreras_externas,id'],
            'carrera_usil_id' => ['required', 'integer', 'exists:carreras,id'],
        ]);

        AlcanceService::autorizarCarrera($request->user(), (int) $datos['carrera_usil_id']);

        $mallaUsil = $this->engine->mallaDeCarrera((int) $datos['carrera_usil_id']);
        abort_if(! $mallaUsil, 422, 'La carrera destino no tiene un plan de estudios (malla) cargado.');

        return response()->json([
            'mallaUsil' => $this->engine->poolCursosUsil((int) $datos['carrera_usil_id']),
            'cursosExternos' => CursoExterno::where('carrera_externa_id', $datos['carrera_externa_id'])
                ->orderBy('nombre')->get(['id', 'nombre', 'creditos']),
            'equivalencias' => Equivalencia::with('cursoExterno:id,nombre,creditos')
                ->where('carrera_externa_id', $datos['carrera_externa_id'])
                ->whereHas('cursoUsil.ciclo', fn ($q) => $q->where('malla_id', $mallaUsil->id))
                ->get(['curso_usil_id', 'curso_externo_id']),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'carrera_usil_id' => ['required', 'integer', 'exists:carreras,id'],
            'curso_usil_id' => ['required', 'integer', 'exists:cursos_usil,id'],
            'carrera_externa_id' => ['required', 'integer', 'exists:carreras_externas,id'],
            'nombre_externo' => ['required', 'string', 'max:255'],
        ]);

        AlcanceService::autorizarCarrera($request->user(), (int) $datos['carrera_usil_id']);

        $mallaUsil = $this->engine->mallaDeCarrera((int) $datos['carrera_usil_id']);
        abort_if(! $mallaUsil, 422, 'La carrera destino no tiene un plan de estudios (malla) cargado.');

        $cursoUsil = CursoUsil::where('id', $datos['curso_usil_id'])
            ->whereHas('ciclo', fn ($q) => $q->where('malla_id', $mallaUsil->id))
            ->first();
        abort_if(! $cursoUsil, 422, 'Ese curso USIL no pertenece al plan de estudios de la carrera destino.');

        $nombreNormalizado = $this->engine->normaliza($datos['nombre_externo']);

        $cursoExterno = CursoExterno::where('carrera_externa_id', $datos['carrera_externa_id'])
            ->where('nombre_normalizado', $nombreNormalizado)
            ->first();

        if (! $cursoExterno) {
            $cursoExterno = CursoExterno::create([
                'carrera_externa_id' => $datos['carrera_externa_id'],
                'nombre' => $datos['nombre_externo'],
            ]);
        }

        try {
            $par = Equivalencia::create([
                'curso_usil_id' => $datos['curso_usil_id'],
                'curso_externo_id' => $cursoExterno->id,
                'carrera_externa_id' => $datos['carrera_externa_id'],
                'registrado_por_id' => $request->user()->id,
            ]);
        } catch (QueryException $e) {
            abort_if($e->getCode() === '23000', 422, 'Esta opción ya está registrada para este curso.');
            throw $e;
        }

        AuditoriaService::registrar('crear', 'equivalencias', null, null, [
            'curso_usil_id' => $par->curso_usil_id,
            'curso_externo_id' => $par->curso_externo_id,
            'carrera_externa_id' => $par->carrera_externa_id,
        ]);

        return response()->json(['status' => 'success', 'curso_externo' => $cursoExterno]);
    }

    public function destroy(Request $request, $cursoUsil, $cursoExterno): JsonResponse
    {
        $curso = CursoUsil::with('ciclo.malla')->findOrFail($cursoUsil);
        AlcanceService::autorizarCarrera($request->user(), $curso->ciclo->malla->carrera_id);

        $eq = Equivalencia::where('curso_usil_id', $cursoUsil)
            ->where('curso_externo_id', $cursoExterno)
            ->firstOrFail();

        $anterior = $eq->toArray();
        $eq->delete();

        AuditoriaService::registrar('eliminar', 'equivalencias', null, $anterior, null);

        return response()->json(['ok' => true]);
    }
}
