<?php

namespace Tests\Unit;

use App\Services\ConvalidacionEngine;
use App\Services\LectorMallaExcel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PHPUnit\Framework\TestCase;

/**
 * Verifica que el lector interpreta el formato USIL: metadatos de cabecera,
 * fila de encabezado más abajo, filas «Total» descartadas, códigos autogenerados
 * y separación de las menciones respecto de los ciclos regulares.
 */
class LectorMallaExcelTest extends TestCase
{
    private function archivoDePrueba(): string
    {
        $ss = new Spreadsheet;
        $s = $ss->getActiveSheet();
        $s->setTitle('Ing. Software');

        // Metadatos de cabecera (como en el archivo real).
        $s->setCellValue('B2', 'Carrera Profesional: Ingeniería de Software');
        $s->setCellValue('B3', 'Plan de Estudios 2023-01');
        // Encabezado en la fila 5.
        $s->fromArray(['Ciclo', 'Curso', 'CR', 'TH', 'Pre - Requisito', 'Mención'], null, 'B5');
        // Ciclo 1.
        $s->fromArray([1, 'Fundamentos de programación', 3, 5, '', ''], null, 'B6');
        $s->fromArray([1, 'Matemática', 4, 6, 'Nivelación en Matemática', ''], null, 'B7');
        $s->fromArray(['', 'Total', 7, 11, '', ''], null, 'B8');       // se descarta
        // Ciclo 2.
        $s->fromArray([2, 'Electivo 1', 4, 6, '', ''], null, 'B9');
        // Bloque de mención.
        $s->fromArray([6, 'Procesamiento de señales', 4, 6, '', 'Analítica de datos'], null, 'B11');
        $s->fromArray([7, 'Visión computacional', 4, 6, 'Procesamiento de señales', 'Analítica de datos'], null, 'B12');

        $ruta = tempnam(sys_get_temp_dir(), 'malla').'.xlsx';
        (new Xlsx($ss))->save($ruta);

        return $ruta;
    }

    public function test_separa_ciclos_menciones_y_genera_codigos(): void
    {
        $ruta = $this->archivoDePrueba();
        $lector = new LectorMallaExcel(new ConvalidacionEngine);

        $res = $lector->parse($ruta, 'Ingeniería de Software', 'ISI');

        // Estructura general.
        $this->assertSame(2, $res['resumen']['ciclos']);
        $this->assertSame(1, $res['resumen']['menciones']);
        $this->assertSame(5, $res['resumen']['cursos']); // 3 regulares (2 del ciclo 1 + 1 del ciclo 2) + 2 de mención

        // Metadatos leídos de la cabecera.
        $this->assertSame(2023, $res['meta']['anio']);
        $this->assertSame('2023-01', $res['meta']['version']);

        // Código autogenerado con el prefijo de la carrera.
        $this->assertSame('ISI-C1-01', $res['ciclos'][0]['cursos'][0]['codigo']);
        $this->assertSame('ISI-M1-01', $res['menciones'][0]['cursos'][0]['codigo']);

        // La fila «Total» no se importó como curso.
        $nombres = array_column($res['ciclos'][0]['cursos'], 'nombre');
        $this->assertNotContains('Total', $nombres);

        // «Electivo 1» se marca como electivo.
        $this->assertTrue($res['ciclos'][1]['cursos'][0]['es_electivo']);

        // La mención conserva su nombre y el ciclo de cada curso.
        $this->assertSame('Analítica de datos', $res['menciones'][0]['nombre']);
        $this->assertSame(6, $res['menciones'][0]['cursos'][0]['ciclo']);

        unlink($ruta);
    }
}
