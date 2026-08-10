<?php

namespace App\Http\Controllers;

use App\Exports\MallaExternaPlantillaExport;
use App\Imports\MallaCursosImport;
use App\Models\CursoExterno;
use App\Models\MallaExterna;
use App\Services\IAConvalidacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class MallaExternaController extends Controller
{
    public function __construct(private IAConvalidacionService $ia) {}

    /** Plantilla para transcribir la malla oficial sin pasar por IA. */
    public function plantilla()
    {
        return Excel::download(new MallaExternaPlantillaExport, 'plantilla_cursos_malla_externa.xlsx');
    }

    /**
     * Lee la plantilla llena y devuelve la lista de cursos.
     *
     * Sustituye a `extraerIA` como origen de la lista y **devuelve la misma forma**,
     * de modo que la revisión en pantalla y `store()` no distinguen la fuente.
     *
     * `omitidas` es lo único que se añade frente a la IA: con un archivo que llena
     * una persona, decir qué línea se descartó y por qué es la diferencia entre
     * corregirlo y volver a intentarlo a ciegas.
     */
    public function previsualizarExcel(Request $request): JsonResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120'],
        ]);

        $import = new MallaCursosImport;
        Excel::import($import, $request->file('archivo'));

        $cursos = [];
        $omitidas = [];

        foreach ($import->filas as $i => $fila) {
            // La cabecera ocupa la línea 1, así que la colección arranca en la 2.
            $linea = $i + 2;
            $nombre = trim((string) ($fila['nombre'] ?? ''));

            if ($nombre === '') {
                // Una fila del todo vacía es separación visual, no un error que reportar.
                if (collect($fila)->filter(fn ($v) => trim((string) $v) !== '')->isNotEmpty()) {
                    $omitidas[] = ['linea' => $linea, 'motivo' => 'Sin nombre de curso.'];
                }

                continue;
            }

            $codigo = trim((string) ($fila['codigo'] ?? ''));
            $creditos = $fila['creditos'] ?? null;

            // Mismos límites que aplica `store()` a la salida de la IA: el destino
            // es la misma columna, así que se recorta aquí y no al guardar.
            $cursos[] = [
                'codigo' => $codigo === '' ? null : mb_substr($codigo, 0, 30),
                'nombre' => mb_substr($nombre, 0, 200),
                'creditos' => is_numeric($creditos) ? $creditos : null,
            ];
        }

        return response()->json(['cursos' => $cursos, 'omitidas' => $omitidas]);
    }

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
            // Disco privado: se sirve por `mallas-externas.pdf`, que exige sesión
            // y permiso. En el disco 'public' quedaba bajo public/storage y
            // cualquiera con la URL se lo llevaba, sin pasar por autenticación.
            $path = $request->file('pdf')->store('mallas_externas');

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
                        // mb_* como en previsualizarExcel(): substr() parte por
                        // bytes y dejaba UTF-8 roto en un nombre con tildes.
                        'codigo' => mb_substr($c['codigo'] ?? '', 0, 30),
                        'nombre' => mb_substr($c['nombre'], 0, 200),
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
        } catch (\Throwable $e) {
            DB::rollBack();

            // El detalle va al log, no al navegador: `$e->getMessage()` expone
            // errores de SQL, nombres de columna y rutas del sistema de archivos
            // incluso con APP_DEBUG=false, que es justo lo que esa bandera
            // debería impedir.
            Log::error('No se pudo registrar la malla externa', ['excepcion' => $e]);

            if ($request->expectsJson()) {
                return response()->json(['message' => 'No se pudo guardar la malla. Inténtelo de nuevo; '
                    .'si persiste, avise a soporte con la hora del intento.'], 500);
            }

            return back()->withErrors(['error' => 'No se pudo guardar la malla.']);
        }
    }

    /**
     * Sirve el PDF de la malla oficial. Vive en el disco privado, así que esta
     * es la única vía: exige sesión y el permiso `mallas_externas.gestionar`.
     */
    public function pdf(MallaExterna $mallaExterna)
    {
        abort_unless($mallaExterna->pdf_path, 404, 'Esta malla no tiene PDF adjunto.');

        // Las mallas registradas antes de mover el almacenamiento siguen en el
        // disco 'public'. Se buscan ahí como respaldo para no romper el enlace de
        // lo ya cargado; las nuevas nacen todas en el privado.
        foreach (['local', 'public'] as $disco) {
            if (Storage::disk($disco)->exists($mallaExterna->pdf_path)) {
                return Storage::disk($disco)->response($mallaExterna->pdf_path, basename($mallaExterna->pdf_path), [
                    'Content-Type' => 'application/pdf',
                ]);
            }
        }

        abort(404, 'El archivo no se encuentra en el almacenamiento.');
    }
}
