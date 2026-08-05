<?php

namespace App\Http\Controllers;

use App\Mail\AccesoPortalMail;
use App\Models\Carrera;
use App\Models\InstitucionExterna;
use App\Models\Postulante;
use App\Models\Role;
use App\Models\Simulacion;
use App\Models\User;
use App\Services\AlcanceService;
use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Gestión de Postulantes (solicitantes de convalidación por traslado externo).
 * Registro mediante asistente de 6 pasos, con guardado de borrador y documentos.
 */
class PostulanteController extends Controller
{
    /**
     * Estados del expediente. 'borrador' lo fija el propio guardado (no es un
     * destino manual) y sale de él al guardarse como registro definitivo.
     */
    private const ESTADOS = ['borrador', 'nuevo', 'en_evaluacion', 'admitido', 'rechazado', 'matriculado'];

    /**
     * Transiciones admitidas del expediente. Antes el endpoint aceptaba
     * cualquier destino: se podía marcar 'matriculado' a alguien recién
     * registrado, sin revisión ni evaluación.
     *
     * 'en_evaluacion' no está como destino manual a propósito: se llega ahí
     * aprobando la revisión, no fijándolo a mano. 'matriculado' exige haber sido
     * admitido, y 'rechazado' se puede declarar en cualquier punto salvo cuando
     * el postulante ya está matriculado.
     */
    private const TRANSICIONES = [
        'borrador' => ['rechazado'],
        'nuevo' => ['rechazado'],
        'en_evaluacion' => ['admitido', 'rechazado'],
        'admitido' => ['matriculado', 'rechazado'],
        'rechazado' => ['nuevo'],
        'matriculado' => [],
    ];

    /**
     * Expediente documental de la modalidad de traslado externo, con la etiqueta
     * que ve el postulante. El nombre importa: «constancia de matrícula» no es
     * lo que pide el proceso —es la de PRIMERA matrícula— y faltaban la copia
     * del documento de identidad y la solicitud en formato USIL.
     */
    private const DOCUMENTOS = [
        'dni' => 'Copia del documento de identidad',
        'certificado' => 'Certificado oficial de notas (SUNEDU)',
        'silabos' => 'Sílabos sellados y visados por la institución de procedencia',
        'constancia' => 'Constancia de primera matrícula',
        'solicitud' => 'Solicitud de convalidación (formato USIL)',
    ];

    /**
     * Formato del número de documento según su tipo, con el mensaje que se muestra.
     * Espejo de DOCUMENTOS_REGLAS en resources/js/Pages/Postulantes/Form.vue.
     */
    private const REGLAS_DOCUMENTO = [
        'DNI' => ['/^\d{8}$/', 'El DNI debe tener exactamente 8 dígitos.'],
        'CE' => ['/^[A-Za-z0-9]{9,12}$/', 'El carné de extranjería debe tener de 9 a 12 caracteres alfanuméricos.'],
        'PASAPORTE' => ['/^[A-Za-z0-9]{6,12}$/', 'El pasaporte debe tener de 6 a 12 caracteres alfanuméricos.'],
        'PTP' => ['/^[A-Za-z0-9]{9,12}$/', 'El PTP debe tener de 9 a 12 caracteres alfanuméricos.'],
    ];

    /** Nombres propios y topónimos: letras con acentos, espacios, apóstrofo, punto y guion. */
    private const RE_NOMBRE = '/^[\p{L}\p{M}\s\'’.\-]+$/u';

    // ponytail: el teléfono solo valida juego de caracteres y longitud; "------" pasaría.
    // Si algún día hay que llamar a esos números, normalizar a E.164 antes de guardar.
    private const RE_TELEFONO = '/^[0-9+()\s-]{6,20}$/';

