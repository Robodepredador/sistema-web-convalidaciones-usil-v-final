<?php

namespace App\Http\Controllers\Estructura;

use App\Http\Controllers\Controller;
use App\Models\Modalidad;
use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ModalidadController extends Controller
{
    public function index(Request $request)
    {
        $modalidades = Modalidad::query()
            ->when($request->q, fn ($x, $v) => $x->where(fn ($w) => $w->where('nombre', 'like', "%{$v}%")->orWhere('codigo', 'like', "%{$v}%")))
            ->when($request->estado === 'activo', fn ($x) => $x->where('activo', true))
            ->when($request->estado === 'inactivo', fn ($x) => $x->where('activo', false))
            ->orderBy('nombre')
            ->paginate(10)->withQueryString()
            ->through(fn (Modalidad $m) => [
                'id' => $m->id,
                'codigo' => $m->codigo,
                'nombre' => $m->nombre,
                'activo' => $m->activo,
            ]);

        return inertia('Estructura/Modalidades/Index', [
            'modalidades' => $modalidades,
            'activas' => Modalidad::where('activo', true)->count(),
            'filtros' => $request->only(['q', 'estado']),
        ]);
    }

    public function create()
    {
        return inertia('Estructura/Modalidades/Form', ['modalidad' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);
        $m = Modalidad::create($datos);
        AuditoriaService::registrar('crear', 'modalidades', $m->id, null, $datos);

        return redirect()->route('estructura.modalidades.index')->with('status', 'Modalidad registrada.');
    }

    public function edit(Modalidad $modalidad)
    {
        return inertia('Estructura/Modalidades/Form', [
            'modalidad' => $modalidad->only(['id', 'codigo', 'nombre', 'activo']),
        ]);
    }

    public function update(Request $request, Modalidad $modalidad): RedirectResponse
    {
        $datos = $this->validar($request, $modalidad->id);
        $antes = $modalidad->only(['codigo', 'nombre', 'activo']);
        $modalidad->update($datos);
        AuditoriaService::registrar('editar', 'modalidades', $modalidad->id, $antes, $datos);

        return redirect()->route('estructura.modalidades.index')->with('status', 'Modalidad actualizada.');
    }

    public function destroy(Modalidad $modalidad): RedirectResponse
    {
        abort_if($modalidad->planes()->exists(), 422, 'No se puede eliminar: la modalidad tiene planes asociados.');

        $modalidad->delete();
        AuditoriaService::registrar('eliminar', 'modalidades', $modalidad->id);

        return redirect()->route('estructura.modalidades.index')->with('status', 'Modalidad eliminada.');
    }

    public function estado(Modalidad $modalidad): RedirectResponse
    {
        $modalidad->update(['activo' => ! $modalidad->activo]);
        AuditoriaService::registrar('editar', 'modalidades', $modalidad->id, null, ['activo' => $modalidad->activo]);

        return back()->with('status', $modalidad->activo ? 'Modalidad activada.' : 'Modalidad inactivada.');
    }

    private function validar(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'codigo' => ['required', 'string', 'max:20', Rule::unique('modalidades', 'codigo')->ignore($id)->whereNull('deleted_at')],
            'nombre' => ['required', 'string', 'max:100', Rule::unique('modalidades', 'nombre')->ignore($id)->whereNull('deleted_at')],
            'activo' => ['boolean'],
        ]);
    }
}
