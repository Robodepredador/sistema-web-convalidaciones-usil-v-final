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
    private const ESTADOS = ['nuevo', 'en_evaluacion', 'admitido', 'rechazado', 'matriculado'];

    private const DOCUMENTOS = ['certificado', 'silabos', 'constancia'];

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
            ]);

        return inertia('Postulantes/Index', [
            'postulantes' => $postulantes,
            // El Asesor solo cuenta los suyos (coherente con su listado).
            'total' => Postulante::when($request->user()->rol?->nombre === Role::ASESOR,
                fn ($q) => $q->where('usuario_id', $request->user()->id))->count(),
            'carreras' => Carrera::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'estados' => self::ESTADOS,
            'revisiones' => ['pendiente', 'aprobada', 'observada'],
            'filtros' => $request->only(['q', 'estado', 'revision', 'carrera_destino_id']),
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
        $datos['estado'] = 'nuevo';

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
            $url = route('portal.login');
            $msg .= " Se enviaron las credenciales al correo del postulante. Acceso ({$url}) → usuario: {$postulante->email} · contraseña temporal: {$temporal}";
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
                'pais_residencia', 'direccion', 'institucion_origen_id', 'carrera_externa_id',
                'carrera_destino_id', 'ciclo_postulacion', 'estado', 'observaciones',
            ]) + [
                'sin_documento' => $postulante->tipo_documento === 'TEMP',
                'carrera_destino_ids' => $postulante->destinos()->pluck('carrera_id')->all(),
                'documentos' => $postulante->documentos->map(fn ($d) => ['tipo' => $d->tipo, 'nombre' => $d->nombre_original])->values(),
            ],
            'preconvalidaciones' => $preconvalidaciones,
            'preconvalidacion_estado' => $estadoPre,
            'revision' => [
                'estado' => $postulante->revision_estado,
                'observaciones' => $postulante->revision_observaciones,
                'revisado_en' => optional($postulante->revisado_en)->format('d/m/Y H:i'),
                'revisado_por' => $postulante->revisadoPor?->nombre,
                'convalidada' => $tieneConv,
                'documentos' => $postulante->documentos->count(),
                'documentos_total' => count(self::DOCUMENTOS),
                // Ya no admite cambios de revisión una vez convalidado.
                'puede_revisar' => $request->user()->puede('solicitudes.validar') && ! $tieneConv,
                'puede_reenviar' => $request->user()->puede('solicitudes.editar') && ! $tieneConv
                    && ($request->user()->rol?->nombre !== Role::ASESOR
                        || $postulante->usuario_id === $request->user()->id),
            ],
        ]);
    }

    /**
     * Devuelve los datos de preconvalidación del postulante como JSON (para el modal en el listado).
     */
    public function preconvalidacion(Postulante $postulante)
    {
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
    public function preconvalidacionPdf(Postulante $postulante, int $simulacion)
    {
        $sim = Simulacion::where('postulante_id', $postulante->id)->findOrFail($simulacion);

        return app(SimulacionController::class)->generarPdf($sim);
    }

    /**
     * Descarga el Excel de preconvalidación de un expediente del postulante.
     */
    public function preconvalidacionExcel(Postulante $postulante, int $simulacion)
    {
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

        $postulante->update($datos);
        $this->guardarDocumentos($request, $postulante);
        $this->syncDestinos($postulante, $destinoIds);

        AuditoriaService::registrar('editar', 'postulantes', $postulante->id, $antes, $datos);

        return redirect()->route('postulantes.index')->with('status', 'Postulante actualizado.');
    }

    public function estado(Request $request, Postulante $postulante): RedirectResponse
    {
        $datos = $request->validate(['estado' => ['required', Rule::in(self::ESTADOS)]]);
        $postulante->update($datos);
        AuditoriaService::registrar('editar', 'postulantes', $postulante->id, null, ['estado' => $postulante->estado]);

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

        return back()->with('status', "Acceso restablecido para {$postulante->email}. Se envió la contraseña temporal por correo. (Temporal: {$temporal})");
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
        // Un expediente ya convalidado por el coordinador no admite más cambios de revisión.
        abort_if($this->tieneConvalidacion($postulante), 422,
            'El expediente ya tiene una convalidación confirmada; no admite cambios de revisión.');

        $datos = $request->validate([
            'accion' => ['required', 'in:aprobar,observar'],
            'observaciones' => ['required_if:accion,observar', 'nullable', 'string', 'max:1000'],
        ], [
            'observaciones.required_if' => 'Indica qué debe corregir el postulante.',
        ]);

        $aprobar = $datos['accion'] === 'aprobar';
        $cambios = [
            'revision_estado' => $aprobar ? 'aprobada' : 'observada',
            'revision_observaciones' => $aprobar ? null : $datos['observaciones'],
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
        ]);

        return back()->with('status', $aprobar
            ? "Expediente {$postulante->codigo} aprobado. Ya puede evaluarse."
            : "Expediente {$postulante->codigo} observado. La observación es visible para el asesor y el postulante.");
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

        return back()->with('status', 'Expediente reenviado a revisión.');
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

    /** El Asesor solo opera sobre sus propios postulantes; Ejecutivo/Superusuario, todos. */
    private function autorizarPropiedad(User $user, Postulante $postulante): void
    {
        if ($user->rol?->nombre === Role::ASESOR) {
            abort_unless($postulante->usuario_id === $user->id, 403, 'Solo puedes gestionar los postulantes que registraste.');
        }
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
        foreach (self::DOCUMENTOS as $tipo) {
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
        ];
    }

    private function validar(Request $request, ?int $id, bool $borrador): array
    {
        $sinDoc = $request->boolean('sin_documento');

        $rules = [
            'nombres' => ['required', 'string', 'max:100'],
            'apellido_paterno' => ['required', 'string', 'max:100'],
            'apellido_materno' => ['nullable', 'string', 'max:100'],
            'fecha_nacimiento' => ['nullable', 'date', 'before:today'],
            'genero' => ['nullable', 'in:masculino,femenino,otro,no_especifica'],
            'nacionalidad' => ['nullable', 'string', 'max:60'],
            'email' => [$borrador ? 'nullable' : 'required', 'email', 'max:150',
                // El correo es la identidad de acceso al portal: único entre postulantes activos.
                Rule::unique('postulantes', 'email')->ignore($id)->whereNull('deleted_at')],
            'telefono' => ['nullable', 'string', 'max:20'],
            'pais_residencia' => ['nullable', 'string', 'max:60'],
            'direccion' => ['nullable', 'string', 'max:200'],
            'institucion_origen_id' => ['nullable', 'exists:instituciones_externas,id'],
            'carrera_externa_id' => ['nullable', 'exists:carreras_externas,id'],
            'carrera_destino_ids' => [$borrador ? 'nullable' : 'required', 'array'],
            'carrera_destino_ids.*' => ['integer', 'exists:carreras,id'],
            'ciclo_postulacion' => [$borrador ? 'nullable' : 'required', 'regex:/^\d{4}-\d$/'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'certificado' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'silabos' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png,zip', 'max:10240'],
            'constancia' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ];

        if (! $sinDoc) {
            $rules['tipo_documento'] = ['required', 'in:DNI,CE,PASAPORTE,PTP'];
            $rules['numero_documento'] = ['required', 'string', 'max:20',
                Rule::unique('postulantes', 'numero_documento')
                    ->where(fn ($q) => $q->where('tipo_documento', $request->tipo_documento))
                    ->ignore($id)->whereNull('deleted_at')];
        }

        $datos = $request->validate($rules, [
            'numero_documento.unique' => 'Ya existe un postulante con ese tipo y número de documento.',
            'ciclo_postulacion.regex' => 'El ciclo debe tener el formato AAAA-N (por ejemplo, 2026-1).',
            'carrera_destino_id.required' => 'Selecciona la carrera destino en USIL.',
            'email.required' => 'El correo es obligatorio para registrar al postulante.',
            'email.unique' => 'Ya existe un postulante registrado con ese correo.',
        ]);

        // Solo columnas persistibles (los archivos se procesan aparte).
        return collect($datos)->except(self::DOCUMENTOS)->all();
    }
}
