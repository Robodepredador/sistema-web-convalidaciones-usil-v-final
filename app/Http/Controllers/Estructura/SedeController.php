<?php

namespace App\Http\Controllers\Estructura;

use App\Http\Controllers\Controller;
use App\Models\UnidadNegocio;
use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Submódulo Sedes (campus). Tabla unidades_negocio.
 */
class SedeController extends Controller
{
    public function index(Request $request)
    {
        $sedes = UnidadNegocio::query()
            ->when($request->q, fn ($x, $v) => $x->where(fn ($w) => $w->where('nombre', 'like', "%{$v}%")->orWhere('codigo', 'like', "%{$v}%")))
            ->when($request->estado === 'activo', fn ($x) => $x->where('activo', true))
            ->when($request->estado === 'inactivo', fn ($x) => $x->where('activo', false))
            ->withCount('facultades')
            ->orderBy('nombre')
            ->paginate(10)->withQueryString()
            ->through(fn (UnidadNegocio $s) => [
                'id' => $s->id,
                'codigo' => $s->codigo,
                'nombre' => $s->nombre,
                'direccion' => $s->direccion,
                'activo' => $s->activo,
                'facultades_count' => $s->facultades_count,
            ]);

        return inertia('Estructura/Sedes/Index', [
            'sedes' => $sedes,
            'activas' => UnidadNegocio::where('activo', true)->count(),
            'filtros' => $request->only(['q', 'estado']),
        ]);
    }

    public function create()
    {
        return inertia('Estructura/Sedes/Form', ['sede' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);
        $sede = UnidadNegocio::create($datos);

        AuditoriaService::registrar('crear', 'unidades_negocio', $sede->id, null, $datos);

        return redirect()->route('estructura.sedes.index')->with('status', 'Sede registrada correctamente.');
    }

    public function edit(UnidadNegocio $sede)
    {
        return inertia('Estructura/Sedes/Form', [
            'sede' => $sede->only(['id', 'codigo', 'nombre', 'direccion', 'activo']),
        ]);
    }

    public function update(Request $request, UnidadNegocio $sede): RedirectResponse
    {
        $datos = $this->validar($request, $sede->id);
        $antes = $sede->only(['codigo', 'nombre', 'direccion', 'activo']);
        $sede->update($datos);

        AuditoriaService::registrar('editar', 'unidades_negocio', $sede->id, $antes, $datos);

        return redirect()->route('estructura.sedes.index')->with('status', 'Sede actualizada correctamente.');
    }

    public function destroy(UnidadNegocio $sede): RedirectResponse
    {
        abort_if($sede->facultades()->exists(), 422, 'No se puede eliminar: la sede tiene facultades asociadas.');

        $sede->delete(); // borrado lógico (soft delete)
        AuditoriaService::registrar('eliminar', 'unidades_negocio', $sede->id);

        return redirect()->route('estructura.sedes.index')->with('status', 'Sede eliminada.');
    }

    public function estado(UnidadNegocio $sede): RedirectResponse
    {
        $sede->update(['activo' => ! $sede->activo]);
        AuditoriaService::registrar('editar', 'unidades_negocio', $sede->id, null, ['activo' => $sede->activo]);

        return back()->with('status', $sede->activo ? 'Sede activada.' : 'Sede inactivada.');
    }

    private function validar(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'codigo' => ['required', 'string', 'max:20', Rule::unique('unidades_negocio', 'codigo')->ignore($id)->whereNull('deleted_at')],
            'nombre' => ['required', 'string', 'max:100', Rule::unique('unidades_negocio', 'nombre')->ignore($id)->whereNull('deleted_at')],
            'direccion' => ['nullable', 'string', 'max:200'],
            'activo' => ['boolean'],
        ]);
    }
}
