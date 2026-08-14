<?php

namespace App\Http\Controllers;

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
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * CU-04/05: Simulación de convalidación (manual y con IA).
 *
 * Flujo: se parte de la lista de postulantes; para cada postulante se genera un
 * expediente mapeando sus cursos de origen contra el plan de estudios USIL
 * (mallas → ciclos → cursos_usil). El mapeo se propone por similitud o con IA.
 */
class SimulacionController extends Controller
{
    /**
     * Nota mínima aprobatoria en escala vigesimal (Reglamento de Estudios de
     * Pregrado, Art. 15). Es un piso normativo, no un valor por defecto que el
     * evaluador pueda bajar: un curso desaprobado no se convalida.
     */
    private const ESCALA_VIGESIMAL = '0-20';

    private const NOTA_MINIMA_VIGESIMAL = 11;

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
        $destinos = PostulanteDestino::query()
            ->whereHas('postulante', fn ($p) => $p->where('revision_estado', 'aprobada'))
            ->with(['carrera:id,nombre', 'postulante.institucionOrigen:id,nombre', 'postulante.carreraExterna:id,nombre'])
            ->when($request->q, fn ($qq, $v) => $qq->whereHas('postulante', fn ($w) => $w
                ->where('nombres', 'like', "%$v%")
                ->orWhere('apellido_paterno', 'like', "%$v%")
                ->orWhere('apellido_materno', 'like', "%$v%")
                ->orWhere('numero_documento', 'like', "%$v%")))
            ->when($request->carrera_destino_id, fn ($qq, $v) => $qq->where('carrera_id', $v))
            // Rango por día completo sobre la fecha en que se solicitó el destino.
            ->when($request->desde, fn ($qq, $v) => $qq->whereDate('created_at', '>=', $v))
            ->when($request->hasta, fn ($qq, $v) => $qq->whereDate('created_at', '<=', $v))
            ->when($carrerasPermitidas !== null, fn ($qq) => $qq->whereIn('carrera_id', $carrerasPermitidas))
            ->orderByDesc('id')
            ->paginate(12)->withQueryString();

        // Un solo COUNT agrupado para toda la página. Antes se lanzaba una
        // consulta por fila (N+1: 12 consultas por carga del listado).
        $filas = $destinos->getCollection();
        $conteos = Simulacion::selectRaw("CONCAT(postulante_id, '-', carrera_usil_id) as clave, COUNT(*) as total")
            ->whereIn('postulante_id', $filas->pluck('postulante_id')->unique()->all() ?: [0])
            ->whereIn('carrera_usil_id', $filas->pluck('carrera_id')->unique()->all() ?: [0])
            ->groupBy('postulante_id', 'carrera_usil_id')
            ->pluck('total', 'clave');

