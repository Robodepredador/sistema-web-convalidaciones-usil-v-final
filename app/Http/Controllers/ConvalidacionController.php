<?php

namespace App\Http\Controllers;

use App\Models\Configuracion;
use App\Models\Convalidacion;
use App\Models\Simulacion;
use App\Models\SimulacionDetalle;
use App\Services\AlcanceService;
use App\Services\AuditoriaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * CU-06: Confirmar Convalidación. RF-30/31/33 y RF-46/47 (anulación).
 */
class ConvalidacionController extends Controller
{
    /** Responsables del memorándum (configurables en Configuración) con sus valores por defecto. */
    public const MEMO_DEFAULTS = [
        'memo_para_nombre' => 'Erika Valdivieso Lopez',
        'memo_para_cargo' => 'Vicerrectorado Académico',
        'memo_de_nombre' => 'Mag. Enrique Zentner Alva',
        'memo_de_cargo' => 'Director de CPEL - Carreras Universitarias para Personas con Experiencia Laboral',
        'memo_firma_izq_nombre' => 'Mag. Enrique Zentner Alva',
        'memo_firma_izq_cargo' => 'Director Cpel',
        'memo_firma_der_nombre' => 'Even Deyser Perez Rojas',
        'memo_firma_der_cargo' => 'Coordinador de la Carrera Cpel',
        'memo_asunto' => 'Convalidación por Traslado Externo al Programa CPEL',
        'memo_unidad' => 'CPEL-USIL',
    ];

    /** Devuelve los responsables actuales (valor guardado o el por defecto). */
    public static function responsablesMemo(): array
    {
        $r = [];
        foreach (self::MEMO_DEFAULTS as $clave => $def) {
            $r[$clave] = Configuracion::get($clave, $def);
        }

        return $r;
    }

    /**
     * Número del memorándum, en el formato en que se imprime: «0007 - 2026-1 / CPEL-USIL».
     *
     * Se guarda tal cual y el PDF imprime lo guardado. Antes había dos números
     * distintos para el mismo documento —uno en la BD, otro en el papel— y el
     * buscador solo encontraba el primero. La unidad se congela al emitir porque
     * es configurable y no debe reescribir documentos ya firmados.
     */
    public static function numeroMemorandum(int $convalidacionId, ?string $periodo): string
    {
        return str_pad((string) $convalidacionId, 4, '0', STR_PAD_LEFT)
            .' - '.($periodo ?: '—').' / '.Configuracion::get('memo_unidad', self::MEMO_DEFAULTS['memo_unidad']);
    }

