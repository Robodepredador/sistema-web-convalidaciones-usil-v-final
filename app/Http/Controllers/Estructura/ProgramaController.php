<?php

namespace App\Http\Controllers\Estructura;

use App\Http\Controllers\Controller;
use App\Models\Carrera;
use App\Models\Facultad;
use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Submódulo Programas Académicos. Tabla carreras.
 */
class ProgramaController extends Controller
{
    public function index(Request $request)
    {
        $programas = Carrera::with('facultad')
            ->withCount('planes')
            ->when($request->q, fn ($x, $v) => $x->where(fn ($w) => $w->where('nombre', 'like', "%{$v}%")->orWhere('codigo', 'like', "%{$v}%")))
            ->when($request->facultad_id, fn ($x, $v) => $x->where('facultad_id', $v))
            ->when($request->estado === 'activo', fn ($x) => $x->where('activo', true))
            ->when($request->estado === 'inactivo', fn ($x) => $x->where('activo', false))
            ->orderBy('nombre')
            ->paginate(10)->withQueryString()
            ->through(fn (Carrera $c) => [
                'id' => $c->id,
                'codigo' => $c->codigo,
                'facultad' => $c->facultad?->nombre,
                'nombre' => $c->nombre,
                'activo' => $c->activo,
                'planes_count' => $c->planes_count,
            ]);

        return inertia('Estructura/Programas/Index', [
            'programas' => $programas,
            'activos' => Carrera::where('activo', true)->count(),
            'facultades' => Facultad::orderBy('nombre')->get(['id', 'nombre']),
            'filtros' => $request->only(['q', 'facultad_id', 'estado']),
        ]);
    }

    public function create()
    {
        return inertia('Estructura/Programas/Form', [
            'programa' => null,
            'facultades' => Facultad::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);
        $datos['max_ciclos'] = 10; // valor por defecto usado por las Mallas (RF-07)
        $c = Carrera::create($datos);
        AuditoriaService::registrar('crear', 'carreras', $c->id, null, $datos);

        return redirect()->route('estructura.programas.index')->with('status', 'Programa académico registrado.');
    }

    public function edit(Carrera $programa)
    {
        return inertia('Estructura/Programas/Form', [
            'programa' => $programa->only(['id', 'codigo', 'facultad_id', 'nombre', 'activo']),
            'facultades' => Facultad::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function update(Request $request, Carrera $programa): RedirectResponse
    {
        $datos = $this->validar($request, $programa->id);
        $antes = $programa->only(['codigo', 'facultad_id', 'nombre', 'activo']);
        $programa->update($datos);
        AuditoriaService::registrar('editar', 'carreras', $programa->id, $antes, $datos);

        return redirect()->route('estructura.programas.index')->with('status', 'Programa académico actualizado.');
    }

    public function destroy(Carrera $programa): RedirectResponse
    {
        abort_if($programa->mallas()->exists() || $programa->planes()->exists(), 422,
            'No se puede eliminar: el programa tiene planes o mallas asociados.');

        $programa->delete();
        AuditoriaService::registrar('eliminar', 'carreras', $programa->id);

        return redirect()->route('estructura.programas.index')->with('status', 'Programa académico eliminado.');
    }

    public function estado(Carrera $programa): RedirectResponse
    {
        $programa->update(['activo' => ! $programa->activo]);
        AuditoriaService::registrar('editar', 'carreras', $programa->id, null, ['activo' => $programa->activo]);

        return back()->with('status', $programa->activo ? 'Programa activado.' : 'Programa inactivado.');
    }

    private function validar(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'codigo' => ['required', 'string', 'max:20', Rule::unique('carreras', 'codigo')->ignore($id)->whereNull('deleted_at')],
            'facultad_id' => ['required', 'exists:facultades,id'],
            'nombre' => ['required', 'string', 'max:150'],
            'activo' => ['boolean'],
        ]);
    }
}
