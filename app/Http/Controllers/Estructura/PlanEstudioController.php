<?php

namespace App\Http\Controllers\Estructura;

use App\Http\Controllers\Controller;
use App\Models\Carrera;
use App\Models\Modalidad;
use App\Models\PlanEstudio;
use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanEstudioController extends Controller
{
    public function index(Request $request)
    {
        $planes = PlanEstudio::with(['carrera', 'modalidad'])
            ->when($request->q, fn ($x, $v) => $x->where(fn ($w) => $w->where('nombre', 'like', "%{$v}%")->orWhere('codigo', 'like', "%{$v}%")))
            ->when($request->carrera_id, fn ($x, $v) => $x->where('carrera_id', $v))
            ->when($request->modalidad_id, fn ($x, $v) => $x->where('modalidad_id', $v))
            ->when($request->estado === 'activo', fn ($x) => $x->where('activo', true))
            ->when($request->estado === 'inactivo', fn ($x) => $x->where('activo', false))
            ->orderByDesc('anio')->orderBy('codigo')
            ->paginate(10)->withQueryString()
            ->through(fn (PlanEstudio $p) => [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'programa' => $p->carrera?->nombre,
                'modalidad' => $p->modalidad?->nombre,
                'nombre' => $p->nombre,
                'anio' => $p->anio,
                'version' => $p->version,
                'activo' => $p->activo,
            ]);

        return inertia('Estructura/Planes/Index', [
            'planes' => $planes,
            'activos' => PlanEstudio::where('activo', true)->count(),
            'programas' => Carrera::orderBy('nombre')->get(['id', 'nombre']),
            'modalidades' => Modalidad::orderBy('nombre')->get(['id', 'nombre']),
            'filtros' => $request->only(['q', 'carrera_id', 'modalidad_id', 'estado']),
        ]);
    }

    public function create()
    {
        return inertia('Estructura/Planes/Form', $this->opciones() + ['plan' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);
        $p = PlanEstudio::create($datos);
        AuditoriaService::registrar('crear', 'planes_estudio', $p->id, null, $datos);

        return redirect()->route('estructura.planes.index')->with('status', 'Plan de estudios registrado.');
    }

    public function edit(PlanEstudio $plan)
    {
        return inertia('Estructura/Planes/Form', $this->opciones() + [
            'plan' => $plan->only(['id', 'codigo', 'carrera_id', 'modalidad_id', 'nombre', 'anio', 'version', 'activo']),
        ]);
    }

    public function update(Request $request, PlanEstudio $plan): RedirectResponse
    {
        $datos = $this->validar($request, $plan->id);
        $antes = $plan->only(['codigo', 'carrera_id', 'modalidad_id', 'nombre', 'anio', 'version', 'activo']);
        $plan->update($datos);
        AuditoriaService::registrar('editar', 'planes_estudio', $plan->id, $antes, $datos);

        return redirect()->route('estructura.planes.index')->with('status', 'Plan de estudios actualizado.');
    }

    public function destroy(PlanEstudio $plan): RedirectResponse
    {
        $plan->delete();
        AuditoriaService::registrar('eliminar', 'planes_estudio', $plan->id);

        return redirect()->route('estructura.planes.index')->with('status', 'Plan de estudios eliminado.');
    }

    public function estado(PlanEstudio $plan): RedirectResponse
    {
        $plan->update(['activo' => ! $plan->activo]);
        AuditoriaService::registrar('editar', 'planes_estudio', $plan->id, null, ['activo' => $plan->activo]);

        return back()->with('status', $plan->activo ? 'Plan activado.' : 'Plan inactivado.');
    }

    private function opciones(): array
    {
        return [
            'programas' => Carrera::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'modalidades' => Modalidad::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ];
    }

    private function validar(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'codigo' => ['required', 'string', 'max:30', Rule::unique('planes_estudio', 'codigo')->ignore($id)->whereNull('deleted_at')],
            'carrera_id' => ['required', 'exists:carreras,id'],
            'modalidad_id' => ['required', 'exists:modalidades,id'],
            'nombre' => ['required', 'string', 'max:150'],
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            // RN: no repetir versión por programa y año.
            'version' => ['required', 'string', 'max:20', Rule::unique('planes_estudio', 'version')
                ->where(fn ($w) => $w->where('carrera_id', $request->carrera_id)->where('anio', $request->anio))
                ->ignore($id)->whereNull('deleted_at')],
            'activo' => ['boolean'],
        ]);
    }
}
