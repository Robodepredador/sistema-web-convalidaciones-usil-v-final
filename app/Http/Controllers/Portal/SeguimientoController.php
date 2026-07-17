<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
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
        $docsCount     = $p->documentos()->count();
        $docsCompletos = $docsCount >= 3;
        $destinos      = $p->destinos;
        $todasAprob    = $destinos->isNotEmpty() && $destinos->every(fn ($d) => $d->estado_equivalencias === 'aprobada');
        $enRevision    = $destinos->contains(fn ($d) => in_array($d->estado_equivalencias, ['en_revision', 'aprobada'], true));
        $tieneSim      = $p->simulaciones->isNotEmpty();
        $confirmada    = $p->simulaciones->contains(fn (Simulacion $s) => (bool) $s->convalidacion);

        return inertia('Portal/Seguimiento', [
            'postulante' => [
                'codigo'            => $p->codigo,
                'nombre'            => $p->nombre_completo,
                'email'             => $p->email,
                'estado'            => $p->estado,
                'carrera_destino'   => $p->carreraDestino?->nombre,
                'institucion'       => $p->institucionOrigen?->nombre,
                'carrera_externa'   => $p->carreraExterna?->nombre,
                'ciclo_postulacion' => $p->ciclo_postulacion,
                'observaciones'     => $p->observaciones,
                'revision_estado'        => $p->revision_estado,
                'revision_observaciones' => $p->revision_observaciones,
            ],
            // Carreras solicitadas (una o más) con su estado de revisión.
            'destinos' => $destinos->map(fn ($d) => [
                'carrera' => $d->carrera?->nombre,
                'estado'  => $d->estado_equivalencias,
            ])->values(),
            // Process Timeline del proceso de convalidación.
            'timeline' => SeguimientoTimeline::construir(
                $p->estado,
                $p->created_at?->format('d/m/Y'),
                $docsCount, $docsCompletos, $todasAprob, $enRevision, $tieneSim, $confirmada
            ),
            'simulaciones' => $p->simulaciones->map(fn (Simulacion $s) => [
                'id'        => $s->id,
                'fecha'     => $s->created_at?->format('Y-m-d'),
                'estado'    => $s->estado,
                'cursos'    => $s->detalles->where('excluido', false)->count(),
                'creditos'  => (float) $s->detalles->where('excluido', false)->sum('creditos_reconocidos'),
            ])->values(),
        ]);
    }
}
