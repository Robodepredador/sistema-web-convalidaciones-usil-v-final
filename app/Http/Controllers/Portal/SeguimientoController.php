<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PostulanteController;
use App\Models\Convalidacion;
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
        $p->load(['carreraDestino', 'institucionOrigen', 'carreraExterna', 'destinos.carrera', 'simulaciones.detalles', 'simulaciones.convalidacion']);

        // Señales reales del avance del expediente.
        $docsCount = $p->documentos()->count();
        $destinos = $p->destinos;
        $tieneSim = $p->simulaciones->isNotEmpty();
        $confirmada = $p->simulaciones->contains(fn (Simulacion $s) => $s->convalidacion?->estado === Convalidacion::CONFIRMADA);

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
                $docsCount, $p->revision_estado ?? 'pendiente', $tieneSim, $confirmada,
                PostulanteController::totalDocumentos(), (bool) $p->revision_provisional
            ),
            'simulaciones' => $p->simulaciones->map(fn (Simulacion $s) => [
                'id' => $s->id,
                'fecha' => $s->created_at?->format('Y-m-d'),
                'estado' => $s->estado,
                'cursos' => $s->detalles->where('excluido', false)->count(),
                'creditos' => (float) $s->detalles->where('excluido', false)->sum('creditos_reconocidos'),
                // Null mientras no esté confirmada: la vista muestra entonces por
                // qué todavía no hay documento, en vez de un botón que daría 403.
                'pdf_url' => $s->convalidacion?->estado === Convalidacion::CONFIRMADA
                    ? route('portal.preconvalidacion', $s->id)
                    : null,
            ])->values(),
        ]);
    }
}
