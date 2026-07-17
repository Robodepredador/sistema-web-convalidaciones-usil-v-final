<?php

namespace App\Support;

/** Construye la línea de tiempo del proceso de convalidación del postulante. */
class SeguimientoTimeline
{
    /**
     * Cada etapa: completado | actual | pendiente. La primera no completada se marca "actual".
     *
     * @return array<int, array{label:string, detalle:string, estado:string}>
     */
    public static function construir(
        string $estado,
        ?string $registradaEl,
        int $docsCount,
        bool $docsCompletos,
        bool $todasAprob,
        bool $enRevision,
        bool $tieneSim,
        bool $confirmada,
    ): array {
        if ($estado === 'rechazado') {
            return [[
                'label' => 'Solicitud rechazada', 'estado' => 'rechazado',
                'detalle' => 'Comunícate con la Coordinación Académica para más información.',
            ]];
        }

        $etapas = [
            ['label' => 'Solicitud registrada', 'done' => true,
                'detalle' => 'Recibida el ' . ($registradaEl ?? '—')],
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
