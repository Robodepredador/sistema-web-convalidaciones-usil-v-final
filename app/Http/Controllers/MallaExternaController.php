<?php

namespace App\Http\Controllers;

use App\Models\CursoExterno;
use App\Models\MallaExterna;
use App\Services\IAConvalidacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MallaExternaController extends Controller
{
    public function __construct(private IAConvalidacionService $ia) {}

    /**
     * Extrae el catálogo de cursos desde un PDF de malla oficial usando IA.
     */
    public function extraerIA(Request $request): JsonResponse
    {
        $request->validate([
            'documento' => ['required', 'file', 'mimes:pdf', 'max:20480'], // Max 20MB
        ]);

        @set_time_limit(180);

        if (! $this->ia->disponible()) {
            return response()->json(['message' => 'IA no configurada. Ve a Configuración y define la API key.'], 422);
        }

        $archivo = $request->file('documento');
        $contenido = file_get_contents($archivo->getRealPath());
        $nombre = $archivo->getClientOriginalName();

        try {
            $extraccion = $this->ia->extraerMallaOficial($contenido, $nombre);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'No se pudo procesar el documento: '.$e->getMessage()], 502);
        }

        return response()->json($extraccion);
    }

    /**
     * Sube un PDF de malla oficial, registra la Malla Externa y guarda el catálogo de cursos.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'carrera_externa_id' => ['required', 'exists:carreras_externas,id'],
            'anio' => ['required', 'string', 'max:4'],
            'version' => ['nullable', 'string', 'max:10'],
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:20480'], // Max 20MB
            'cursos' => ['required', 'string'], // JSON de cursos extraídos
        ]);

        $cursosExtraidos = json_decode($request->cursos, true);
        if (! is_array($cursosExtraidos)) {
            return back()->withErrors(['cursos' => 'Formato de cursos inválido.']);
        }

        DB::beginTransaction();
        try {
            $path = $request->file('pdf')->store('mallas_externas', 'public');

            // Solo una malla oficial activa por carrera: desactiva las anteriores.
            MallaExterna::where('carrera_externa_id', $request->carrera_externa_id)
                ->where('activa', true)->update(['activa' => false]);

            $malla = MallaExterna::create([
                'carrera_externa_id' => $request->carrera_externa_id,
                'anio' => $request->anio,
                'version' => $request->version,
                'activa' => true,
                'pdf_path' => $path,
            ]);

            $cursosNuevos = [];
            foreach ($cursosExtraidos as $c) {
                if (! empty($c['nombre'])) {
                    $cursosNuevos[] = [
                        'malla_externa_id' => $malla->id,
                        'codigo' => substr($c['codigo'] ?? '', 0, 30),
                        'nombre' => substr($c['nombre'], 0, 200),
                        'creditos' => is_numeric($c['creditos'] ?? null) ? $c['creditos'] : null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            if (! empty($cursosNuevos)) {
                CursoExterno::insert($cursosNuevos);
            }

            DB::commit();

            if ($request->expectsJson()) {
                return response()->json(['id' => $malla->id, 'status' => 'Malla oficial registrada y cursos extraídos.']);
            }

            return back()->with('status', 'Malla externa oficial registrada exitosamente.');
        } catch (\Exception $e) {
            DB::rollBack();
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Error al guardar la malla: '.$e->getMessage()], 500);
            }

            return back()->withErrors(['error' => 'Error al guardar la malla.']);
        }
    }
}