        $postulantes = $destinos
            ->through(function (PostulanteDestino $d) use ($conteos) {
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
                    'simulaciones_count' => (int) ($conteos["{$d->postulante_id}-{$d->carrera_id}"] ?? 0),
                    'solicitado' => optional($d->created_at)->format('d/m/Y H:i'),
                ];
            });

        return inertia('Simulaciones/Index', [
            'postulantes' => $postulantes,
            'carreras' => Carrera::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'filtros' => $request->only(['q', 'carrera_destino_id', 'desde', 'hasta']),
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

        AlcanceService::autorizarCarrera($request->user(), $carreraId);

        return inertia('Simulaciones/Simular', $this->propsWorkspace($postulante, null, $carreraId));
    }

    /** Espacio de trabajo para EDITAR una simulación existente. */
    public function editar(Request $request, Simulacion $simulacion)
    {
        AlcanceService::autorizarCarrera($request->user(), $simulacion->carrera_usil_id);
        // Se corta ya en el espacio de trabajo: abrirlo para no poder guardar es peor.
        abort_if($simulacion->estaCerrada(), 422,
            'El expediente tiene una convalidación confirmada; anúlela antes de editar el mapeo.');

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

        // Las opciones pre-autorizadas por el especialista.
        $equivalencias = $carreraDestinoId && $postulante->carrera_externa_id
            ? Equivalencia::with('cursoExterno:id,nombre,creditos')
                ->where('carrera_externa_id', $postulante->carrera_externa_id)
                ->whereHas('cursoUsil.ciclo.malla', fn ($q) => $q->where('carrera_id', $carreraDestinoId))
                ->get()
            : collect([]);

        $opcionesPorUsil = [];
        foreach ($equivalencias as $eq) {
            $opcionesPorUsil[$eq->curso_usil_id][] = $eq->cursoExterno;
        }

        foreach ($pool as &$cursoUsil) {
            $cursoUsil['opciones'] = $opcionesPorUsil[$cursoUsil['id']] ?? [];
        }

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
                    'motivo' => $d->motivo,
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
            'cursosMalla' => $pool,
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
                ->where('estado', '!=', 'borrador')
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

        $carreraId = (int) $datos['carrera_usil_id'];
        AlcanceService::autorizarCarrera($request->user(), $carreraId);

        $pool = $this->engine->poolCursosUsil($carreraId);
        $mapa = $this->engine->asignacionOptima($datos['cursos'] ?? [], $pool);

        return response()->json(['mapa' => $mapa]);
    }

    /**
     * Sirve el archivo del récord académico del postulante en línea (el navegador
     * muestra PDF/imagen y permite descargarlo). Solo lectura del expediente.
     *
     * Contiene datos personales (documento de identidad, notas): se comprueba
     * que el postulante dueño esté dentro del alcance de quien lo pide.
     */
    public function verDocumento(Request $request, PostulanteDocumento $documento)
    {
        $documento->loadMissing('postulante');
        abort_unless($documento->postulante, 404, 'El documento no tiene un postulante asociado.');
        AlcanceService::autorizarPostulante($request->user(), $documento->postulante);

        abort_unless(Storage::exists($documento->ruta), 404, 'El documento no se encuentra en el almacenamiento.');

        return Storage::response($documento->ruta, $documento->nombre_original);
    }

    /** Sugerencia de mapeo semántico con IA. */
    public function sugerirIA(Request $request): JsonResponse
    {
        $datos = $request->validate([
            'carrera_usil_id' => ['required', 'exists:carreras,id'],
            'cursos' => ['array'],
            'cursos.*' => ['string'],
        ]);

        // El pool de cursos de una carrera ajena no se filtra ni siquiera para
        // pedir una sugerencia: el id viaja en el cuerpo de la petición.
        AlcanceService::autorizarCarrera($request->user(), (int) $datos['carrera_usil_id']);

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
            // De quién es el récord que se sube al vuelo: sin saberlo no se puede
            // comprobar su consentimiento de tratamiento de datos.
            'postulante_id' => ['nullable', 'integer', 'exists:postulantes,id'],
        ]);

        // La extracción con IA de un PDF puede tardar más que el límite por defecto.
        @set_time_limit(180);

        // El récord va íntegro al proveedor de IA (nombre, documento y notas), así
        // que el consentimiento se comprueba ANTES que nada: es la base legal del
        // tratamiento (Art. 15 del Reglamento de Admisión, Ley 29733), no un
        // detalle de configuración.
        $doc = $request->filled('documento_id')
            ? PostulanteDocumento::with('postulante')->findOrFail($request->integer('documento_id'))
            : null;

        $dueno = $doc?->postulante ?: ($request->filled('postulante_id')
            ? Postulante::find($request->integer('postulante_id'))
            : null);

        if (! $dueno) {
            return response()->json(['message' => 'Indica de qué postulante es el documento: sin eso no se puede '
                .'comprobar su consentimiento para el tratamiento de datos personales.'], 422);
        }

        // El alcance se comprueba ANTES que el consentimiento: `documento_id`
        // llega por el cuerpo de la petición, así que sin esto un evaluador
        // restringido a una carrera podía recorrer identificadores y extraer el
        // récord íntegro —nombre, documento y notas— de cualquier postulante del
        // sistema, y de paso enviarlo al proveedor de IA. Es la misma puerta que
        // `verDocumento()` ya cerraba; aquí faltaba.
        AlcanceService::autorizarPostulante($request->user(), $dueno);

        if (! $dueno->tieneConsentimientoDatos()) {
            return response()->json(['message' => 'El postulante no tiene registrado su consentimiento para el '
                .'tratamiento de datos personales. Regístralo en su expediente antes de usar la extracción automática, '
                .'o transcribe los cursos a mano.'], 422);
        }

        if (! $this->ia->disponible()) {
            return response()->json(['message' => 'IA no configurada. Ve a Configuración y define la API key.'], 422);
        }

        // Catálogo real de la institución (para completar/normalizar nombres extraídos).
        $carreraExternaId = $request->integer('carrera_externa_id') ?: null;

        // 1) Documento existente del postulante (fuente principal).
        if ($doc) {
            if (! Storage::exists($doc->ruta)) {
                return response()->json(['message' => 'El documento del postulante no se encuentra en el almacenamiento.'], 404);
            }
            $contenido = Storage::get($doc->ruta);
            $nombre = $doc->nombre_original;
            $rutaTrazabilidad = $doc->ruta;
            $carreraExternaId = $carreraExternaId ?: $doc->postulante?->carrera_externa_id;
        } elseif ($request->hasFile('documento')) {
            // 2) Subida puntual (alternativa), ya con el consentimiento verificado.
            $archivo = $request->file('documento');
            $contenido = file_get_contents($archivo->getRealPath());
            $nombre = $archivo->getClientOriginalName();
            $rutaTrazabilidad = null;
        } else {
            return response()->json(['message' => 'Selecciona un documento del postulante o sube uno.'], 422);
        }

        try {
            $notaMinima = $request->input('nota_minima', 11);
            $escala = $request->input('escala', '0-20');
            $extraccion = $this->ia->extraerCursos($contenido, $nombre, $notaMinima, $escala);
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

        // Todo curso aprobado llega al especialista para que decida el mapeo: ya
        // no hay una política aparte que lo descarte de antemano por su nombre.
        $aprobados = array_map($normalizar, $extraccion['aprobados']);
        $desaprobados = array_map($normalizar, $extraccion['desaprobados']);

        return response()->json([
            'estudiante' => $extraccion['estudiante'],
            'institucion' => $extraccion['institucion'],
            'aprobados' => $aprobados,
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

    /**
     * Un expediente con convalidación vigente sustenta un memorándum ya emitido:
     * su detalle no se toca. Sin esta guarda, el contenido del acto oficial
     * cambiaba después de firmado, conservando su número.
     */
    private function abortSiCerrada(Simulacion $simulacion, string $accion): void
    {
        if ($simulacion->estaCerrada()) {
            throw ValidationException::withMessages([
                'simulacion' => "No se puede {$accion}: el expediente tiene una convalidación confirmada. Anúlela primero.",
            ]);
        }
    }

    /** Actualiza una simulación existente y su detalle. */
    public function update(Request $request, Simulacion $simulacion): RedirectResponse|JsonResponse
    {
        AlcanceService::autorizarCarrera($request->user(), $simulacion->carrera_usil_id);
        $this->abortSiCerrada($simulacion, 'modificar el mapeo');

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
            'filas.*.curso_origen_nombre' => ['nullable', 'string', 'max:200'],
            'filas.*.curso_externo_id' => ['nullable', 'integer'],
            'filas.*.curso_usil_id' => ['required', 'integer', 'exists:cursos_usil,id'],
            'filas.*.nota_origen' => ['nullable', 'string', 'max:20'],
            'filas.*.creditos_origen' => ['nullable', 'numeric'],
            'filas.*.ciclo_origen' => ['nullable', 'string', 'max:30'],
            'filas.*.clasificacion' => ['nullable', 'in:convalidable,desaprobado,no_convalidable'],
            'filas.*.motivo' => ['nullable', 'string', 'max:300'],
            'filas.*.confianza' => ['nullable', 'numeric'],
            'filas.*.origen' => ['nullable', 'in:automatico,manual,ia,similitud'],
        ]);

        // La carrera destino debe estar dentro del alcance del evaluador (RF-40):
        // sin esto, el id llega por el cuerpo de la petición y salta el filtro.
        AlcanceService::autorizarCarrera($request->user(), (int) $datos['carrera_usil_id']);

        $postulante = Postulante::findOrFail($datos['postulante_id']);
        abort_unless($postulante->revision_estado === 'aprobada', 403,
            'La solicitud aún no ha sido aprobada por el Ejecutivo Comercial de Admisión.');
        abort_if(! $postulante->carrera_externa_id, 422, 'El postulante no tiene una carrera de origen registrada.');

        $malla = $this->engine->mallaDeCarrera((int) $datos['carrera_usil_id']);
        abort_if(! $malla, 422, 'La carrera destino no tiene un plan de estudios (malla) cargado.');

        // Solo se puede convalidar hacia cursos del plan de estudios destino (mismo pool que ve el front).
        $poolIds = array_flip(array_column($this->engine->poolCursosUsil((int) $datos['carrera_usil_id']), 'id'));

        // Las equivalencias autorizadas
        $equivalencias = Equivalencia::where('carrera_externa_id', $postulante->carrera_externa_id)
            ->whereHas('cursoUsil.ciclo.malla', fn ($q) => $q->where('carrera_id', $datos['carrera_usil_id']))
            ->get()
            ->groupBy('curso_usil_id');

        $notaMinima = $this->notaMinimaAplicable($datos);

        $usados = [];
        foreach ($datos['filas'] ?? [] as $i => $f) {
            $cid = $f['curso_usil_id'];

            abort_if(! isset($poolIds[$cid]), 422, 'Un curso USIL asignado no pertenece al plan de estudios de la carrera destino.');
            abort_if(isset($usados[$cid]), 422, 'Un curso USIL está asignado más de una vez (regla 1 a 1).');
            $usados[$cid] = true;

            $extId = $f['curso_externo_id'] ?? null;
            if ($extId) {
                // Verificar que está autorizado por el especialista
                $autorizados = $equivalencias->get($cid)?->pluck('curso_externo_id')->toArray() ?? [];
                abort_unless(in_array((int) $extId, $autorizados, true), 422, 'El curso externo seleccionado no está autorizado para este curso USIL.');

                $datos['filas'][$i]['clasificacion'] = 'convalidable';

                $nota = $this->notaNumerica($f['nota_origen'] ?? null);
                if ($nota !== null && $nota < $notaMinima) {
                    abort(422, "«{$f['curso_origen_nombre']}» tiene nota {$f['nota_origen']}, por debajo de la mínima "
                        ."aprobatoria ({$notaMinima}). Un curso desaprobado no se convalida.");
                }
            } else {
                $datos['filas'][$i]['clasificacion'] = 'no_convalidable';
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
                'estado' => $existente ? $existente->estado : 'borrador',
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
                $cid = $f['curso_usil_id'];
                $sim->detalles()->create([
                    'curso_usil_id' => $cid,
                    'curso_externo_id' => $f['curso_externo_id'] ?? null,
                    'curso_origen_nombre' => $f['curso_origen_nombre'] ?? '',
                    'nota_origen' => $f['nota_origen'] ?? null,
                    'creditos_origen' => $f['creditos_origen'] ?? null,
                    'ciclo_origen' => $f['ciclo_origen'] ?? null,
                    'clasificacion' => $f['clasificacion'],
                    // Si no convalida, no hay un motivo que el usuario ingrese ahora, pero podemos dejar null
                    'motivo' => $f['motivo'] ?? null,
                    'confianza' => $f['confianza'] ?? null,
                    'creditos_reconocidos' => $f['curso_externo_id'] ? (float) ($creditosUsil[$cid] ?? 0) : 0,
                    'excluido' => false,
                    'origen' => $f['origen'] ?? ($datos['metodo'] === 'ia' ? 'ia' : 'manual'),
                ]);
            }

            return $sim;
        });
    }

    /**
     * Nota mínima que rige esta simulación.
     *
     * En escala vigesimal el reglamento fija 11 y el evaluador no puede bajar de
     * ahí (sí subirlo: una carrera puede exigir más). En otras escalas manda lo
     * que declare la simulación, porque el sistema no sabe traducirlas.
     */
    private function notaMinimaAplicable(array $datos): float
    {
        $escala = $datos['escala_notas'] ?? self::ESCALA_VIGESIMAL;
        $declarada = $datos['nota_minima'] ?? null;

        if ($escala !== self::ESCALA_VIGESIMAL) {
            return (float) ($declarada ?? 0);
        }

        // Se ha flexibilizado la nota mínima para permitir el redondeo (e.g. 10.9)
        return (float) ($declarada ?? self::NOTA_MINIMA_VIGESIMAL);
    }

    /** La nota de origen como número, o null si no lo es («APROBADO», «A», vacío). */
    private function notaNumerica(?string $nota): ?float
    {
        $n = str_replace(',', '.', trim((string) $nota));

        return is_numeric($n) ? (float) $n : null;
    }

    public function show(Request $request, Simulacion $simulacion)
    {
        AlcanceService::autorizarCarrera($request->user(), $simulacion->carrera_usil_id);

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
                // Cerrado por convalidación vigente: la pantalla no ofrece editarlo.
                'convalidada' => $simulacion->estaCerrada(),
            ],
            'detalles' => $simulacion->detalles->map(fn (SimulacionDetalle $d) => [
                'id' => $d->id,
                'curso_externo' => $d->nombre_origen,
                'nota' => $d->nota_origen,
                'curso_usil' => $d->cursoUsil?->nombre,
                'clasificacion' => $d->clasificacion,
                'motivo' => $d->motivo,
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
        AlcanceService::autorizarCarrera($request->user(), $simulacion->carrera_usil_id);

        $datos = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:300'],
        ]);

        // Una simulación con convalidación vigente es el sustento de un memorándum
        // en vigor: eliminarla dejaba la resolución sin respaldo. Anular primero.
        if ($simulacion->tieneConvalidacionVigente()) {
            throw ValidationException::withMessages([
                'simulacion' => 'No se puede eliminar: tiene una convalidación confirmada. Anúlela primero.',
            ]);
        }

        $simulacion->update(['motivo_eliminacion' => $datos['motivo']]);
        $simulacion->delete();   // soft delete: el registro se conserva

        AuditoriaService::registrar('eliminar', 'simulaciones', $simulacion->id, null, ['motivo' => $datos['motivo']]);

        return back()->with('status', 'Simulación eliminada.');
    }

    /**
     * RF-27: excluir/incluir una fila por excepción.
     *
     * Cambia los créditos que reconoce el expediente, así que es una decisión
     * académica: exige motivo y queda en la traza de auditoría.
     */
    public function toggleDetalle(Request $request, Simulacion $simulacion, SimulacionDetalle $detalle): RedirectResponse
    {
        AlcanceService::autorizarCarrera($request->user(), $simulacion->carrera_usil_id);
        abort_unless($detalle->simulacion_id === $simulacion->id, 404);
        $this->abortSiCerrada($simulacion, 'cambiar la exclusión de un curso');

        $datos = $request->validate(['motivo' => ['required', 'string', 'min:5', 'max:300']]);

        $detalle->update(['excluido' => ! $detalle->excluido]);

        AuditoriaService::registrar('editar', 'simulacion_detalle', $detalle->id,
            ['excluido' => ! $detalle->excluido],
            ['excluido' => $detalle->excluido, 'motivo' => $datos['motivo'], 'simulacion_id' => $simulacion->id]);

        return back()->with('status', $detalle->excluido ? 'Curso excluido.' : 'Curso incluido.');
    }

    /**
     * RF-46: Validar una simulación generada, marcándola como aceptada.
     */
    public function validar(Request $request, Simulacion $simulacion): RedirectResponse
    {
        AlcanceService::autorizarCarrera($request->user(), $simulacion->carrera_usil_id);
        $this->abortSiCerrada($simulacion, 'validar la simulación');

        $simulacion->update(['estado' => 'aceptada']);

        AuditoriaService::registrar('editar', 'simulaciones', $simulacion->id,
            ['estado' => 'aceptada'],
            ['estado' => $simulacion->getOriginal('estado')]);

        return back()->with('status', 'Simulación validada.');
    }

    /**
     * Guarda la simulación como borrador (pasa de temporal a la lista de trabajo).
     */
    public function guardarBorrador(Request $request, Simulacion $simulacion): RedirectResponse
    {
        AlcanceService::autorizarCarrera($request->user(), $simulacion->carrera_usil_id);

        if ($simulacion->estado === 'borrador') {
            $simulacion->update(['estado' => 'generada']);
        }

        return back()->with('status', 'Simulación guardada en el historial de trabajo.');
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

        // El mensaje crudo puede traer rutas del servidor o fragmentos de la
        // respuesta del proveedor: va al log, no a la pantalla del evaluador.
        Log::error($prefijo, ['excepcion' => $e]);

        return "{$prefijo}. Vuelve a intentarlo; si persiste, avisa a soporte con la hora del intento.";
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

    /**
     * Alcance de LECTURA de una simulación concreta.
     *
     * Usa el usuario autenticado en vez de recibir la Request porque
     * PostulanteController reutiliza generarPdf()/exportarExcel() internamente:
     * así la comprobación se aplica por las dos rutas de entrada.
     */
    private function autorizarLectura(Simulacion $simulacion): void
    {
        $user = auth()->user();
        abort_unless($user, 403, 'No autenticado.');
        AlcanceService::autorizarCarrera($user, $simulacion->carrera_usil_id);
    }

    /**
     * RF-28/29: descargar el PDF de preconvalidación.
     *
     * Es una lectura: no cambia el estado de la simulación ni escribe en disco.
     * Antes ponía `estado = 'enviada'` en cada descarga, con lo que un perfil de
     * solo lectura (Auditor) alteraba el expediente al consultarlo y el estado
     * dejaba de significar "enviada al postulante".
     */
    public function generarPdf(Simulacion $simulacion)
    {
        $this->autorizarLectura($simulacion);

        return $this->renderPdf($simulacion);
    }

    /**
     * Renderiza el PDF de preconvalidación SIN comprobar permisos.
     *
     * No está enrutado: quien lo llama autoriza antes. Hoy solo lo hace el
     * personal, filtrado por alcance de carrera (autorizarLectura). El portal
     * del postulante lo usaba con su propio criterio de propiedad; dejó de
     * hacerlo cuando la entrega del documento oficial salió del sistema.
     */
    private function renderPdf(Simulacion $simulacion, string $disposition = 'attachment')
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

        return response($contenido, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$this->nombreArchivo($simulacion, 'pdf').'"',
        ]);
    }

    /**
     * Descargar la preconvalidación en Excel, sobre la plantilla institucional
     * `storage/app/plantillas/formato_simulacion.xltx`.
     *
     * La plantilla trae dos hojas y solo se rellena una:
     *
     *   - `PRECONVA` (A: Curso USIL · B: Curso Convalidado) es la ENTRADA. Son
     *     dos columnas a propósito: no es un formato incompleto.
     *   - `Export` lleva la malla USIL con sus códigos de ERP y resuelve el curso
     *     de origen con `=VLOOKUP(D, BASE, 2, 0)`, donde `BASE` es
     *     `PRECONVA!$A$1:$B$155`. Escribiendo PRECONVA, el ERP se completa solo.
     *
     * Eso limita el listado a 154 cursos: por encima, las filas se escriben pero
     * quedan fuera de `BASE` y el VLOOKUP no las encuentra. Una preconvalidación
     * real ronda los 20-40, así que no se corta el archivo por ello; si algún día
     * se acerca al límite, hay que ampliar el rango en la plantilla.
     */
    public function exportarExcel(Simulacion $simulacion)
    {
        $this->autorizarLectura($simulacion);

        $plantilla = storage_path('app/plantillas/formato_simulacion.xltx');
        abort_unless(is_file($plantilla), 500,
            'Falta la plantilla de Excel (storage/app/plantillas/formato_simulacion.xltx).');

        $simulacion->load(['detalles.cursoUsil', 'detalles.cursoExterno']);

        $mapaEquivalencias = [];
        foreach ($simulacion->detalles as $detalle) {
            if (! $detalle->curso_usil_id || $detalle->excluido) {
                continue;
            }
            $clave = mb_strtolower(trim($detalle->cursoUsil?->nombre ?? ''));
            $mapaEquivalencias[$clave] = $detalle->nombre_origen;
        }

        $libro = IOFactory::load($plantilla);
        $hojaExport = $libro->getSheetByName('Export') ?: $libro->getActiveSheet();

        // Escribir directamente en la columna E de 'Export' para evitar los #N/D
        $fila = 2;
        while (true) {
            $cursoUsil = $hojaExport->getCell('D'.$fila)->getValue();
            if (empty($cursoUsil)) {
                break; // fin de la lista de cursos en la malla de la plantilla
            }

            $claveBusqueda = mb_strtolower(trim($cursoUsil));

            if (isset($mapaEquivalencias[$claveBusqueda])) {
                $hojaExport->setCellValue('E'.$fila, $mapaEquivalencias[$claveBusqueda]);
            } else {
                // Eliminar la fórmula y dejar en blanco para que no salga #N/D
                $hojaExport->setCellValue('E'.$fila, '');
            }
            $fila++;
        }

        // Eliminar la hoja PRECONVA ya que ahora mapeamos directo
        $indicePreconva = $libro->getIndex($libro->getSheetByName('PRECONVA'));
        if ($indicePreconva !== null) {
            $libro->removeSheetByIndex($indicePreconva);
        }

        // Nombre irrepetible: dos descargas simultáneas del mismo expediente
        // escribían el mismo archivo y una se llevaba el contenido de la otra.
        $directorio = storage_path('app/temp');
        if (! is_dir($directorio)) {
            mkdir($directorio, 0775, true);
        }
        $this->limpiarTemporales($directorio);
        $temporal = $directorio.'/'.Str::uuid()->toString().'.xlsx';

        try {
            (new Xlsx($libro))->save($temporal);
        } catch (\Throwable $e) {
            // `deleteFileAfterSend` solo limpia si el envío llega a ocurrir.
            if (is_file($temporal)) {
                unlink($temporal);
            }
            throw $e;
        }

        return response()
            ->download($temporal, $this->nombreArchivo($simulacion, 'xlsx'))
            ->deleteFileAfterSend(true);
    }

    public function exportarExcelOficial(Simulacion $simulacion)
    {
        $plantilla = storage_path('app/plantillas/plantilla_preconvalidacion_oficial.xlsx');
        abort_unless(is_file($plantilla), 500,
            'Falta la plantilla de Excel Oficial (storage/app/plantillas/plantilla_preconvalidacion_oficial.xlsx).');

        $simulacion->load([
            'postulante.carreraDestino.facultad',
            'postulante.institucionOrigen',
            'postulante.carreraExterna',
            'detalles.cursoUsil.ciclo',
            'detalles.cursoExterno',
        ]);

        $postulante = $simulacion->postulante;
        $libro = IOFactory::load($plantilla);

        // Hoja 1: Preconvalidación
        $hojaPreconva = $libro->getSheetByName('Preconvalidación') ?: $libro->getSheet(0);

        if ($postulante->carreraDestino && $postulante->carreraDestino->facultad) {
            $hojaPreconva->setCellValue('A1', $postulante->carreraDestino->facultad->nombre);
        }
        if ($postulante->carreraDestino) {
            $hojaPreconva->setCellValue('A2', 'Carrera Profesional: '.$postulante->carreraDestino->nombre);
        }

        $nombreCompleto = trim($postulante->nombres.' '.$postulante->apellido_paterno.' '.$postulante->apellido_materno);
        $hojaPreconva->setCellValue('C4', $nombreCompleto);
        $hojaPreconva->setCellValue('C5', $postulante->codigo ?? '');
        $hojaPreconva->setCellValue('C6', $postulante->ciclo_postulacion ?? '');
        $hojaPreconva->setCellValue('C7', $postulante->institucionOrigen?->nombre ?? '');
        $hojaPreconva->setCellValue('C8', $postulante->carreraExterna?->nombre ?? '');
        $hojaPreconva->setCellValue('C9', $simulacion->created_at ? $simulacion->created_at->format('d/m/Y') : date('d/m/Y'));

        // Cursos convalidados
        $filaPreconva = 11;
        foreach ($simulacion->detalles as $detalle) {
            if ($detalle->curso_usil_id && ! $detalle->excluido) {
                $hojaPreconva->setCellValue('A'.$filaPreconva, $detalle->cursoUsil?->ciclo?->numero ?? '');
                $hojaPreconva->setCellValue('B'.$filaPreconva, $detalle->cursoUsil?->nombre ?? '');
                $hojaPreconva->setCellValue('C'.$filaPreconva, mb_strtoupper($detalle->nombre_origen ?? ''));
                $hojaPreconva->setCellValue('D'.$filaPreconva, $detalle->cursoUsil?->creditos ?? '');
                $filaPreconva++;
            }
        }

        // Hoja 2: Cursos no convalidados
        $hojaNoConva = $libro->getSheetByName('Cursos no convalidados') ?: $libro->getSheet(1);
        $filaNoConva = 3;
        foreach ($simulacion->detalles as $detalle) {
            if (! $detalle->curso_usil_id || $detalle->excluido) {
                $hojaNoConva->setCellValue('A'.$filaNoConva, mb_strtoupper($detalle->nombre_origen ?? ''));
                $hojaNoConva->setCellValue('B'.$filaNoConva, $detalle->nota_origen ?? '');
                $hojaNoConva->setCellValue('C'.$filaNoConva, $detalle->creditos_origen ?? '');
                $hojaNoConva->setCellValue('D'.$filaNoConva, 'No convalidable');
                $filaNoConva++;
            }
        }

        $directorio = storage_path('app/temp');
        if (! is_dir($directorio)) {
            mkdir($directorio, 0775, true);
        }
        $this->limpiarTemporales($directorio);
        $temporal = $directorio.'/'.Str::uuid()->toString().'.xlsx';

        try {
            (new Xlsx($libro))->save($temporal);
        } catch (\Throwable $e) {
            if (is_file($temporal)) {
                unlink($temporal);
            }
            throw $e;
        }

        $nom = Str::slug($postulante->nombres.'_'.$postulante->apellido_paterno);
        $nombreFinal = "Preconvalidacion_Oficial_{$simulacion->id}_{$nom}.xlsx";

        return response()
            ->download($temporal, $nombreFinal)
            ->deleteFileAfterSend(true);
    }

    /**
     * Barre los Excel temporales de más de una hora.
     *
     * `deleteFileAfterSend` solo borra si el envío llega a completarse: una
     * descarga que el usuario cancela, o un error a media respuesta, deja el
     * archivo ahí. Sin esto el directorio crece sin límite y nadie lo mira hasta
     * que el disco se llena.
     *
     * ponytail: barrido oportunista al descargar, sin tarea programada. Si algún
     * día el volumen lo pide, esto se mueve a un comando y al cron.
     */
    private function limpiarTemporales(string $directorio): void
    {
        $limite = now()->subHour()->getTimestamp();

        foreach (glob($directorio.'/*.xlsx') ?: [] as $archivo) {
            if (@filemtime($archivo) < $limite) {
                @unlink($archivo);
            }
        }
    }
}
