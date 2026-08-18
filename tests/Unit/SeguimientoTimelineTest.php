<?php

namespace Tests\Unit;

use App\Support\SeguimientoTimeline;
use PHPUnit\Framework\TestCase;

class SeguimientoTimelineTest extends TestCase
{
    /** @param array<int, array> $t */
    private function estados(array $t): array
    {
        return array_map(fn ($e) => $e['estado'], $t);
    }

    public function test_fase_1_solo_registro(): void
    {
        $t = SeguimientoTimeline::construir('nuevo', '01/01/2026', 0, 'pendiente', false);
        $this->assertSame(['completado', 'actual', 'pendiente'], $this->estados($t));
    }

    public function test_fase_2_documentos_aprobados_por_admision(): void
    {
        $t = SeguimientoTimeline::construir('en_evaluacion', '01/01/2026', 3, 'aprobada', false);
        $this->assertSame(['completado', 'completado', 'actual'], $this->estados($t));
    }

    /**
     * La última etapa se alcanza con la simulación generada, y ahí termina el
     * recorrido del postulante. Es la razón de ser del cambio: antes había una
     * cuarta etapa atada a la confirmación del memorándum, que el sistema dejó
     * de emitir, y por tanto no se completaba nunca.
     */
    public function test_fase_3_preconvalidacion_disponible_cierra_el_recorrido(): void
    {
        $t = SeguimientoTimeline::construir('en_evaluacion', '01/01/2026', 3, 'aprobada', true);

        $this->assertCount(3, $t);
        $this->assertSame(['completado', 'completado', 'completado'], $this->estados($t));
        $this->assertSame('Preconvalidación disponible', $t[2]['label']);
        $this->assertSame('Ya puedes consultar los cursos preconvalidados', $t[2]['detalle']);
    }

    public function test_detalle_documentos_segun_revision(): void
    {
        // Pendiente: muestra el avance de entrega de documentos.
        $pendiente = SeguimientoTimeline::construir('nuevo', '01/01/2026', 1, 'pendiente', false);
        $this->assertSame('1 de 2 documentos entregados', $pendiente[1]['detalle']);

        // Observada: pide corregir.
        $observada = SeguimientoTimeline::construir('en_evaluacion', '01/01/2026', 3, 'observada', false);
        $this->assertSame('Documentación observada: revisa las indicaciones', $observada[1]['detalle']);

        // Aprobada: expediente validado por Admisión.
        $aprobada = SeguimientoTimeline::construir('en_evaluacion', '01/01/2026', 5, 'aprobada', false);
        $this->assertSame('Documentos revisados y aprobados por Admisión', $aprobada[1]['detalle']);

        // Aprobada de forma provisional: avanza, pero el postulante debe regularizar.
        $provisional = SeguimientoTimeline::construir('en_evaluacion', '01/01/2026', 2, 'aprobada', false, 5, true);
        $this->assertSame('Aprobado de forma provisional: queda pendiente regularizar la documentación',
            $provisional[1]['detalle']);
    }

    public function test_rechazado_devuelve_una_sola_etapa(): void
    {
        $t = SeguimientoTimeline::construir('rechazado', '01/01/2026', 0, 'pendiente', false);
        $this->assertCount(1, $t);
        $this->assertSame('rechazado', $t[0]['estado']);
    }
}
