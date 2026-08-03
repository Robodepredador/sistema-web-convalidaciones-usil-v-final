<?php

namespace App\Http\Controllers;

use App\Models\CarreraExterna;
use App\Models\CursoExterno;
use App\Services\AuditoriaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Endpoints de catálogo para cargas en cascada bajo demanda (evita enviar
 * miles de registros al frontend).
 */
class CatalogoController extends Controller
{
    /** Carreras de una institución externa. */
    public function carrerasExternas(Request $request): JsonResponse
    {
        $request->validate(['institucion_id' => ['required', 'exists:instituciones_externas,id']]);

        return response()->json(
            CarreraExterna::where('institucion_id', $request->institucion_id)
                ->orderBy('nombre')->get(['id', 'nombre'])
        );
    }

    /** Crea (o reutiliza) una carrera de una institución externa. Todo mantenible. */
    public function crearCarreraExterna(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'institucion_id' => ['required', 'exists:instituciones_externas,id'],
            'nombre' => ['required', 'string', 'max:200'],
        ]);

        $carrera = CarreraExterna::firstOrCreate([
            'institucion_id' => $datos['institucion_id'],
            'nombre' => trim($datos['nombre']),
        ]);

        return response()->json(['id' => $carrera->id, 'nombre' => $carrera->nombre]);
    }

    // ---- Récord académico externo: cursos de la carrera de origen ----

    /** Registra un curso del plan de estudios de la carrera externa. */
    public function agregarCursoExterno(Request $request, CarreraExterna $carreraExterna): RedirectResponse
    {
        $datos = $request->validate([
            'codigo' => ['nullable', 'string', 'max:30'],
            'nombre' => ['required', 'string', 'max:200'],
            'creditos' => ['nullable', 'numeric', 'min:0', 'max:99'],
        ]);

        // Los cursos ahora cuelgan de la malla externa; se agregan a la malla activa de la carrera.
        $malla = $carreraExterna->mallas()->where('activa', true)->latest()->first();
        abort_if(! $malla, 422, 'La carrera de origen no tiene una malla oficial activa. Registra su malla en Equivalencias.');

        $curso = $malla->cursos()->firstOrCreate(
            ['nombre' => trim($datos['nombre'])],
            ['codigo' => $datos['codigo'] ?? null, 'creditos' => $datos['creditos'] ?? null]
        );

        AuditoriaService::registrar('crear', 'cursos_externos', $curso->id, null, $datos);

        return back()->with('status', "Curso «{$curso->nombre}» agregado al récord externo.");
    }

    public function actualizarCursoExterno(Request $request, CursoExterno $cursoExterno): RedirectResponse
    {
        $datos = $request->validate([
            'codigo' => ['nullable', 'string', 'max:30'],
            'nombre' => ['required', 'string', 'max:200'],
            'creditos' => ['nullable', 'numeric', 'min:0', 'max:99'],
        ]);

        $antes = $cursoExterno->only(['codigo', 'nombre', 'creditos']);
        $cursoExterno->update($datos);

        AuditoriaService::registrar('editar', 'cursos_externos', $cursoExterno->id, $antes, $datos);

        return back()->with('status', 'Curso externo actualizado.');
    }

    public function eliminarCursoExterno(CursoExterno $cursoExterno): RedirectResponse
    {
        abort_if($cursoExterno->equivalencias()->exists(), 422,
            'No se puede eliminar: el curso ya tiene equivalencias registradas.');

        $cursoExterno->delete();
        AuditoriaService::registrar('eliminar', 'cursos_externos', $cursoExterno->id);

        return back()->with('status', 'Curso externo eliminado.');
    }
}
