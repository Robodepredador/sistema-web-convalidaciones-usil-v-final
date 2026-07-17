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
        $t = SeguimientoTimeline::construir('nuevo', '01/01/2026', 0, false, false, false, false, false);
        $this->assertSame(['completado', 'actual', 'pendiente', 'pendiente', 'pendiente'], $this->estados($t));
    }

    public function test_fase_2_documentos_completos(): void
    {
        $t = SeguimientoTimeline::construir('nuevo', '01/01/2026', 3, true, false, false, false, false);
        $this->assertSame(['completado', 'completado', 'actual', 'pendiente', 'pendiente'], $this->estados($t));
    }

    public function test_fase_3_equivalencias_aprobadas(): void
    {
        $t = SeguimientoTimeline::construir('en_evaluacion', '01/01/2026', 3, true, true, true, false, false);
        $this->assertSame(['completado', 'completado', 'completado', 'actual', 'pendiente'], $this->estados($t));
    }

    public function test_fase_4_simulacion(): void
    {
        $t = SeguimientoTimeline::construir('en_evaluacion', '01/01/2026', 3, true, true, true, true, false);
        $this->assertSame(['completado', 'completado', 'completado', 'completado', 'actual'], $this->estados($t));
    }

    public function test_fase_5_convalidacion_confirmada(): void
    {
        $t = SeguimientoTimeline::construir('admitido', '01/01/2026', 3, true, true, true, true, true);
        $this->assertSame(['completado', 'completado', 'completado', 'completado', 'completado'], $this->estados($t));
    }

    public function test_equivalencias_en_revision_muestra_detalle(): void
    {
        $t = SeguimientoTimeline::construir('en_evaluacion', '01/01/2026', 3, true, false, true, false, false);
        $this->assertSame('En revisión por la coordinación', $t[2]['detalle']);
    }

    public function test_rechazado_devuelve_una_sola_etapa(): void
    {
        $t = SeguimientoTimeline::construir('rechazado', '01/01/2026', 0, false, false, false, false, false);
        $this->assertCount(1, $t);
        $this->assertSame('rechazado', $t[0]['estado']);
    }
}
