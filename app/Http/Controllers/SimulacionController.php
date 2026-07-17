<?php

namespace App\Http\Controllers;

use App\Exports\PreconvalidacionExport;
use App\Models\Carrera;
use App\Models\CursoExterno;
use App\Models\CursoUsil;
use App\Models\Equivalencia;
use App\Models\MallaCurricular;
use App\Models\Postulante;
use App\Models\PostulanteDestino;
use App\Models\PostulanteDocumento;
use App\Models\Simulacion;
use App\Models\SimulacionDetalle;
use App\Services\AlcanceService;
use App\Services\AuditoriaService;
use App\Services\ConvalidacionEngine;
use App\Services\IAConvalidacionService;
use App\Services\SimulacionService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

/**
 * CU-04/05: Simulación de convalidación (manual y con IA).
 *
 * Flujo: se parte de la lista de postulantes; para cada postulante se genera un
 * expediente mapeando sus cursos de origen contra el plan de estudios USIL
 * (mallas → ciclos → cursos_usil). El mapeo se propone por similitud o con IA.
 */
class SimulacionController extends Controller
{
    public function __construct(
        private SimulacionService $service,
        private ConvalidacionEngine $engine,
        private IAConvalidacionService $ia,
    ) {}

    /** Lista de postulantes lista para iniciar/ver simulaciones. */
    public function index(Request $request)
    {
        $user = $request->user();

        // Alcance por rol: null = todas; array = solo esas carreras (RF-40 + Decano por facultad).
        $carrerasPermitidas = AlcanceService::carrerasVisibles($user);

        // Una fila por destino solicitado (postulante × carrera USIL): un
        // postulante que pidió varias carreras aparece varias veces.
        $postulantes = PostulanteDestino::query()
            ->whereHas('postulante', fn ($p) => $p->where('revision_estado', 'aprobada'))
            ->with(['carrera:id,nombre', 'postulante.institucionOrigen:id,nombre', 'postulante.carreraExterna:id,nombre'])
            ->when($request->q, fn ($qq, $v) => $qq->whereHas('postulante', fn ($w) => $w
                ->where('nombres', 'like', "%$v%")
                ->orWhere('apellido_paterno', 'like', "%$v%")
                ->orWhere('apellido_materno', 'like', "%$v%")
                ->orWhere('numero_documento', 'like', "%$v%")))
            ->when($request->carrera_destino_id, fn ($qq, $v) => $qq->where('carrera_id', $v))
            ->when($carrerasPermitidas !== null, fn ($qq) => $qq->whereIn('carrera_id', $carrerasPermitidas))
            ->orderByDesc('id')
            ->paginate(12)->withQueryString()
            ->through(function (PostulanteDestino $d) {
                $p = $d->postulante;

                return [
                    'id' => $p->id,
                    'destino_id' => $d->id,
                    'carrera_destino_id' => $d->carrera_id,
                    'codigo' => $p->codigo,
                    'nombre' => $p->nombre_completo,
                    'documento' => "{$p->tipo_documento} {$p->numero_documento}",
                    'institucion' => $p->institucionOrigen?->nombre,
                    'carrera_externa' => $p->carreraExterna?->nombre,
                    'carrera_destino' => $d->carrera?->nombre,
                    'simulaciones_count' => Simulacion::where('postulante_id', $p->id)
                        ->where('carrera_usil_id', $d->carrera_id)->count(),
                ];
            });

        return inertia('Simulaciones/Index', [
            'postulantes' => $postulantes,
            'carreras' => Carrera::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'filtros' => $request->only(['q', 'carrera_destino_id']),
            'ia' => ['disponible' => $this->ia->disponible(), 'proveedor' => $this->ia->proveedor()],
        ]);
    }

    /** Espacio de trabajo de simulación para un postulante (nueva). */
    public function crear(Request $request, Postulante $postulante)
    {
        abort_unless($postulante->revision_estado === 'aprobada', 403,
            'La solicitud aún no ha sido aprobada por el Ejecutivo Comercial de Admisión.');

        // Carrera destino elegida en la lista (una de las que solicitó el postulante).
        $carreraId = $request->integer('carrera') ?: $postulante->carrera_destino_id;

        return inertia('Simulaciones/Simular', $this->propsWorkspace($postulante, null, $carreraId));
    }