    public function index(Request $request)
    {
        // Alcance por rol: convalidaciones cuya simulación es de una carrera visible.
        $visibles = AlcanceService::carrerasVisibles($request->user());

        $q = $request->query('q');
        $estado = $request->query('estado');
        // Se filtra por created_at y no por fecha_confirmacion: esa columna es una
        // fecha sin hora, y la bandeja muestra el instante real de la transacción.
        $desde = $request->query('desde');
        $hasta = $request->query('hasta');

        // --- CONVALIDACIONES CONFIRMADAS / ANULADAS ---
        $convalidacionesQuery = Convalidacion::with([
            'simulacion.carreraUsil',
            'simulacion.postulante.institucionOrigen',
            'simulacion.carreraExterna',
            'simulacion.detalles' => fn ($q) => $q->where('excluido', false)->whereNotNull('curso_usil_id'),
            'simulacion.detalles.cursoUsil',
        ])
            ->when($visibles !== null, fn ($q) => $q->whereHas('simulacion', fn ($s) => $s->whereIn('carrera_usil_id', $visibles ?: [0])))
            ->when($estado, fn ($query) => $query->where('estado', $estado))
            // El grupo es obligatorio: sin él, AND precede a OR y el filtro de
            // alcance de arriba queda anulado en cuanto la coincidencia cae en
            // la rama del memorándum.
            ->when($q, fn ($query) => $query->where(function ($w) use ($q) {
                $w->whereHas('simulacion', function ($sq) use ($q) {
                    $sq->where('nombres', 'like', "%{$q}%")
                        ->orWhere('apellidos', 'like', "%{$q}%")
                        ->orWhere('numero_documento', 'like', "%{$q}%");
                })->orWhere('memorandum_numero', 'like', "%{$q}%");
            }))
            ->when($desde, fn ($query, $v) => $query->whereDate('convalidaciones.created_at', '>=', $v))
            ->when($hasta, fn ($query, $v) => $query->whereDate('convalidaciones.created_at', '<=', $v));

        $convalidaciones = $convalidacionesQuery->orderByDesc('id')
            ->paginate(15, ['*'], 'page')
            ->withQueryString()
            ->through(function (Convalidacion $c) {
                $sim = $c->simulacion;
                $detalles = $sim ? $sim->detalles : collect();

                return [
                    'id' => $c->id,
                    'simulacion_id' => $sim?->id,
                    'estudiante' => $sim ? "{$sim->nombres} {$sim->apellidos}" : '—',
                    'documento' => $sim?->numero_documento,
                    'carrera' => $sim?->carreraUsil?->nombre,
                    'origen' => $sim?->universidad_origen ?? $sim?->postulante?->institucionOrigen?->nombre ?? $sim?->carreraExterna?->nombre,
                    'creditos' => (float) $detalles->sum('creditos_reconocidos'),
                    'convalidados' => $detalles->count(),
                    'memorandum' => $c->memorandum_numero,
                    'fecha' => optional($c->created_at)->format('d/m/Y H:i'),
                    'estado' => $c->estado,
                    'motivo_anulacion' => $c->motivo_anulacion,
                    'pdf_preconv' => $sim ? route('convalidaciones.preconvalidacion.pdf', $sim->id) : null,
                    'excel_preconv' => $sim ? route('convalidaciones.preconvalidacion.excel', $sim->id) : null,
                    'cursos' => $detalles->map(fn ($d) => [
                        'origen' => $d->curso_origen_nombre,
                        'usil' => $d->cursoUsil?->nombre,
                        'creditos' => (float) $d->creditos_reconocidos,
                    ])->values(),
                ];
            });

        // --- PRECONVALIDACIONES (Pendientes de confirmar) ---
        $preconvalidacionesQuery = Simulacion::with([
            'carreraUsil',
            'detalles' => fn ($q) => $q->where('excluido', false)->whereNotNull('curso_usil_id'),
            'detalles.cursoUsil',
        ])
            ->whereDoesntHave('convalidacion')
            ->when($visibles !== null, fn ($q) => $q->whereIn('carrera_usil_id', $visibles ?: [0]))
            // Las preconvalidaciones son, por definición, las pendientes: al
            // filtrar por 'confirmada' o 'anulada' esta sección va vacía.
            ->when($estado && $estado !== 'pendiente', fn ($query) => $query->whereRaw('1 = 0'))
            // Agrupado: si no, el OR anula el filtro de alcance de arriba.
            ->when($q, fn ($query) => $query->where(function ($w) use ($q) {
                $w->where('nombres', 'like', "%{$q}%")
                    ->orWhere('apellidos', 'like', "%{$q}%")
                    ->orWhere('numero_documento', 'like', "%{$q}%");
            }))
            ->when($desde, fn ($query, $v) => $query->whereDate('created_at', '>=', $v))
            ->when($hasta, fn ($query, $v) => $query->whereDate('created_at', '<=', $v));

        $preconvalidaciones = $preconvalidacionesQuery->orderByDesc('id')
            ->paginate(15, ['*'], 'pre')
            ->withQueryString()
            ->through(fn (Simulacion $s) => [
                'id' => $s->id,
                'estudiante' => trim("{$s->nombres} {$s->apellidos}") ?: '—',
                'documento' => $s->numero_documento,
                'carrera' => $s->carreraUsil?->nombre,
                'origen' => $s->universidad_origen,
                'metodo' => $s->metodo,
                'fecha' => optional($s->created_at)->format('d/m/Y H:i'),
                'estado' => 'pendiente', // Explicitly marking as pending since it has no convalidacion
                'convalidados' => $s->detalles->count(),
                'creditos' => (float) $s->detalles->sum('creditos_reconocidos'),
                'pdf' => route('convalidaciones.preconvalidacion.pdf', $s->id),
                'excel' => route('convalidaciones.preconvalidacion.excel', $s->id),
                'cursos' => $s->detalles->map(fn ($d) => [
                    'origen' => $d->curso_origen_nombre,
                    'usil' => $d->cursoUsil?->nombre,
                    'creditos' => (float) $d->creditos_reconocidos,
                ])->values(),
            ]);

        // --- KPIs ---
        $baseSimQuery = Simulacion::when($visibles !== null, fn ($q) => $q->whereIn('carrera_usil_id', $visibles ?: [0]));

        $totalPendientes = (clone $baseSimQuery)->whereDoesntHave('convalidacion')->count();

        $baseConvQuery = Convalidacion::when($visibles !== null, fn ($q) => $q->whereHas('simulacion', fn ($s) => $s->whereIn('carrera_usil_id', $visibles ?: [0])));

        $totalConfirmadas = (clone $baseConvQuery)->where('estado', Convalidacion::CONFIRMADA)->count();
        $totalAnuladas = (clone $baseConvQuery)->where('estado', Convalidacion::ANULADA)->count();

        // Calcular créditos promedio solo de las confirmadas
        $simIdsConfirmadas = (clone $baseConvQuery)->where('estado', Convalidacion::CONFIRMADA)->pluck('simulacion_id');
        $creditosTotales = SimulacionDetalle::whereIn('simulacion_id', $simIdsConfirmadas)
            ->where('excluido', false)
            ->whereNotNull('curso_usil_id')
            ->sum('creditos_reconocidos');

        $creditosPromedio = $totalConfirmadas > 0 ? round($creditosTotales / $totalConfirmadas, 1) : 0;

        return inertia('Convalidaciones/Index', [
            'convalidaciones' => $convalidaciones,
            'preconvalidaciones' => $preconvalidaciones,
            'filtros' => ['q' => $q, 'estado' => $estado, 'desde' => $desde, 'hasta' => $hasta],
            'kpis' => [
                'pendientes' => $totalPendientes,
                'confirmadas' => $totalConfirmadas,
                'anuladas' => $totalAnuladas,
                'creditos_promedio' => $creditosPromedio,
            ],
        ]);
    }

