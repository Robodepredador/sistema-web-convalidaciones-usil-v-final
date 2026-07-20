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
        string $revisionEstado,
        bool $tieneSim,
        bool $confirmada,
    ): array {
        if ($estado === 'rechazado') {
            return [[
                'label' => 'Solicitud rechazada', 'estado' => 'rechazado',
                'detalle' => 'Comunícate con la Coordinación Académica para más información.',
            ]];
        }

        // La aprobación del Ejecutivo Comercial (revision_estado) es la señal real que
        // habilita la evaluación del coordinador; el conteo de documentos es solo el avance previo.
        $aprobada  = $revisionEstado === 'aprobada';
        $observada = $revisionEstado === 'observada';
        $detalleDocs = match (true) {
            $aprobada  => 'Documentos revisados y aprobados por Admisión',
            $observada => 'Documentación observada: revisa las indicaciones',
            default    => "{$docsCount} de 3 documentos entregados",
        };

        $etapas = [
            ['label' => 'Solicitud registrada', 'done' => true,
                'detalle' => 'Recibida el ' . ($registradaEl ?? '—')],
            ['label' => 'Revisión de documentos', 'done' => $aprobada,
                'detalle' => $detalleDocs],
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