    /** Espacio de trabajo para EDITAR una simulación existente. */
    public function editar(Simulacion $simulacion)
    {
        $simulacion->load(['detalles.cursoUsil', 'detalles.cursoExterno', 'postulante']);

        return inertia('Simulaciones/Simular', $this->propsWorkspace($simulacion->postulante, $simulacion, $simulacion->carrera_usil_id));
    }

    /** Props del workspace, compartidas por crear() y editar(). */
    private function propsWorkspace(Postulante $postulante, ?Simulacion $edicion, ?int $carreraDestinoId = null): array
    {
        $postulante->load(['institucionOrigen', 'carreraExterna', 'documentos']);

        $carreraDestinoId = $carreraDestinoId ?: $postulante->carrera_destino_id;
        $carreraDestino = $carreraDestinoId ? Carrera::find($carreraDestinoId) : null;
        $pool = $carreraDestinoId ? $this->engine->poolCursosUsil($carreraDestinoId) : [];

        // El récord de origen se llena a mano o con el extractor de IA; arranca vacío.
        $cursosOrigen = [];

        // Al editar: se reconstruyen las filas desde el detalle guardado.
        $edicionData = null;
        if ($edicion) {
            $edicionData = [
                'id' => $edicion->id,
                'metodo' => $edicion->metodo,
                'escala_notas' => $edicion->escala_notas,
                'nota_minima' => $edicion->nota_minima,
                'universidad_origen' => $edicion->universidad_origen,
                'observaciones' => $edicion->observaciones,
                'filas' => $edicion->detalles->map(fn (SimulacionDetalle $d) => [
                    'curso_externo_id' => $d->curso_externo_id,
                    'curso_origen_nombre' => $d->nombre_origen,
                    'nota_origen' => $d->nota_origen,
                    'creditos_origen' => $d->creditos_origen,
                    'ciclo_origen' => $d->ciclo_origen,
                    'clasificacion' => $d->clasificacion,
                    'curso_usil_id' => $d->curso_usil_id,
                    'confianza' => $d->confianza,
                ])->values(),
            ];
        }

        return [
            'postulante' => [
                'id' => $postulante->id,
                'nombre' => $postulante->nombre_completo,
                'documento' => "{$postulante->tipo_documento} {$postulante->numero_documento}",
                'institucion' => $postulante->institucionOrigen?->nombre,
                'carrera_externa' => $postulante->carreraExterna?->nombre,
                'carrera_destino' => $carreraDestino?->nombre,
                'carrera_destino_id' => $carreraDestinoId,
                'carrera_externa_id' => $postulante->carrera_externa_id,
                'ciclo_postulacion' => $postulante->ciclo_postulacion,
            ],
            'poolUsil' => $pool,
            'cursosOrigen' => $cursosOrigen,
            'documentos' => $postulante->documentos->map(fn ($d) => [
                'id' => $d->id,
                'tipo' => $d->tipo,
                'nombre' => $d->nombre_original,
                'url' => route('documentos.ver', $d->id),
            ]),
            'tieneMalla' => $carreraDestinoId ? (bool) $this->engine->mallaDeCarrera($carreraDestinoId) : false,
            'noConvalidar' => ConvalidacionEngine::NO_CONVALIDAR,
            'ia' => ['disponible' => $this->ia->disponible(), 'proveedor' => $this->ia->proveedor()],
            'edicion' => $edicionData,
            'simulacionesPrevias' => $postulante->simulaciones()
                ->when($carreraDestinoId, fn ($q) => $q->where('carrera_usil_id', $carreraDestinoId))
                ->with('carreraUsil')->orderByDesc('id')->get()
                ->map(fn (Simulacion $s) => [
                    'id' => $s->id, 'metodo' => $s->metodo, 'estado' => $s->estado,
                    'carrera' => $s->carreraUsil?->nombre, 'fecha' => $s->created_at?->format('d/m/Y H:i'),
                ]),
        ];
    }

