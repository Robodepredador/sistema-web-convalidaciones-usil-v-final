<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Models\CursoNoConvalidable;
use App\Services\AuditoriaService;
use App\Services\ConvalidacionEngine;
use App\Services\IAConvalidacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Configuración del sistema (solo Administrador).
 * Incluye el motor de IA para convalidaciones: proveedor, modelo y API key.
 */
class ConfiguracionController extends Controller
{
    public function __construct(private IAConvalidacionService $ia) {}

    public function index(): Response
    {
        return inertia('Configuracion/Index', [
            'ia' => [
                'proveedor' => Configuracion::get('ia_proveedor', config('services.ia.proveedor', 'gemini')),
                'gemini_model' => Configuracion::get('gemini_model', config('services.gemini.model', 'gemini-2.5-flash')),
                'openai_model' => Configuracion::get('openai_model', config('services.openai.model', 'gpt-4o')),
                // Nunca se envía la clave al frontend; solo si existe y su fin (enmascarada).
                'gemini_key_set' => Configuracion::tiene('gemini_api_key') || (bool) config('services.gemini.key'),
                'openai_key_set' => Configuracion::tiene('openai_api_key') || (bool) config('services.openai.key'),
                'disponible' => $this->ia->disponible(),
            ],
            'modelos' => [
                'gemini' => ['gemini-2.5-flash', 'gemini-2.5-flash-lite', 'gemini-2.5-pro'],
                'openai' => ['gpt-4o', 'gpt-4o-mini'],
            ],
            // Solo las institucionales: las de cada carrera se administran desde
            // su malla, con el alcance por rol de esa pantalla.
            'noConvalidables' => CursoNoConvalidable::whereNull('carrera_id')
                ->orderBy('palabra_clave')
                ->get(['id', 'palabra_clave', 'motivo', 'activo']),
            // Responsables del memorándum (formato oficial CPEL-USIL).
            'memorandum' => ConvalidacionController::responsablesMemo(),
        ]);
    }

    /** Guarda los responsables/campos del memorándum. */
    public function updateMemorandum(Request $request): RedirectResponse
    {
        $reglas = [];
        foreach (array_keys(ConvalidacionController::MEMO_DEFAULTS) as $clave) {
            $reglas[$clave] = ['nullable', 'string', 'max:200'];
        }
        $datos = $request->validate($reglas);

        foreach (array_keys(ConvalidacionController::MEMO_DEFAULTS) as $clave) {
            // Vacío → se borra la clave y vuelve al valor por defecto.
            Configuracion::set($clave, $datos[$clave] ?? null);
        }

        AuditoriaService::registrar('editar', 'configuraciones', null, null, ['memorandum' => true]);

        return back()->with('status', 'Responsables del memorándum actualizados.');
    }

    /** Agrega una materia a la lista de no convalidables (origen). */
    public function agregarNoConvalidable(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'palabra_clave' => ['required', 'string', 'max:120'],
            'motivo' => ['nullable', 'string', 'max:150'],
        ]);

        CursoNoConvalidable::updateOrCreate(
            ['clave_normalizada' => app(ConvalidacionEngine::class)->normaliza($datos['palabra_clave']),
                'carrera_id' => null],
            ['palabra_clave' => $datos['palabra_clave'], 'motivo' => $datos['motivo'] ?? null, 'activo' => true],
        );

        return back()->with('status', 'Materia agregada a la lista de no convalidables.');
    }

    /** Activa/desactiva o elimina una materia de la lista. */
    public function actualizarNoConvalidable(Request $request, CursoNoConvalidable $noConvalidable): RedirectResponse
    {
        // Esta pantalla es la de la política institucional; las reglas de una
        // carrera se editan desde su malla, donde rige el alcance por rol.
        abort_unless($noConvalidable->carrera_id === null, 403,
            'Esa regla pertenece a una carrera: edítela desde su plan de estudios.');

        if ($request->boolean('eliminar')) {
            $noConvalidable->delete();

            return back()->with('status', 'Materia eliminada de la lista.');
        }

        $noConvalidable->update(['activo' => $request->boolean('activo')]);

        return back()->with('status', 'Lista de no convalidables actualizada.');
    }

    public function update(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'proveedor' => ['required', 'in:gemini,openai'],
            'gemini_model' => ['nullable', 'string', 'max:60'],
            'openai_model' => ['nullable', 'string', 'max:60'],
            // Las claves son opcionales: si vienen vacías, se conserva la existente.
            'gemini_api_key' => ['nullable', 'string', 'max:200'],
            'openai_api_key' => ['nullable', 'string', 'max:200'],
            'limpiar_gemini' => ['boolean'],
            'limpiar_openai' => ['boolean'],
        ]);

        Configuracion::set('ia_proveedor', $datos['proveedor']);
        Configuracion::set('gemini_model', $datos['gemini_model'] ?? null);
        Configuracion::set('openai_model', $datos['openai_model'] ?? null);

        // Actualiza claves solo si el usuario escribió una nueva o pidió limpiarla.
        if ($request->boolean('limpiar_gemini')) {
            Configuracion::set('gemini_api_key', null);
        } elseif (! empty($datos['gemini_api_key'])) {
            Configuracion::set('gemini_api_key', $datos['gemini_api_key']);
        }
        if ($request->boolean('limpiar_openai')) {
            Configuracion::set('openai_api_key', null);
        } elseif (! empty($datos['openai_api_key'])) {
            Configuracion::set('openai_api_key', $datos['openai_api_key']);
        }

        AuditoriaService::registrar('editar', 'configuraciones', null, null, ['ia_proveedor' => $datos['proveedor']]);

        return back()->with('status', 'Configuración guardada.');
    }

    /** Prueba la conexión con el proveedor sin necesidad de guardar. */
    public function probar(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'proveedor' => ['required', 'in:gemini,openai'],
            'modelo' => ['nullable', 'string', 'max:60'],
            'api_key' => ['nullable', 'string', 'max:200'],
        ]);

        @set_time_limit(60);

        // Si no se envía clave nueva, se usa la ya guardada.
        $apiKey = $datos['api_key'] ?: (string) Configuracion::get(
            $datos['proveedor'] === 'openai' ? 'openai_api_key' : 'gemini_api_key',
            (string) config($datos['proveedor'] === 'openai' ? 'services.openai.key' : 'services.gemini.key')
        );

        $modelo = $datos['modelo'] ?: ($datos['proveedor'] === 'openai' ? 'gpt-4o' : 'gemini-2.5-flash');

        return response()->json($this->ia->probar($datos['proveedor'], $apiKey, $modelo));
    }
}
