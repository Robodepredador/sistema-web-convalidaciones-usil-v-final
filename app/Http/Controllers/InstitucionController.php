<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInstitucionRequest;
use App\Http\Requests\UpdateInstitucionRequest;
use App\Models\InstitucionExterna;
use App\Models\TipoInstitucion;
use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * CU-02: Gestionar Instituciones Externas (RF-18, RF-23).
 */
class InstitucionController extends Controller
{
    public function index(Request $request)
    {
        // RF-18: filtros por tipo, gestión, país y estado. Búsqueda por nombre.
        $instituciones = InstitucionExterna::with('tipo')
            ->withCount('carreras')
            ->when($request->buscar, fn ($q, $v) => $q->where('nombre', 'like', '%'.$v.'%'))
            ->when($request->tipo_id, fn ($q, $v) => $q->where('tipo_id', $v))
            ->when($request->gestion, fn ($q, $v) => $q->where('gestion', $v))
            ->when($request->licenciamiento, fn ($q, $v) => $q->where('licenciamiento', $v))
            ->when($request->pais, fn ($q, $v) => $q->where('pais', $v))
            ->when($request->estado === 'activa', fn ($q) => $q->where('activa', true))
            ->when($request->estado === 'inactiva', fn ($q) => $q->where('activa', false))
            ->orderBy('nombre')
            ->paginate(10)->withQueryString()
            ->through(fn (InstitucionExterna $i) => [
                'id' => $i->id,
                'nombre' => $i->nombre,
                'tipo' => $i->tipo?->nombre,
                'pais' => $i->pais,
                'gestion' => $i->gestion,
                'licenciamiento' => $i->licenciamiento,
                'activa' => $i->activa,
                'carreras_count' => $i->carreras_count,
            ]);

        return inertia('Instituciones/Index', [
            'instituciones' => $instituciones,
            'institucionesActivas' => InstitucionExterna::where('activa', true)->count(),
            'tipos' => TipoInstitucion::orderBy('nombre')->get(['id', 'nombre']),
            'paises' => InstitucionExterna::whereNotNull('pais')->distinct()->orderBy('pais')->pluck('pais'),
            'filtros' => $request->only(['buscar', 'tipo_id', 'gestion', 'licenciamiento', 'pais', 'estado']),
        ]);
    }

    public function create()
    {
        return inertia('Instituciones/Form', [
            'tipos' => TipoInstitucion::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(StoreInstitucionRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        $institucion = DB::transaction(function () use ($datos) {
            $inst = InstitucionExterna::create([
                'tipo_id' => $datos['tipo_id'],
                'nombre' => $datos['nombre'],
                'pais' => $datos['pais'] ?? null,
                'gestion' => $datos['gestion'] ?? null,
                'licenciamiento' => $datos['licenciamiento'] ?? 'desconocido',
                'licenciamiento_resolucion' => $datos['licenciamiento_resolucion'] ?? null,
                'activa' => $datos['activa'] ?? true,
            ]);

            foreach ($datos['carreras'] ?? [] as $carrera) {
                $inst->carreras()->create(['nombre' => $carrera['nombre']]);
            }

            return $inst;
        });

        AuditoriaService::registrar('crear', 'instituciones_externas', $institucion->id, null, [
            'nombre' => $institucion->nombre,
        ]);

        return redirect()->route('instituciones.index')->with('status', 'Institución registrada.');
    }

    public function edit(InstitucionExterna $institucion)
    {
        $institucion->load(['carreras' => fn ($q) => $q->withCount('cursos')->orderBy('nombre')]);

        return inertia('Instituciones/Editar', [
            'institucion' => [
                'id' => $institucion->id,
                'tipo_id' => $institucion->tipo_id,
                'nombre' => $institucion->nombre,
                'pais' => $institucion->pais,
                'gestion' => $institucion->gestion,
                'licenciamiento' => $institucion->licenciamiento,
                'licenciamiento_resolucion' => $institucion->licenciamiento_resolucion,
                'activa' => $institucion->activa,
                'carreras' => $institucion->carreras->map(fn ($c) => [
                    'id' => $c->id,
                    'nombre' => $c->nombre,
                    'cursos_count' => $c->cursos_count,
                ]),
            ],
            'tipos' => TipoInstitucion::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function update(UpdateInstitucionRequest $request, InstitucionExterna $institucion): RedirectResponse
    {
        $datos = $request->validated();
        $antes = $institucion->only(['tipo_id', 'nombre', 'pais', 'gestion', 'licenciamiento', 'activa']);

        $enviadas = collect($datos['carreras'] ?? []);
        $idsConserva = $enviadas->pluck('id')->filter()->all();

        // RF-23: no se pueden eliminar carreras que ya tienen cursos registrados (integridad referencial).
        $bloqueadas = $institucion->carreras()
            ->whereNotIn('id', $idsConserva)
            ->whereHas('cursos')
            ->pluck('nombre');

        if ($bloqueadas->isNotEmpty()) {
            return back()->withErrors([
                'carreras' => 'No se pueden eliminar carreras con cursos registrados: '.$bloqueadas->implode(', ').'.',
            ])->withInput();
        }

        DB::transaction(function () use ($datos, $institucion, $enviadas, $idsConserva) {
            $institucion->update([
                'tipo_id' => $datos['tipo_id'],
                'nombre' => $datos['nombre'],
                'pais' => $datos['pais'] ?? null,
                'gestion' => $datos['gestion'] ?? null,
                'licenciamiento' => $datos['licenciamiento'] ?? 'desconocido',
                'licenciamiento_resolucion' => $datos['licenciamiento_resolucion'] ?? null,
                'activa' => $datos['activa'] ?? false,
            ]);

            // Eliminar las carreras que el usuario quitó (ya validado que no tienen cursos).
            $institucion->carreras()->whereNotIn('id', $idsConserva)->delete();

            // Crear nuevas / actualizar existentes.
            foreach ($enviadas as $c) {
                if (! empty($c['id'])) {
                    $institucion->carreras()->whereKey($c['id'])->update(['nombre' => $c['nombre']]);
                } else {
                    $institucion->carreras()->create(['nombre' => $c['nombre']]);
                }
            }
        });

        AuditoriaService::registrar('editar', 'instituciones_externas', $institucion->id, $antes, $institucion->only(['tipo_id', 'nombre', 'pais', 'gestion', 'licenciamiento', 'activa']));

        return redirect()->route('instituciones.index')->with('status', 'Institución actualizada.');
    }

    public function activar(InstitucionExterna $institucion): RedirectResponse
    {
        $institucion->update(['activa' => true]);

        AuditoriaService::registrar('editar', 'instituciones_externas', $institucion->id, null, ['activa' => true]);

        return redirect()->route('instituciones.index')->with('status', 'Institución activada.');
    }

    public function destroy(InstitucionExterna $institucion): RedirectResponse
    {
        // RF-23: no se elimina físicamente; se desactiva.
        $institucion->update(['activa' => false]);

        AuditoriaService::registrar('editar', 'instituciones_externas', $institucion->id, null, ['activa' => false]);

        return redirect()->route('instituciones.index')->with('status', 'Institución desactivada.');
    }
}