    /** Sugerencia de mapeo por similitud (sin IA). */
    public function sugerirSimilitud(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'carrera_usil_id' => ['required', 'exists:carreras,id'],
            'cursos' => ['array'],
            'cursos.*' => ['string'],
        ]);

        $pool = $this->engine->poolCursosUsil((int) $datos['carrera_usil_id']);
        $mapa = $this->engine->asignacionOptima($datos['cursos'] ?? [], $pool);

        return response()->json(['mapa' => $mapa]);
    }

    /**
     * Sirve el archivo del récord académico del postulante en línea (el navegador
     * muestra PDF/imagen y permite descargarlo). Solo lectura del expediente.
     */
    public function verDocumento(PostulanteDocumento $documento)
    {
        abort_unless(Storage::exists($documento->ruta), 404, 'El documento no se encuentra en el almacenamiento.');

        return Storage::response($documento->ruta, $documento->nombre_original);
    }

    /**
     * Sugerencia de mapeo reutilizando equivalencias ya registradas en el catálogo
     * (Equivalencia) para el mismo par carrera externa ↔ carrera USIL. A diferencia
     * de similitud/IA, esto no "adivina": solo aplica cruces que un evaluador ya
     * validó antes para esa institución, con 100% de confianza.
     */
    public function sugerirCatalogo(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'carrera_usil_id' => ['required', 'exists:carreras,id'],
            'carrera_externa_id' => ['nullable', 'exists:carreras_externas,id'],
            'cursos' => ['array'],
            'cursos.*' => ['string'],
        ]);

        if (empty($datos['carrera_externa_id'])) {
            return response()->json(['mapa' => [], 'message' => 'El postulante no tiene carrera externa registrada.']);
        }

        $pool = $this->engine->poolCursosUsil((int) $datos['carrera_usil_id']);
        $porId = collect($pool)->keyBy('id');

        // Nombre externo normalizado → curso USIL (solo si ese curso sigue vigente en la malla actual).
        $porNombreExterno = Equivalencia::where('carrera_usil_id', $datos['carrera_usil_id'])
            ->where('carrera_externa_id', $datos['carrera_externa_id'])
            ->with('cursoExterno:id,nombre')
            ->get()
            ->filter(fn (Equivalencia $eq) => $eq->cursoExterno && $porId->has($eq->curso_usil_id))
            ->mapWithKeys(fn (Equivalencia $eq) => [$this->engine->normaliza($eq->cursoExterno->nombre) => $eq->curso_usil_id]);

        $mapa = [];
        $usados = [];
        foreach ($datos['cursos'] ?? [] as $curso) {
            $usilId = $porNombreExterno[$this->engine->normaliza($curso)] ?? null;
            if ($usilId && ! isset($usados[$usilId])) {
                $mapa[$curso] = ['curso_usil_id' => $usilId, 'label' => $porId[$usilId]['label'], 'confianza' => 100.0];
                $usados[$usilId] = true;
            }
        }

        return response()->json(['mapa' => $mapa]);
    }

    /** Sugerencia de mapeo semántico con IA. */
    public function sugerirIA(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'carrera_usil_id' => ['required', 'exists:carreras,id'],
            'cursos' => ['array'],
            'cursos.*' => ['string'],
        ]);

        if (! $this->ia->disponible()) {
            return response()->json(['message' => 'IA no configurada. Define la API key en .env.'], 422);
        }

        @set_time_limit(180);

        $carrera = Carrera::find($datos['carrera_usil_id']);
        $pool = $this->engine->poolCursosUsil((int) $datos['carrera_usil_id']);
        $porLabel = collect($pool)->keyBy('label');

        try {
            $mapeo = $this->ia->sugerirMapeo($carrera->nombre, $datos['cursos'] ?? [], $pool);
        } catch (\Throwable $e) {
            return response()->json(['message' => $this->mensajeErrorIA($e, 'No se pudo consultar la IA')], 502);
        }

        // Traduce label → curso_usil_id.
        $mapa = [];
        foreach ($datos['cursos'] ?? [] as $curso) {
            $label = $mapeo[$curso] ?? ConvalidacionEngine::NO_CONVALIDAR;
            $mapa[$curso] = [
                'curso_usil_id' => $porLabel[$label]['id'] ?? null,
                'label' => $label,
                'confianza' => $label === ConvalidacionEngine::NO_CONVALIDAR ? 0 : 90,
            ];
        }

        return response()->json(['mapa' => $mapa]);
    }

    /**
     * Extracción de cursos con IA.
     *
     * Trabaja con la base de datos existente: por defecto usa un documento ya
     * cargado por el postulante (trazabilidad); admite también subir uno nuevo.
     */
    public function extraerIA(Request $request): JsonResponse
    {
        $request->validate([
            'documento_id' => ['nullable', 'integer', 'exists:postulante_documentos,id'],
            'documento' => ['nullable', 'file', 'max:20480', 'mimes:pdf,png,jpg,jpeg,gif,webp,txt,csv'],
            'carrera_externa_id' => ['nullable', 'integer', 'exists:carreras_externas,id'],
        ]);

        // La extracción con IA de un PDF puede tardar más que el límite por defecto.
        @set_time_limit(180);

        if (! $this->ia->disponible()) {
            return response()->json(['message' => 'IA no configurada. Ve a Configuración y define la API key.'], 422);
        }

        // Catálogo real de la institución (para completar/normalizar nombres extraídos).
        $carreraExternaId = $request->integer('carrera_externa_id') ?: null;

        // 1) Documento existente del postulante (fuente principal).
        if ($request->filled('documento_id')) {
            $doc = PostulanteDocumento::with('postulante')->findOrFail($request->integer('documento_id'));
            if (! Storage::exists($doc->ruta)) {
                return response()->json(['message' => 'El documento del postulante no se encuentra en el almacenamiento.'], 404);
            }
            $contenido = Storage::get($doc->ruta);
            $nombre = $doc->nombre_original;
            $rutaTrazabilidad = $doc->ruta;
            $carreraExternaId = $carreraExternaId ?: $doc->postulante?->carrera_externa_id;
        } elseif ($request->hasFile('documento')) {
            // 2) Subida puntual (alternativa).
            $archivo = $request->file('documento');
            $contenido = file_get_contents($archivo->getRealPath());
            $nombre = $archivo->getClientOriginalName();
            $rutaTrazabilidad = null;
        } else {
            return response()->json(['message' => 'Selecciona un documento del postulante o sube uno.'], 422);
        }

        try {
            $extraccion = $this->ia->extraerCursos($contenido, $nombre);
        } catch (\Throwable $e) {
            return response()->json(['message' => $this->mensajeErrorIA($e, 'No se pudo procesar el documento')], 502);
        }

        // Catálogo canónico de la institución de origen (nombres completos y bien acentuados).
        $catalogo = $carreraExternaId
            ? CursoExterno::whereHas('mallaExterna', fn ($q) => $q
                    ->where('carrera_externa_id', $carreraExternaId)->where('activa', true))
                ->pluck('nombre')->all()
            : [];

        // Completa/normaliza cada nombre extraído contra el catálogo (o formatea a estilo oración).
        $normalizar = fn ($c) => [
            'nombre' => $this->engine->nombreCanonico((string) ($c['curso'] ?? ''), $catalogo),
            'nota' => $c['nota'] ?? '',
            'creditos' => $c['creditos'] ?? '',
            'ciclo' => $c['ciclo'] ?? '',
        ];

        // Separa por clasificación (no convalidables entre los aprobados).
        $aprobados = [];
        $noConv = [];
        foreach ($extraccion['aprobados'] as $c) {
            $fila = $normalizar($c);
            if ($this->engine->esNoConvalidable($fila['nombre'])) {
                $noConv[] = $fila;
            } else {
                $aprobados[] = $fila;
            }
        }
        $desaprobados = array_map($normalizar, $extraccion['desaprobados']);

        return response()->json([
            'estudiante' => $extraccion['estudiante'],
            'institucion' => $extraccion['institucion'],
            'aprobados' => $aprobados,
            'no_convalidables' => $noConv,
            'desaprobados' => $desaprobados,
            'documento_path' => $rutaTrazabilidad,
            'documento_nombre' => $nombre,
        ]);
    }

    /** Guarda una nueva simulación. */
    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $simulacion = $this->persistirSimulacion($request, null);

        AuditoriaService::registrar('crear', 'simulaciones', $simulacion->id, null, ['metodo' => $simulacion->metodo]);

        if ($request->expectsJson()) {
            return response()->json(['id' => $simulacion->id, 'status' => 'Preconvalidación guardada.']);
        }

        return redirect()->route('simulaciones.show', $simulacion->id)->with('status', 'Simulación generada.');
    }

    /** Actualiza una simulación existente y su detalle. */
    public function update(Request $request, Simulacion $simulacion): RedirectResponse|JsonResponse
    {
        $this->persistirSimulacion($request, $simulacion);

        AuditoriaService::registrar('editar', 'simulaciones', $simulacion->id, null, ['metodo' => $simulacion->metodo]);

        if ($request->expectsJson()) {
            return response()->json(['id' => $simulacion->id, 'status' => 'Preconvalidación actualizada.']);
        }

        return redirect()->route('simulaciones.show', $simulacion->id)->with('status', 'Simulación actualizada.');
    }

    /** Valida y persiste (crea o actualiza) la simulación con su detalle. */
    private function persistirSimulacion(Request $request, ?Simulacion $existente): Simulacion
    {
        $datos = $request->validate([
            'postulante_id' => ['required', 'exists:postulantes,id'],
            'carrera_usil_id' => ['required', 'exists:carreras,id'],
            'metodo' => ['required', 'in:manual,ia'],
            'universidad_origen' => ['nullable', 'string', 'max:200'],
            'documento_path' => ['nullable', 'string', 'max:500'],
            'escala_notas' => ['nullable', 'string', 'max:10'],
            'nota_minima' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'filas' => ['array'],
            'filas.*.curso_origen_nombre' => ['required', 'string', 'max:200'],
            'filas.*.curso_externo_id' => ['nullable', 'integer'],
            'filas.*.curso_usil_id' => ['nullable', 'integer', 'exists:cursos_usil,id'],
            'filas.*.nota_origen' => ['nullable', 'string', 'max:20'],
            'filas.*.creditos_origen' => ['nullable', 'numeric'],
            'filas.*.ciclo_origen' => ['nullable', 'string', 'max:30'],
            'filas.*.clasificacion' => ['required', 'in:convalidable,desaprobado,no_convalidable'],
            'filas.*.confianza' => ['nullable', 'numeric'],
            'filas.*.origen' => ['nullable', 'in:automatico,manual,ia,similitud,catalogo'],
        ]);

        $postulante = Postulante::findOrFail($datos['postulante_id']);
        abort_unless($postulante->revision_estado === 'aprobada', 403,
            'La solicitud aún no ha sido aprobada por el Ejecutivo Comercial de Admisión.');
        abort_if(! $postulante->carrera_externa_id, 422, 'El postulante no tiene una carrera de origen registrada.');

        $malla = $this->engine->mallaDeCarrera((int) $datos['carrera_usil_id']);
        abort_if(! $malla, 422, 'La carrera destino no tiene un plan de estudios (malla) cargado.');

        // Solo se puede convalidar hacia cursos del plan de estudios destino (mismo pool que ve el front).
        $poolIds = array_flip(array_column($this->engine->poolCursosUsil((int) $datos['carrera_usil_id']), 'id'));

        // Solo las filas convalidables llevan destino; regla 1‑a‑1: un curso USIL no se repite.
        $usados = [];
        foreach ($datos['filas'] ?? [] as $i => $f) {
            if (($f['clasificacion'] ?? '') !== 'convalidable') {
                $datos['filas'][$i]['curso_usil_id'] = null;
                continue;
            }
            $cid = $f['curso_usil_id'] ?? null;
            if ($cid) {
                abort_if(! isset($poolIds[$cid]), 422, 'Un curso USIL asignado no pertenece al plan de estudios de la carrera destino.');
                abort_if(isset($usados[$cid]), 422, 'Un curso USIL está asignado más de una vez (regla 1 a 1).');
                $usados[$cid] = true;
            }
        }

        $creditosUsil = CursoUsil::whereIn('id', array_keys($usados))->pluck('creditos', 'id');

        return DB::transaction(function () use ($datos, $postulante, $malla, $creditosUsil, $request, $existente) {
            $atributos = [
                'postulante_id' => $postulante->id,
                'nombres' => $postulante->nombres,
                'apellidos' => trim("{$postulante->apellido_paterno} {$postulante->apellido_materno}"),
                'tipo_documento' => in_array($postulante->tipo_documento, ['DNI', 'CE', 'PASAPORTE']) ? $postulante->tipo_documento : 'DNI',
                'numero_documento' => $postulante->numero_documento,
                'email' => $postulante->email ?: 'sin-correo@usil.edu.pe',
                'telefono' => $postulante->telefono,
                'ciclo_postulacion' => $postulante->ciclo_postulacion ?: '2026-1',
                'carrera_externa_id' => $postulante->carrera_externa_id,
                'carrera_usil_id' => $datos['carrera_usil_id'],
                'malla_usil_id' => $malla->id,
                'metodo' => $datos['metodo'],
                'documento_path' => $datos['documento_path'] ?? null,
                'universidad_origen' => $datos['universidad_origen'] ?? $postulante->institucionOrigen?->nombre,
                'escala_notas' => $datos['escala_notas'] ?? null,
                'nota_minima' => $datos['nota_minima'] ?? null,
                'observaciones' => $datos['observaciones'] ?? null,
                'usuario_id' => $request->user()->id,
            ];

            if ($existente) {
                $existente->update($atributos);
                $existente->detalles()->delete();   // se regenera el detalle
                $sim = $existente;
            } else {
                $sim = Simulacion::create($atributos + ['estado' => 'generada']);
            }

            foreach ($datos['filas'] ?? [] as $f) {
                $cid = $f['curso_usil_id'] ?? null;
                $sim->detalles()->create([
                    'curso_usil_id' => $cid,
                    'curso_externo_id' => $f['curso_externo_id'] ?? null,
                    'curso_origen_nombre' => $f['curso_origen_nombre'],
                    'nota_origen' => $f['nota_origen'] ?? null,
                    'creditos_origen' => $f['creditos_origen'] ?? null,
                    'ciclo_origen' => $f['ciclo_origen'] ?? null,
                    'clasificacion' => $f['clasificacion'],
                    'confianza' => $f['confianza'] ?? null,
                    'creditos_reconocidos' => $cid ? (float) ($creditosUsil[$cid] ?? 0) : 0,
                    'excluido' => false,
                    'origen' => $f['origen'] ?? ($datos['metodo'] === 'ia' ? 'ia' : 'manual'),
                ]);
            }

            return $sim;
        });
    }

    public function show(Simulacion $simulacion)
    {
        $simulacion->load(['detalles.cursoUsil', 'detalles.cursoExterno', 'carreraUsil', 'carreraExterna', 'postulante']);

        return inertia('Simulaciones/Detalle', [
            'simulacion' => [
                'id' => $simulacion->id,
                'estudiante' => "{$simulacion->nombres} {$simulacion->apellidos}",
                'documento' => "{$simulacion->tipo_documento} {$simulacion->numero_documento}",
                'carrera' => $simulacion->carreraUsil?->nombre,
                'origen' => $simulacion->universidad_origen ?: $simulacion->carreraExterna?->nombre,
                'metodo' => $simulacion->metodo,
                'estado' => $simulacion->estado,
                'documento_fuente' => $simulacion->documento_path ? basename($simulacion->documento_path) : null,
                'tiene_pdf' => (bool) $simulacion->pdf_path,
            ],
            'detalles' => $simulacion->detalles->map(fn (SimulacionDetalle $d) => [
                'id' => $d->id,
                'curso_externo' => $d->nombre_origen,
                'nota' => $d->nota_origen,
                'curso_usil' => $d->cursoUsil?->nombre,
                'clasificacion' => $d->clasificacion,
                'confianza' => $d->confianza,
                'creditos' => $d->creditos_reconocidos,
                'excluido' => $d->excluido,
            ]),
            'creditos_total' => $this->service->creditosReconocidos($simulacion),
        ]);
    }

    /** Elimina (lógicamente) una simulación registrando el motivo en la BD. */
    public function destroy(Request $request, Simulacion $simulacion): RedirectResponse
    {
        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:300'],
        ]);

        $simulacion->update(['motivo_eliminacion' => $datos['motivo']]);
        $simulacion->delete();   // soft delete: el registro se conserva

        AuditoriaService::registrar('eliminar', 'simulaciones', $simulacion->id, null, ['motivo' => $datos['motivo']]);

        return back()->with('status', 'Simulación eliminada.');
    }

    /** RF-27: excluir/incluir una fila por excepción. */
    public function toggleDetalle(Simulacion $simulacion, SimulacionDetalle $detalle): RedirectResponse
    {
        abort_unless($detalle->simulacion_id === $simulacion->id, 404);

        $detalle->update(['excluido' => ! $detalle->excluido]);

        return back()->with('status', $detalle->excluido ? 'Curso excluido.' : 'Curso incluido.');
    }

    /** Traduce errores de la IA a un mensaje claro (saturación, clave, etc.). */
    private function mensajeErrorIA(\Throwable $e, string $prefijo): string
    {
        if ($e instanceof RequestException) {
            $status = $e->response?->status();
            if (in_array($status, [429, 500, 502, 503, 529], true)) {
                return 'El servicio de IA está saturado por alta demanda. Espera unos segundos y vuelve a intentar (o cambia de modelo en Configuración).';
            }
            if (in_array($status, [400, 401, 403], true)) {
                return 'La API key de IA no es válida o no tiene acceso. Revísala en Configuración.';
            }
        }

        return "{$prefijo}: {$e->getMessage()}";
    }

    /** Nombre de archivo con apellidos y nombres del postulante. */
    private function nombreArchivo(Simulacion $simulacion, string $ext): string
    {
        $nombre = trim("{$simulacion->apellidos} {$simulacion->nombres}");
        // Quita acentos y caracteres no válidos para nombres de archivo.
        $nombre = Str::ascii($nombre);
        $nombre = preg_replace('/[^A-Za-z0-9 ]/', '', $nombre) ?: "postulante_{$simulacion->id}";

        return "Preconvalidacion - {$nombre}.{$ext}";
    }

    /** Carga las relaciones y datos necesarios para la preconvalidación (PDF/Excel). */
    private function datosPreconvalidacion(Simulacion $simulacion): array
    {
        $simulacion->load([
            'carreraUsil.facultad', 'carreraExterna', 'postulante.institucionOrigen',
            'detalles.cursoUsil.ciclo', 'detalles.cursoExterno',
        ]);

        $convalidados = $simulacion->detalles->filter(fn ($d) => $d->curso_usil_id && ! $d->excluido);
        $noConvalidables = $simulacion->detalles->filter(fn ($d) => $d->clasificacion === 'no_convalidable'
            || (! $d->curso_usil_id && $d->clasificacion === 'convalidable'));
        $desaprobados = $simulacion->detalles->filter(fn ($d) => $d->clasificacion === 'desaprobado');

        return [
            'simulacion' => $simulacion,
            'malla' => MallaCurricular::find($simulacion->malla_usil_id),
            'creditos' => (float) $convalidados->sum('creditos_reconocidos'),
            'convalidados' => $convalidados,
            'noConvalidables' => $noConvalidables,
            'desaprobados' => $desaprobados,
        ];
    }

    /** RF-28/29: generar y descargar el PDF de preconvalidación. */
    public function generarPdf(Simulacion $simulacion)
    {
        // Evita que avisos de PHP 8.5 (p. ej. "null como offset" al cargar
        // relaciones con FK nula) se filtren y corrompan el binario del PDF.
        $nivelPrevio = error_reporting();
        error_reporting($nivelPrevio & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

        try {
            $pdf = Pdf::loadView('pdf.simulacion', $this->datosPreconvalidacion($simulacion));
            $contenido = $pdf->output();
        } finally {
            error_reporting($nivelPrevio);
        }

        $ruta = "simulaciones/preconvalidacion_{$simulacion->id}.pdf";
        Storage::put($ruta, $contenido);
        $simulacion->update(['pdf_path' => $ruta, 'estado' => 'enviada']);

        AuditoriaService::registrar('crear', 'simulaciones', $simulacion->id, null, ['pdf' => $ruta]);

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$this->nombreArchivo($simulacion, 'pdf').'"',
        ]);
    }

    /** Descargar la preconvalidación en Excel. */
    public function exportarExcel(Simulacion $simulacion)
    {
        return Excel::download(
            new PreconvalidacionExport($simulacion),
            $this->nombreArchivo($simulacion, 'xlsx')
        );
    }
}