    public function index(Request $request)
    {
        // Alcance por rol: solo postulantes con un destino dentro de las carreras visibles.
        $visibles = AlcanceService::carrerasVisibles($request->user());

        $postulantes = Postulante::with(['carreraDestino', 'institucionOrigen', 'usuario:id,nombre'])
            // Estado de preconvalidación derivado de las simulaciones/convalidaciones reales
            // (así Admisión ve si su solicitud ya fue atendida, sin tocar el estado manual).
            ->withCount([
                'simulaciones',
                'simulaciones as convalidaciones_count' => fn ($q) => $q->whereHas('convalidacion'),
                'documentos',
            ])
            ->when($visibles !== null, fn ($x) => $x->whereHas('destinos',
                fn ($d) => $d->whereIn('carrera_id', $visibles ?: [0])))
            ->when($request->q, fn ($x, $v) => $x->where(fn ($w) => $w->where('nombres', 'like', "%{$v}%")
                ->orWhere('apellido_paterno', 'like', "%{$v}%")
                ->orWhere('apellido_materno', 'like', "%{$v}%")
                ->orWhere('numero_documento', 'like', "%{$v}%")
                ->orWhere('codigo', 'like', "%{$v}%")
                ->orWhere('email', 'like', "%{$v}%")))
            ->when($request->estado, fn ($x, $v) => $x->where('estado', $v))
            ->when($request->revision, fn ($x, $v) => $x->where('revision_estado', $v))
            ->when($request->carrera_destino_id, fn ($x, $v) => $x->where('carrera_destino_id', $v))
            // Rango por día completo sobre la fecha de registro (whereDate ignora la hora).
            ->when($request->desde, fn ($x, $v) => $x->whereDate('created_at', '>=', $v))
            ->when($request->hasta, fn ($x, $v) => $x->whereDate('created_at', '<=', $v))
            // El Asesor solo ve los postulantes que él registró.
            ->when($request->user()->rol?->nombre === Role::ASESOR,
                fn ($x) => $x->where('usuario_id', $request->user()->id))
            ->orderByDesc('id')
            ->paginate(10)->withQueryString()
            ->through(fn (Postulante $p) => [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'documento' => "{$p->tipo_documento} {$p->numero_documento}",
                'nombre' => $p->nombre_completo,
                'email' => $p->email,
                'carrera_destino' => $p->carreraDestino?->nombre,
                'procedencia' => $p->institucionOrigen?->nombre,
                'estado' => $p->estado,
                'preconvalidacion' => $p->convalidaciones_count > 0 ? 'convalidada'
                    : ($p->simulaciones_count > 0 ? 'atendida' : 'pendiente'),
                'revision' => $p->revision_estado,
                'asesor' => $p->usuario?->nombre,
                'documentos' => $p->documentos_count,
                'documentos_total' => count(self::DOCUMENTOS),
                'registrado' => optional($p->created_at)->format('d/m/Y H:i'),
            ]);

        return inertia('Postulantes/Index', [
            'postulantes' => $postulantes,
            // El Asesor solo cuenta los suyos (coherente con su listado).
            'total' => Postulante::when($request->user()->rol?->nombre === Role::ASESOR,
                fn ($q) => $q->where('usuario_id', $request->user()->id))->count(),
            'carreras' => Carrera::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'estados' => self::ESTADOS,
            'revisiones' => ['pendiente', 'aprobada', 'observada'],
            'filtros' => $request->only(['q', 'estado', 'revision', 'carrera_destino_id', 'desde', 'hasta']),
        ]);
    }

