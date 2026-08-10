<?php

namespace App\Support;

/**
 * Construye la línea de tiempo del proceso de convalidación del postulante.
 *
 * Son TRES etapas y la última es la preconvalidación disponible. Antes había una
 * cuarta, «Convalidación confirmada», que se apoyaba en que el sistema emitiera
 * el memorándum oficial. Ese acto pasó a gestionarse fuera del sistema, así que
 * la etapa ya no podía completarse nunca: el postulante veía «Pendiente de
 * confirmación» de forma indefinida, sin que hubiera nada que esperar.
 */
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
        int $docsTotal = 5,
        bool $provisional = false,
    ): array {
        if ($estado === 'rechazado') {
            return [[
                'label' => 'Solicitud rechazada', 'estado' => 'rechazado',
                'detalle' => 'Comunícate con la Coordinación Académica para más información.',
            ]];
        }

        // La aprobación del Ejecutivo Comercial (revision_estado) es la señal real que
        // habilita la evaluación del coordinador; el conteo de documentos es solo el avance previo.
        $aprobada = $revisionEstado === 'aprobada';
        $observada = $revisionEstado === 'observada';
        $detalleDocs = match (true) {
            // Una aprobación provisional avanza el expediente pero deja pendiente
            // entregar lo que falta: el postulante tiene que saberlo.
            $aprobada && $provisional => 'Aprobado de forma provisional: queda pendiente regularizar la documentación',
            $aprobada => 'Documentos revisados y aprobados por Admisión',
            $observada => 'Documentación observada: revisa las indicaciones',
            default => "{$docsCount} de {$docsTotal} documentos entregados",
        };

        $etapas = [
            ['label' => 'Solicitud registrada', 'done' => true,
                'detalle' => 'Recibida el '.($registradaEl ?? '—')],
            ['label' => 'Revisión de documentos', 'done' => $aprobada,
                'detalle' => $detalleDocs],
            ['label' => 'Preconvalidación disponible', 'done' => $tieneSim,
                'detalle' => $tieneSim
                    ? 'Ya puedes consultar los cursos preconvalidados'
                    : 'Pendiente de la evaluación académica'],
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
