<?php

namespace App\Http\Controllers\Estructura;

use App\Http\Controllers\Controller;
use App\Models\Facultad;
use App\Models\UnidadNegocio;
use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FacultadController extends Controller
{
    public function index(Request $request)
    {
        $facultades = Facultad::with('sede')
            ->withCount('carreras')
            ->when($request->q, fn ($x, $v) => $x->where(fn ($w) => $w->where('nombre', 'like', "%{$v}%")->orWhere('codigo', 'like', "%{$v}%")))
            ->when($request->sede_id, fn ($x, $v) => $x->where('unidad_negocio_id', $v))
            ->when($request->estado === 'activo', fn ($x) => $x->where('activo', true))
            ->when($request->estado === 'inactivo', fn ($x) => $x->where('activo', false))
            ->orderBy('nombre')
            ->paginate(10)->withQueryString()
            ->through(fn (Facultad $f) => [
                'id' => $f->id,
                'codigo' => $f->codigo,
                'sede' => $f->sede?->nombre,
                'nombre' => $f->nombre,
                'activo' => $f->activo,
                'carreras_count' => $f->carreras_count,
            ]);

        return inertia('Estructura/Facultades/Index', [
            'facultades' => $facultades,
            'activas' => Facultad::where('activo', true)->count(),
            'sedes' => UnidadNegocio::orderBy('nombre')->get(['id', 'nombre']),
            'filtros' => $request->only(['q', 'sede_id', 'estado']),
        ]);
    }

    public function create()
    {
        return inertia('Estructura/Facultades/Form', [
            'facultad' => null,
            'sedes' => UnidadNegocio::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);
        $f = Facultad::create($datos);
        AuditoriaService::registrar('crear', 'facultades', $f->id, null, $datos);

        return redirect()->route('estructura.facultades.index')->with('status', 'Facultad registrada.');
    }

    public function edit(Facultad $facultad)
    {
        return inertia('Estructura/Facultades/Form', [
            'facultad' => $facultad->only(['id', 'codigo', 'unidad_negocio_id', 'nombre', 'activo']),
            'sedes' => UnidadNegocio::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function update(Request $request, Facultad $facultad): RedirectResponse
    {
        $datos = $this->validar($request, $facultad->id);
        $antes = $facultad->only(['codigo', 'unidad_negocio_id', 'nombre', 'activo']);
        $facultad->update($datos);
        AuditoriaService::registrar('editar', 'facultades', $facultad->id, $antes, $datos);

        return redirect()->route('estructura.facultades.index')->with('status', 'Facultad actualizada.');
    }

    public function destroy(Facultad $facultad): RedirectResponse
    {
        abort_if($facultad->carreras()->exists(), 422, 'No se puede eliminar: la facultad tiene programas asociados.');

        $facultad->delete();
        AuditoriaService::registrar('eliminar', 'facultades', $facultad->id);

        return redirect()->route('estructura.facultades.index')->with('status', 'Facultad eliminada.');
    }

    public function estado(Facultad $facultad): RedirectResponse
    {
        $facultad->update(['activo' => ! $facultad->activo]);
        AuditoriaService::registrar('editar', 'facultades', $facultad->id, null, ['activo' => $facultad->activo]);

        return back()->with('status', $facultad->activo ? 'Facultad activada.' : 'Facultad inactivada.');
    }

    private function validar(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'codigo' => ['required', 'string', 'max:20', Rule::unique('facultades', 'codigo')->ignore($id)->whereNull('deleted_at')],
            'unidad_negocio_id' => ['required', 'exists:unidades_negocio,id'],
            'nombre' => ['required', 'string', 'max:150'],
            'activo' => ['boolean'],
        ]);
    }
}
