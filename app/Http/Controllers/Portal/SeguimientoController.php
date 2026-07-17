<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Simulacion;
use Illuminate\Support\Facades\Auth;

/**
 * Portal del postulante: seguimiento de su solicitud de convalidación.
 */
class SeguimientoController extends Controller
{
    public function index()
    {
        $p = Auth::guard('postulante')->user();
        $p->load(['carreraDestino', 'institucionOrigen', 'carreraExterna', 'destinos.carrera', 'simulaciones.detalles']);

        // Señales reales del avance del expediente.
        $docsCount     = $p->documentos()->count();
        $docsCompletos = $docsCount >= 3;
        $destinos      = $p->destinos;
        $todasAprob    = $destinos->isNotEmpty() && $destinos->every(fn ($d) => $d->estado_equivalencias === 'aprobada');
        $enRevision    = $destinos->contains(fn ($d) => in_array($d->estado_equivalencias, ['en_revision', 'aprobada'], true));
        $tieneSim      = $p->simulaciones->isNotEmpty();
        $confirmada    = $p->simulaciones->contains(fn (Simulacion $s) => $s->estado === 'confirmada');

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
            'timeline' => $this->timeline($p, $docsCount, $docsCompletos, $todasAprob, $enRevision, $tieneSim, $confirmada),
            'simulaciones' => $p->simulaciones->map(fn (Simulacion $s) => [
                'id'        => $s->id,
                'fecha'     => $s->created_at?->format('Y-m-d'),
                'estado'    => $s->estado,
                'cursos'    => $s->detalles->where('excluido', false)->count(),
                'creditos'  => (float) $s->detalles->where('excluido', false)->sum('creditos_reconocidos'),
            ])->values(),
        ]);
    }

    /**
     * Construye la línea de tiempo del proceso. Cada etapa: completado | actual | pendiente.
     * La primera etapa no completada se marca como "actual".
     */
    private function timeline($p, int $docsCount, bool $docsCompletos, bool $todasAprob, bool $enRevision, bool $tieneSim, bool $confirmada): array
    {
        if ($p->estado === 'rechazado') {
            return [[
                'label' => 'Solicitud rechazada', 'estado' => 'rechazado',
                'detalle' => 'Comunícate con la Coordinación Académica para más información.',
            ]];
        }

        $etapas = [
            ['label' => 'Solicitud registrada', 'done' => true,
                'detalle' => 'Recibida el ' . ($p->created_at?->format('d/m/Y') ?? '—')],
            ['label' => 'Documentos recibidos', 'done' => $docsCompletos,
                'detalle' => $docsCompletos ? 'Expediente completo' : "{$docsCount} de 3 documentos entregados"],
            ['label' => 'Revisión de equivalencias', 'done' => $todasAprob,
                'detalle' => $todasAprob ? 'Equivalencias aprobadas' : ($enRevision ? 'En revisión por la coordinación' : 'En espera de revisión')],
            ['label' => 'Simulación de convalidación', 'done' => $tieneSim,
                'detalle' => $tieneSim ? 'Simulación generada' : 'Aún no generada'],
            ['label' => 'Convalidación confirmada', 'done' => $confirmada,
                'detalle' => $confirmada ? 'Convalidación oficial confirmada' : 'Pendiente de confirmación'],
        ];

        $hayActual = false;

        return array_map(function ($e) use (&$hayActual) {
            if ($e['done']) {
                $estado = 'completado';
            } elseif (! $hayActual) {
                $estado = 'actual';
                $hayActual = true;
            } else {
                $estado = 'pendiente';
            }

            return ['label' => $e['label'], 'detalle' => $e['detalle'], 'estado' => $estado];
        }, $etapas);
    }
}