    /** RF-30/31: confirmar la convalidación definitiva (1:1 con la simulación). */
    public function confirmar(Request $request, Simulacion $simulacion): RedirectResponse
    {
        // El memorándum es un acto oficial: solo lo emite quien tiene competencia
        // sobre la carrera (RF-40). Antes, un decano podía firmar el de otra facultad.
        AlcanceService::autorizarCarrera($request->user(), $simulacion->carrera_usil_id);

        if ($simulacion->convalidacion()->exists()) {
            throw ValidationException::withMessages(['simulacion' => 'La simulación ya fue convalidada.']);
        }

        // El memorándum es un documento oficial: no se emite si no reconoce ningún
        // curso. Sin esta guarda se generaban resoluciones con 0 cursos y 0 créditos.
        $convalidables = $simulacion->detalles()
            ->where('clasificacion', 'convalidable')
            ->where('excluido', false)
            ->whereNotNull('curso_usil_id')
            ->count();

        if ($convalidables === 0) {
            throw ValidationException::withMessages([
                'simulacion' => 'No se puede confirmar: la simulación no reconoce ningún curso convalidado.',
            ]);
        }

        $convalidacion = DB::transaction(function () use ($simulacion, $request) {
            $simulacion->update(['estado' => 'aceptada']);

            $c = Convalidacion::create([
                'simulacion_id' => $simulacion->id,
                'fecha_confirmacion' => now()->toDateString(),
                'estado' => Convalidacion::CONFIRMADA,
                'usuario_id' => $request->user()->id,
            ]);
            // El número depende del id, que no existe hasta después del INSERT.
            // Los responsables se congelan aquí: son configurables y no deben
            // reescribir un documento ya emitido si alguien los cambia luego.
            $c->update([
                'memorandum_numero' => self::numeroMemorandum($c->id, $simulacion->ciclo_postulacion),
                'responsables' => self::responsablesMemo(),
            ]);

            return $c;
        });

        // RF-33: generar el memorándum oficial.
        $this->generarMemorandum($convalidacion);

        AuditoriaService::registrar('crear', 'convalidaciones', $convalidacion->id);

        return redirect()->route('convalidaciones.index')->with('status', 'Convalidación confirmada.');
    }

    /** RF-46/47: anular una convalidación confirmada (sin eliminar el registro). */
    public function anular(Request $request, Convalidacion $convalidacion): RedirectResponse
    {
        AlcanceService::autorizarCarrera($request->user(), $convalidacion->simulacion?->carrera_usil_id);

        $request->validate(['motivo' => ['required', 'string', 'max:300']]);

        if ($convalidacion->estado === Convalidacion::ANULADA) {
            return back()->with('status', 'La convalidación ya estaba anulada.');
        }

        $previos = $convalidacion->only(['estado']);

        DB::transaction(function () use ($convalidacion, $request) {
            $convalidacion->update([
                'estado' => Convalidacion::ANULADA,
                'motivo_anulacion' => $request->motivo,
            ]);
            // La simulación deja de estar 'aceptada': el expediente vuelve a ser
            // editable y debe poder confirmarse de nuevo tras corregirlo.
            $convalidacion->simulacion?->update(['estado' => 'generada']);
        });

        // El archivo emitido pasa a llevar el sello de anulación y su motivo:
        // desde aquí la descarga sirve el archivo, no una versión recalculada.
        $this->generarMemorandum($convalidacion);

        AuditoriaService::registrar('editar', 'convalidaciones', $convalidacion->id, $previos, [
            'estado' => Convalidacion::ANULADA, 'motivo' => $request->motivo,
        ]);

        return back()->with('status', 'Convalidación anulada.');
    }

