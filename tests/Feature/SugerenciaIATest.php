<?php

namespace Tests\Feature;

use App\Services\Seudonimizador;
use PHPUnit\Framework\TestCase;

class SugerenciaIATest extends TestCase
{
    /** RNF-09: la seudonimización elimina datos personales del texto. */
    public function test_seudonimiza_datos_personales(): void
    {
        $texto = 'Contacto: ana.perez@usil.edu.pe, DNI 12345678, cel 987654321';
        $limpio = Seudonimizador::limpiar($texto);

        $this->assertStringNotContainsString('ana.perez@usil.edu.pe', $limpio);
        $this->assertStringNotContainsString('12345678', $limpio);
        $this->assertStringContainsString('[correo]', $limpio);
        $this->assertStringContainsString('[documento]', $limpio);
    }

    /** El contenido académico sobrevive a la limpieza (notas y créditos intactos). */
    public function test_conserva_el_contenido_academico(): void
    {
        $limpio = Seudonimizador::limpiar('Cálculo Diferencial | nota 15 | creditos 4');

        $this->assertStringContainsString('Cálculo Diferencial', $limpio);
        $this->assertStringContainsString('nota 15', $limpio);
        $this->assertStringContainsString('creditos 4', $limpio);
    }
}