    public function create()
    {
        return inertia('Postulantes/Form', $this->opciones() + ['postulante' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $borrador = $request->boolean('borrador');
        $datos = $this->validar($request, null, $borrador);
        $destinoIds = $this->extraerDestinos($datos);

        $siguiente = (Postulante::withTrashed()->max('id') ?? 0) + 1;

        // Postulante sin documento → identificador temporal único.
        if ($request->boolean('sin_documento')) {
            $datos['tipo_documento'] = 'TEMP';
            $datos['numero_documento'] = 'TMP-'.now()->year.'-'.str_pad((string) $siguiente, 5, '0', STR_PAD_LEFT);
        }

        $datos['codigo'] = 'POST-'.now()->year.'-'.str_pad((string) $siguiente, 5, '0', STR_PAD_LEFT);
        $datos['usuario_id'] = $request->user()->id;
        $datos['estado'] = $borrador ? 'borrador' : 'nuevo';

        // Acceso al portal solo si hay correo.
        $temporal = null;
        if (! empty($datos['email'])) {
            $temporal = Str::password(10);
            $datos['password_hash'] = Hash::make($temporal);
            $datos['acceso_habilitado'] = true;
            $datos['debe_cambiar_password'] = true;
        }

        $postulante = Postulante::create($datos);
        $this->guardarDocumentos($request, $postulante);
        $this->syncDestinos($postulante, $destinoIds);

        if ($temporal) {
            $this->enviarAcceso($postulante, $temporal);
        }

        AuditoriaService::registrar('crear', 'postulantes', $postulante->id, null, ['documento' => $postulante->numero_documento]);

        $msg = $borrador
            ? "Borrador guardado ({$postulante->codigo})."
            : "Postulante registrado ({$postulante->codigo}).";
        if ($temporal) {
            $msg .= " Se enviaron las credenciales a {$postulante->email}.".$this->pistaLocal($temporal);
        }

        return redirect()->route('postulantes.index')->with('status', $msg);
    }

    public function edit(Request $request, Postulante $postulante)
    {
        $this->autorizarPropiedad($request->user(), $postulante);

        $postulante->load([
            'documentos',
            'simulaciones.carreraUsil',
            'simulaciones.convalidacion',
            'simulaciones.detalles' => fn ($q) => $q->where('excluido', false)->whereNotNull('curso_usil_id'),
            'simulaciones.detalles.cursoUsil',
        ]);

        // Resultado de la evaluación del coordinador (solo lectura) para que Admisión lo consulte.
        $preconvalidaciones = $postulante->simulaciones->sortByDesc('id')->map(fn (Simulacion $s) => [
            'id' => $s->id,
            'carrera' => $s->carreraUsil?->nombre,
            'metodo' => $s->metodo,
            'estado' => $s->estado,
            'fecha' => optional($s->created_at)->format('d/m/Y H:i'),
            'convalidados' => $s->detalles->count(),
            'creditos' => (float) $s->detalles->sum('creditos_reconocidos'),
            'cursos' => $s->detalles->map(fn ($d) => [
                'origen' => $d->curso_origen_nombre,
                'nota' => $d->nota_origen,
                'usil' => $d->cursoUsil?->nombre,
                'creditos' => (float) $d->creditos_reconocidos,
            ])->values(),
            'convalidada' => (bool) $s->convalidacion,
            'memorandum' => $s->convalidacion?->memorandum_numero,
            'pdf' => route('postulantes.preconvalidacion.pdf', [$postulante->id, $s->id]),
            'excel' => route('postulantes.preconvalidacion.excel', [$postulante->id, $s->id]),
        ])->values();

        $tieneConv = $postulante->simulaciones->contains(fn ($s) => $s->convalidacion);
        $estadoPre = $tieneConv ? 'convalidada'
            : ($postulante->simulaciones->isNotEmpty() ? 'atendida' : 'pendiente');

        return inertia('Postulantes/Form', $this->opciones() + [
            'postulante' => $postulante->only([
                'id', 'codigo', 'tipo_documento', 'numero_documento', 'nombres', 'apellido_paterno',
                'apellido_materno', 'fecha_nacimiento', 'genero', 'nacionalidad', 'email', 'telefono',
                'institucion_origen_id', 'carrera_externa_id',
                'carrera_destino_id', 'ciclo_postulacion', 'estado', 'observaciones',
            ]) + [
                'consentimiento_datos' => $postulante->tieneConsentimientoDatos(),
                'consentimiento_datos_en' => optional($postulante->consentimiento_datos_en)->format('d/m/Y H:i'),
                'sin_documento' => $postulante->tipo_documento === 'TEMP',
                'carrera_destino_ids' => $postulante->destinos()->pluck('carrera_id')->all(),
                'documentos' => $postulante->documentos->map(fn ($d) => ['tipo' => $d->tipo, 'nombre' => $d->nombre_original])->values(),
            ],
            'preconvalidaciones' => $preconvalidaciones,
            'preconvalidacion_estado' => $estadoPre,
            'revision' => [
                'estado' => $postulante->revision_estado,
                'provisional' => (bool) $postulante->revision_provisional,
                'observaciones' => $postulante->revision_observaciones,
                'revisado_en' => optional($postulante->revisado_en)->format('d/m/Y H:i'),
                'revisado_por' => $postulante->revisadoPor?->nombre,
                'convalidada' => $tieneConv,
                'es_borrador' => $postulante->estado === 'borrador',
                'documentos' => $postulante->documentos->count(),
                'documentos_total' => count(self::DOCUMENTOS),
                // Lo que impide aprobar sin marcar la vía provisional.
                'documentos_faltantes' => $this->documentosFaltantes($postulante),
                // No admite revisión si ya está convalidado o si sigue siendo borrador.
                'puede_revisar' => $request->user()->puede('solicitudes.validar') && ! $tieneConv
                    && $postulante->estado !== 'borrador',
                'puede_reenviar' => $request->user()->puede('solicitudes.editar') && ! $tieneConv
                    && ($request->user()->rol?->nombre !== Role::ASESOR
                        || $postulante->usuario_id === $request->user()->id),
            ],
        ]);
    }

    /**
     * Devuelve los datos de preconvalidación del postulante como JSON (para el modal en el listado).
     */
    public function preconvalidacion(Request $request, Postulante $postulante)
    {
        $this->autorizarPropiedad($request->user(), $postulante);

        $postulante->load([
            'simulaciones.carreraUsil',
            'simulaciones.convalidacion',
            'simulaciones.detalles' => fn ($q) => $q->where('excluido', false)->whereNotNull('curso_usil_id'),
            'simulaciones.detalles.cursoUsil',
        ]);

        $preconvalidaciones = $postulante->simulaciones->sortByDesc('id')->map(fn (Simulacion $s) => [
            'id' => $s->id,
            'carrera' => $s->carreraUsil?->nombre,
            'metodo' => $s->metodo,
            'estado' => $s->estado,
            'fecha' => optional($s->created_at)->format('d/m/Y H:i'),
            'convalidados' => $s->detalles->count(),
            'creditos' => (float) $s->detalles->sum('creditos_reconocidos'),
            'cursos' => $s->detalles->map(fn ($d) => [
                'origen' => $d->curso_origen_nombre,
                'nota' => $d->nota_origen,
                'usil' => $d->cursoUsil?->nombre,
                'creditos' => (float) $d->creditos_reconocidos,
            ])->values(),
            'convalidada' => (bool) $s->convalidacion,
            'memorandum' => $s->convalidacion?->memorandum_numero,
            'pdf' => route('postulantes.preconvalidacion.pdf', [$postulante->id, $s->id]),
            'excel' => route('postulantes.preconvalidacion.excel', [$postulante->id, $s->id]),
        ])->values();

        $estadoPre = $postulante->simulaciones->contains(fn ($s) => $s->convalidacion) ? 'convalidada'
            : ($postulante->simulaciones->isNotEmpty() ? 'atendida' : 'pendiente');

        return response()->json([
            'postulante' => [
                'id' => $postulante->id,
                'nombre' => $postulante->nombre_completo,
                'codigo' => $postulante->codigo,
            ],
            'preconvalidaciones' => $preconvalidaciones,
            'preconvalidacion_estado' => $estadoPre,
        ]);
    }

    /**
     * Descarga el PDF de preconvalidación de un expediente del postulante.
     * Valida que la simulación pertenezca al postulante (scope manual).
     */
    public function preconvalidacionPdf(Request $request, Postulante $postulante, int $simulacion)
    {
        $this->autorizarPropiedad($request->user(), $postulante);
        $sim = Simulacion::where('postulante_id', $postulante->id)->findOrFail($simulacion);

        return app(SimulacionController::class)->generarPdf($sim);
    }

    /**
     * Descarga el Excel de preconvalidación de un expediente del postulante.
     */
    public function preconvalidacionExcel(Request $request, Postulante $postulante, int $simulacion)
    {
        $this->autorizarPropiedad($request->user(), $postulante);
        $sim = Simulacion::where('postulante_id', $postulante->id)->findOrFail($simulacion);

        return app(SimulacionController::class)->exportarExcel($sim);
    }

    public function update(Request $request, Postulante $postulante): RedirectResponse
    {
        $this->autorizarPropiedad($request->user(), $postulante);

        $borrador = $request->boolean('borrador');
        $datos = $this->validar($request, $postulante->id, $borrador);
        $destinoIds = $this->extraerDestinos($datos);
        $antes = $postulante->only(['estado', 'carrera_destino_id', 'email']);

        if ($request->boolean('sin_documento') && $postulante->tipo_documento !== 'TEMP') {
            $datos['tipo_documento'] = 'TEMP';
            $datos['numero_documento'] = 'TMP-'.now()->year.'-'.str_pad((string) $postulante->id, 5, '0', STR_PAD_LEFT);
        }

        // Un borrador que se guarda completo pasa a registro definitivo. Solo avanza:
        // editar un expediente ya registrado nunca lo devuelve a borrador.
        $promovido = ! $borrador && $postulante->estado === 'borrador';
        if ($promovido) {
            $datos['estado'] = 'nuevo';
        }

        $postulante->update($datos);
        $this->guardarDocumentos($request, $postulante);
        $this->syncDestinos($postulante, $destinoIds);

        AuditoriaService::registrar('editar', 'postulantes', $postulante->id, $antes, $datos);

        return redirect()->route('postulantes.index')->with('status', $promovido
            ? "Borrador completado: {$postulante->codigo} pasó a registro definitivo y ya puede revisarse."
            : 'Postulante actualizado.');
    }

    public function estado(Request $request, Postulante $postulante): RedirectResponse
    {
        $this->autorizarPropiedad($request->user(), $postulante);

        $actual = $postulante->estado;
        $permitidos = self::TRANSICIONES[$actual] ?? [];

        $datos = $request->validate(['estado' => ['required', Rule::in($permitidos)]], [
            'estado.in' => $permitidos === []
                ? "Un expediente en «{$actual}» ya no admite más cambios de estado."
                : "Desde «{$actual}» solo se puede pasar a: ".implode(', ', $permitidos).'.',
        ]);

        $postulante->update($datos);
        AuditoriaService::registrar('editar', 'postulantes', $postulante->id,
            ['estado' => $actual], ['estado' => $postulante->estado]);

        return back()->with('status', 'Estado del postulante actualizado.');
    }

    public function resetAcceso(Request $request, Postulante $postulante): RedirectResponse
    {
        $this->autorizarPropiedad($request->user(), $postulante);
        abort_if(empty($postulante->email), 422, 'El postulante no tiene correo para habilitar acceso.');

        $temporal = Str::password(10);
        $postulante->update([
            'password_hash' => Hash::make($temporal),
            'acceso_habilitado' => true,
            'debe_cambiar_password' => true,
        ]);
        $this->enviarAcceso($postulante, $temporal);
        AuditoriaService::registrar('editar', 'postulantes', $postulante->id, null, ['reset_acceso' => true]);

        return back()->with('status',
            "Acceso restablecido. Se envió la contraseña temporal a {$postulante->email}.".$this->pistaLocal($temporal));
    }

    /**
     * Solo en desarrollo se muestra la contraseña en pantalla. En producción el
     * canal de entrega es el correo: en el mensaje flash quedaba en la sesión y
     * en el historial del navegador del asesor.
     */
    private function pistaLocal(string $temporal): string
    {
        return app()->environment('local') ? " (solo en desarrollo — temporal: {$temporal})" : '';
    }

    public function destroy(Request $request, Postulante $postulante): RedirectResponse
    {
        $this->autorizarPropiedad($request->user(), $postulante);

        $postulante->delete();
        AuditoriaService::registrar('eliminar', 'postulantes', $postulante->id);

        return redirect()->route('postulantes.index')->with('status', 'Postulante eliminado.');
    }

    /** El Ejecutivo Comercial aprueba u observa el expediente. */
    public function revisar(Request $request, Postulante $postulante): RedirectResponse
    {
        // Un borrador está incompleto por definición (puede no tener correo, carrera
        // destino ni ciclo): no hay nada que aprobar hasta que el asesor lo cierre.
        abort_if($postulante->estado === 'borrador', 422,
            'El expediente aún es un borrador; el asesor debe completarlo antes de que pueda revisarse.');

        // Un expediente ya convalidado por el coordinador no admite más cambios de revisión.
        abort_if($this->tieneConvalidacion($postulante), 422,
            'El expediente ya tiene una convalidación confirmada; no admite cambios de revisión.');

        $datos = $request->validate([
            'accion' => ['required', 'in:aprobar,observar'],
            'observaciones' => ['required_if:accion,observar', 'nullable', 'string', 'max:1000'],
            'provisional' => ['boolean'],
        ], [
            'observaciones.required_if' => 'Indica qué debe corregir el postulante.',
        ]);

        $aprobar = $datos['accion'] === 'aprobar';
        $provisional = $aprobar && $request->boolean('provisional');

        // Art. 24 del Reglamento de Admisión: apto solo con TODOS los documentos
        // exigidos. La modalidad admite una vía temporal (récord de notas con
        // declaración jurada), que aquí es una aprobación provisional explícita.
        if ($aprobar && ($faltan = $this->documentosFaltantes($postulante)) !== []) {
            abort_unless($provisional, 422,
                'Falta documentación del expediente: '.implode(', ', $faltan).'. '
                .'Obsérvalo para que se complete, o apruébalo de forma provisional si se presentó declaración jurada.');

            abort_if(trim((string) ($datos['observaciones'] ?? '')) === '', 422,
                'La aprobación provisional exige dejar constancia de qué falta y bajo qué declaración jurada se admite.');
        }

        $cambios = [
            'revision_estado' => $aprobar ? 'aprobada' : 'observada',
            'revision_provisional' => $provisional,
            // Se conserva la nota de la aprobación provisional: es su justificación.
            'revision_observaciones' => $aprobar
                ? ($provisional ? $datos['observaciones'] : null)
                : $datos['observaciones'],
            'revisado_por' => $request->user()->id,
            'revisado_en' => now(),
        ];
        // Al aprobar, el expediente pasa a evaluación del coordinador (el "estado" avanza).
        if ($aprobar && $postulante->estado === 'nuevo') {
            $cambios['estado'] = 'en_evaluacion';
        }
        $postulante->update($cambios);

        // 'accion' es un enum fijo en auditoria_log; el matiz de revisión va en el payload.
        AuditoriaService::registrar('editar', 'postulantes', $postulante->id, null, [
            'revision_estado' => $postulante->revision_estado,
            'provisional' => $provisional,
        ]);

        if (! $aprobar) {
            return back()->with('status',
                "Expediente {$postulante->codigo} observado. La observación es visible para el asesor y el postulante.");
        }

        return back()->with('status', $provisional
            ? "Expediente {$postulante->codigo} aprobado de forma PROVISIONAL. Queda pendiente regularizar la documentación."
            : "Expediente {$postulante->codigo} aprobado. Ya puede evaluarse.");
    }

    /** Cuántos documentos componen el expediente (lo consulta el portal). */
    public static function totalDocumentos(): int
    {
        return count(self::DOCUMENTOS);
    }

    /** Etiquetas de los documentos del expediente que aún no se han adjuntado. */
    private function documentosFaltantes(Postulante $postulante): array
    {
        $entregados = $postulante->documentos()->pluck('tipo')->unique()->all();

        return array_values(array_diff_key(self::DOCUMENTOS, array_flip($entregados)));
    }

    /** El Asesor dueño reenvía a revisión un expediente observado ya corregido. */
    public function reenviarRevision(Request $request, Postulante $postulante): RedirectResponse
    {
        $this->autorizarPropiedad($request->user(), $postulante);
        abort_unless($postulante->revision_estado === 'observada', 422, 'Solo se puede reenviar un expediente observado.');

        $postulante->update([
            'revision_estado' => 'pendiente',
            'revision_observaciones' => null,
            'revisado_por' => null,
            'revisado_en' => null,
        ]);

        AuditoriaService::registrar('editar', 'postulantes', $postulante->id, null, ['revision_estado' => 'reenviada']);

        return back()->with('status',
            "Expediente {$postulante->codigo} reenviado a revisión. El Ejecutivo Comercial lo verá como pendiente.");
    }

    /** Envía al postulante sus credenciales del portal; no rompe el registro si falla el correo. */
    private function enviarAcceso(Postulante $postulante, string $temporal): void
    {
        try {
            Mail::to($postulante->email)->send(
                new AccesoPortalMail($postulante, route('portal.login'), $temporal)
            );
        } catch (\Throwable $e) {
            Log::warning('No se pudo enviar el correo de acceso al portal: '.$e->getMessage());
        }
    }

    /**
     * Autorización sobre un expediente concreto. Dos reglas independientes:
     *  - propiedad: el Asesor solo opera sobre los postulantes que él registró.
     *  - alcance:   Coordinador/Director/Decano solo los de sus carreras (RF-40).
     * Filtrar el listado no basta: sin esto se entra por la URL directa.
     */
    private function autorizarPropiedad(User $user, Postulante $postulante): void
    {
        if ($user->rol?->nombre === Role::ASESOR) {
            abort_unless($postulante->usuario_id === $user->id, 403, 'Solo puedes gestionar los postulantes que registraste.');
        }

        AlcanceService::autorizarPostulante($user, $postulante);
    }

    /** ¿El expediente ya tiene una convalidación confirmada (el coordinador ya lo procesó)? */
    private function tieneConvalidacion(Postulante $postulante): bool
    {
        return $postulante->simulaciones()->whereHas('convalidacion')->exists();
    }

    /**
     * Extrae y normaliza los ids de carreras destino del arreglo validado,
     * fijando el primero como destino primario (postulantes.carrera_destino_id).
     */
    private function extraerDestinos(array &$datos): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $datos['carrera_destino_ids'] ?? []))));
        unset($datos['carrera_destino_ids']);
        $datos['carrera_destino_id'] = $ids[0] ?? null;

        return $ids;
    }

    /** Sincroniza la tabla postulante_destinos con las carreras solicitadas. */
    private function syncDestinos(Postulante $postulante, array $ids): void
    {
        $postulante->destinos()->whereNotIn('carrera_id', $ids ?: [0])->delete();

        foreach ($ids as $carreraId) {
            $postulante->destinos()->firstOrCreate(['carrera_id' => $carreraId]);
        }
    }

    private function guardarDocumentos(Request $request, Postulante $postulante): void
    {
        foreach (array_keys(self::DOCUMENTOS) as $tipo) {
            if ($request->hasFile($tipo)) {
                $archivo = $request->file($tipo);
                $ruta = $archivo->store("postulantes/{$postulante->id}");
                $postulante->documentos()->create([
                    'tipo' => $tipo,
                    'nombre_original' => $archivo->getClientOriginalName(),
                    'ruta' => $ruta,
                    'tamano' => $archivo->getSize(),
                ]);
            }
        }
    }

    private function opciones(): array
    {
        return [
            'instituciones' => InstitucionExterna::where('activa', true)->orderBy('nombre')->get(['id', 'nombre']),
            'carreras' => Carrera::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'estados' => self::ESTADOS,
            // Del servidor, para que la lista del formulario no se desalinee con
            // la que se valida y se exige al aprobar.
            'documentos_tipos' => self::DOCUMENTOS,
        ];
    }

    private function validar(Request $request, ?int $id, bool $borrador): array
    {
        $sinDoc = $request->boolean('sin_documento');

        $rules = [
            'nombres' => ['required', 'string', 'min:2', 'max:100', 'regex:'.self::RE_NOMBRE],
            'apellido_paterno' => ['required', 'string', 'min:2', 'max:100', 'regex:'.self::RE_NOMBRE],
            'apellido_materno' => ['nullable', 'string', 'min:2', 'max:100', 'regex:'.self::RE_NOMBRE],
            // Rango de edad plausible para un traslado externo (evita fechas futuras y tecleos absurdos).
            'fecha_nacimiento' => ['nullable', 'date', 'before:-15 years', 'after:-100 years'],
            'genero' => ['nullable', 'in:masculino,femenino,otro,no_especifica'],
            'nacionalidad' => ['nullable', 'string', 'max:60', 'regex:'.self::RE_NOMBRE],
            'email' => [$borrador ? 'nullable' : 'required', 'email', 'max:150',
                // El correo es la identidad de acceso al portal: único entre postulantes activos.
                Rule::unique('postulantes', 'email')->ignore($id)->whereNull('deleted_at')],
            'telefono' => ['nullable', 'string', 'regex:'.self::RE_TELEFONO],
            'institucion_origen_id' => ['nullable', 'exists:instituciones_externas,id'],
            'carrera_externa_id' => ['nullable', 'exists:carreras_externas,id'],
            'carrera_destino_ids' => [$borrador ? 'nullable' : 'required', 'array'],
            'carrera_destino_ids.*' => ['integer', 'exists:carreras,id'],
            'ciclo_postulacion' => [$borrador ? 'nullable' : 'required', 'regex:/^20\d{2}-[12]$/'],
            // Art. 15 del Reglamento de Admisión: consentimiento expreso e
            // inequívoco. Un borrador aún no es un registro, así que no se exige.
            'consentimiento_datos' => [$borrador ? 'nullable' : 'accepted'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            // Adjuntar es opcional al registrar (el asesor los recibe por partes);
            // lo que ya no es opcional es aprobar el expediente sin ellos: eso lo
            // exige revisar(). Los sílabos admiten ZIP porque suelen ser varios.
            'dni' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'certificado' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'silabos' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,zip', 'max:10240'],
            'constancia' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'solicitud' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];

        $mensajes = [
            'ciclo_postulacion.regex' => 'El ciclo debe tener el formato AAAA-N, con N igual a 1 o 2 (por ejemplo, 2026-1).',
            'carrera_destino_ids.required' => 'Selecciona al menos una carrera destino en USIL.',
            'consentimiento_datos.accepted' => 'El postulante debe autorizar expresamente el tratamiento de sus datos personales (Art. 15 del Reglamento de Admisión).',
            'email.required' => 'El correo es obligatorio para registrar al postulante.',
            'email.unique' => 'Ya existe un postulante registrado con ese correo.',
            'nombres.regex' => 'Los nombres solo admiten letras, espacios, apóstrofos y guiones.',
            'apellido_paterno.regex' => 'El apellido paterno solo admite letras, espacios, apóstrofos y guiones.',
            'apellido_materno.regex' => 'El apellido materno solo admite letras, espacios, apóstrofos y guiones.',
            'nacionalidad.regex' => 'La nacionalidad solo admite letras y espacios.',
            'telefono.regex' => 'El teléfono admite de 6 a 20 caracteres entre dígitos, espacios y los signos + ( ) -.',
            'fecha_nacimiento.before' => 'El postulante debe tener al menos 15 años.',
            'fecha_nacimiento.after' => 'Revisa la fecha de nacimiento: la edad no es plausible.',
        ];

        if (! $sinDoc) {
            $rules['tipo_documento'] = ['required', Rule::in(array_keys(self::REGLAS_DOCUMENTO))];
            $rules['numero_documento'] = ['required', 'string', 'max:20',
                Rule::unique('postulantes', 'numero_documento')
                    ->where(fn ($q) => $q->where('tipo_documento', $request->tipo_documento))
                    ->ignore($id)->whereNull('deleted_at')];
            $mensajes['numero_documento.unique'] = 'Ya existe un postulante con ese tipo y número de documento.';

            // Formato propio de cada tipo (un DNI no admite letras). Si el tipo no es
            // válido, 'tipo_documento' ya lo rechaza y no hay formato que aplicar.
            if ($regla = self::REGLAS_DOCUMENTO[$request->tipo_documento] ?? null) {
                $rules['numero_documento'][] = 'regex:'.$regla[0];
                $mensajes['numero_documento.regex'] = $regla[1];
            }
        }

        $datos = $request->validate($rules, $mensajes);

        // La casilla se guarda como el instante en que se otorgó: es la constancia.
        $acepta = $request->boolean('consentimiento_datos');
        unset($datos['consentimiento_datos']);
        if ($acepta) {
            $datos['consentimiento_datos_en'] = now();
        }

        // Solo columnas persistibles (los archivos se procesan aparte).
        return collect($datos)->except(array_keys(self::DOCUMENTOS))->all();
    }
}