    /**
     * Descarga del memorándum emitido.
     *
     * Sirve el archivo guardado al confirmar, no una versión recalculada: un
     * documento oficial no puede cambiar de contenido entre dos descargas. Solo
     * se regenera si el archivo falta (respaldo restaurado, migración, etc.), y
     * en ese caso se vuelve a guardar para que la siguiente descarga sea la misma.
     */
    public function memorandumPdf(Request $request, Convalidacion $convalidacion)
    {
        AlcanceService::autorizarCarrera($request->user(), $convalidacion->simulacion?->carrera_usil_id);

        if (! $convalidacion->memorandum_pdf_path || ! Storage::exists($convalidacion->memorandum_pdf_path)) {
            $this->generarMemorandum($convalidacion);
        }

        // El número lleva espacios y '/': no sirve tal cual como nombre de archivo.
        $nombre = 'Memorandum_'.preg_replace('/[^A-Za-z0-9._-]+/', '_', (string) $convalidacion->memorandum_numero).'.pdf';

        return Storage::download($convalidacion->memorandum_pdf_path, $nombre);
    }

    private function generarMemorandum(Convalidacion $convalidacion): void
    {
        $contenido = $this->renderMemorandum($convalidacion);
        $ruta = "convalidaciones/memo_{$convalidacion->id}.pdf";
        Storage::put($ruta, $contenido);

        $convalidacion->update(['memorandum_pdf_path' => $ruta]);
    }

    /** Renderiza el memorándum a bytes PDF (silenciando avisos de PHP 8.5). */
    private function renderMemorandum(Convalidacion $convalidacion): string
    {
        $nivelPrevio = error_reporting();
        error_reporting($nivelPrevio & ~E_DEPRECATED & ~E_NOTICE & ~E_WARNING);

        try {
            return Pdf::loadView('pdf.memorandum', $this->datosMemorandum($convalidacion))->output();
        } finally {
            error_reporting($nivelPrevio);
        }
    }

    /** Prepara los datos del memorándum en formato oficial CPEL-USIL. */
    private function datosMemorandum(Convalidacion $convalidacion): array
    {
        $convalidacion->load([
            'simulacion.detalles' => fn ($q) => $q->where('excluido', false)->whereNotNull('curso_usil_id'),
            'simulacion.detalles.cursoUsil.ciclo', 'simulacion.detalles.cursoExterno',
            'simulacion.carreraUsil.facultad', 'simulacion.carreraExterna',
            'simulacion.postulante.institucionOrigen', 'usuario:id,nombre',
        ]);

        // Los del documento emitido; los de Configuración solo para los anteriores
        // a que se empezaran a congelar.
        $resp = $convalidacion->responsables ?: self::responsablesMemo();
        $sim = $convalidacion->simulacion;

        $detalles = $sim
            ? $sim->detalles->sortBy(fn ($d) => $d->cursoUsil?->nombre)->values()
            : collect();

        $periodo = $sim?->ciclo_postulacion ?? '';

        // Evita "Facultad de: Facultad de ..." (el blade ya antepone "Facultad de:").
        $facultad = preg_replace('/^facultad\s+de\s+/i', '', $sim?->carreraUsil?->facultad?->nombre ?? 'Ingeniería');

        return [
            'convalidacion' => $convalidacion,
            'facultad' => $facultad,
            'carrera' => $sim?->carreraUsil?->nombre,
            'estudiante' => mb_strtoupper(trim(($sim?->apellidos ?? '').', '.($sim?->nombres ?? ''))),
            'codigo' => $sim?->postulante?->codigo ?? $sim?->numero_documento,
            'procedencia' => $sim?->universidad_origen
                ?? $sim?->postulante?->institucionOrigen?->nombre
                ?? $sim?->carreraExterna?->nombre,
            'periodo' => $periodo,
            // Lo que se imprime es exactamente lo que se guardó y por lo que se busca.
            'codigoMemo' => $convalidacion->memorandum_numero
                ?: self::numeroMemorandum($convalidacion->id, $periodo),
            'fecha' => $this->fechaLarga($convalidacion->fecha_confirmacion),
            'detalles' => $detalles,
            'total' => (float) $detalles->sum('creditos_reconocidos'),
            'resp' => $resp,
            // Trazabilidad: quién emitió el acto en el sistema.
            'emitidoPor' => $convalidacion->usuario?->nombre,
        ];
    }

    /** Fecha en formato "12 de Marzo 2026". */
    private function fechaLarga($fecha): string
    {
        if (! $fecha) {
            return '';
        }
        $f = $fecha instanceof Carbon ? $fecha : Carbon::parse($fecha);
        $meses = [1 => 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
            'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

        return $f->day.' de '.$meses[$f->month].' '.$f->year;
    }
}
