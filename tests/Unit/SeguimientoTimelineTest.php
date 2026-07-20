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
        $t = SeguimientoTimeline::construir('nuevo', '01/01/2026', 0, 'pendiente', false, false);
        $this->assertSame(['completado', 'actual', 'pendiente', 'pendiente'], $this->estados($t));
    }

    public function test_fase_2_documentos_aprobados_por_admision(): void
    {
        $t = SeguimientoTimeline::construir('en_evaluacion', '01/01/2026', 3, 'aprobada', false, false);
        $this->assertSame(['completado', 'completado', 'actual', 'pendiente'], $this->estados($t));
    }

    public function test_fase_3_simulacion(): void
    {
        $t = SeguimientoTimeline::construir('en_evaluacion', '01/01/2026', 3, 'aprobada', true, false);
        $this->assertSame(['completado', 'completado', 'completado', 'actual'], $this->estados($t));
    }

    public function test_fase_4_convalidacion_confirmada(): void
    {
        $t = SeguimientoTimeline::construir('admitido', '01/01/2026', 3, 'aprobada', true, true);
        $this->assertSame(['completado', 'completado', 'completado', 'completado'], $this->estados($t));
    }

    public function test_detalle_documentos_segun_revision(): void
    {
        // Pendiente: muestra el avance de entrega de documentos.
        $pendiente = SeguimientoTimeline::construir('nuevo', '01/01/2026', 1, 'pendiente', false, false);
        $this->assertSame('1 de 3 documentos entregados', $pendiente[1]['detalle']);

        // Observada: pide corregir.
        $observada = SeguimientoTimeline::construir('en_evaluacion', '01/01/2026', 3, 'observada', false, false);
        $this->assertSame('Documentación observada: revisa las indicaciones', $observada[1]['detalle']);

        // Aprobada: expediente validado por Admisión.
        $aprobada = SeguimientoTimeline::construir('en_evaluacion', '01/01/2026', 3, 'aprobada', false, false);
        $this->assertSame('Documentos revisados y aprobados por Admisión', $aprobada[1]['detalle']);
    }

    public function test_rechazado_devuelve_una_sola_etapa(): void
    {
        $t = SeguimientoTimeline::construir('rechazado', '01/01/2026', 0, 'pendiente', false, false);
        $this->assertCount(1, $t);
        $this->assertSame('rechazado', $t[0]['estado']);
    }
}
