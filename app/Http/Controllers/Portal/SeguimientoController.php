<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PostulanteController;
use App\Models\Simulacion;
use App\Support\SeguimientoTimeline;
use Illuminate\Support\Facades\Auth;

/**
 * Portal del postulante: seguimiento de su solicitud de convalidación.
 */
class SeguimientoController extends Controller
{
    public function index()
    {
        $p = Auth::guard('postulante')->user();
        $p->load(['carreraDestino', 'institucionOrigen', 'carreraExterna', 'destinos.carrera',
            'simulaciones.detalles.cursoUsil']);

        // Señales reales del avance del expediente. Se cuentan TIPOS distintos y
        // no filas: si no, varias versiones de un mismo documento le decían al
        // postulante que había entregado todo el expediente.
        $docsCount = $p->documentos()->distinct()->count('tipo');
        $destinos = $p->destinos;
        $tieneSim = $p->simulaciones->isNotEmpty();

        return inertia('Portal/Seguimiento', [
            'postulante' => [
                'codigo' => $p->codigo,
                'nombre' => $p->nombre_completo,
                'email' => $p->email,
                'estado' => $p->estado,
                'carrera_destino' => $p->carreraDestino?->nombre,
                'institucion' => $p->institucionOrigen?->nombre,
                'carrera_externa' => $p->carreraExterna?->nombre,
                'ciclo_postulacion' => $p->ciclo_postulacion,
                'observaciones' => $p->observaciones,
                'revision_estado' => $p->revision_estado,
                'revision_provisional' => (bool) $p->revision_provisional,
                'revision_observaciones' => $p->revision_observaciones,
            ],
            // Carreras solicitadas (una o más).
            'destinos' => $destinos->map(fn ($d) => [
                'carrera' => $d->carrera?->nombre,
            ])->values(),
            // Process Timeline del proceso de convalidación.
            'timeline' => SeguimientoTimeline::construir(
                $p->estado,
                $p->created_at?->format('d/m/Y'),
                $docsCount, $p->revision_estado ?? 'pendiente', $tieneSim,
                PostulanteController::totalDocumentos(), (bool) $p->revision_provisional
            ),
            // El postulante consulta el resultado EN PANTALLA y no descarga nada:
            // el documento oficial se gestiona fuera del sistema. Por eso aquí
            // viaja el detalle y no una URL de PDF.
            'simulaciones' => $p->simulaciones->map(function (Simulacion $s) {
                $vigentes = $s->detalles->where('excluido', false);
                $convalidados = $vigentes->whereNotNull('curso_usil_id');

                return [
                    'id' => $s->id,
                    'fecha' => $s->created_at?->format('Y-m-d'),
                    'estado' => $s->estado,
                    'cursos' => $convalidados->count(),
                    'creditos' => (float) $convalidados->sum('creditos_reconocidos'),
                    'convalidados' => $convalidados->map(fn ($d) => [
                        'origen' => $d->nombre_origen,
                        'usil' => $d->cursoUsil?->nombre,
                        'creditos' => (float) $d->creditos_reconocidos,
                    ])->values(),
                    // Con su motivo: el evaluador está obligado a escribirlo
                    // precisamente porque es lo que ve el postulante (ver la regla
                    // `filas.*.motivo` en SimulacionController::persistirSimulacion).
                    'no_convalidados' => $vigentes->whereNull('curso_usil_id')->map(fn ($d) => [
                        'origen' => $d->nombre_origen,
                        'motivo' => $d->motivo ?: 'No cumple los criterios de convalidación',
                    ])->values(),
                ];
            })->values(),
        ]);
    }
}
