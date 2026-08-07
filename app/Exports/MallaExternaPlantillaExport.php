<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Plantilla para cargar los cursos de una malla de institución externa sin IA.
 *
 * Tres columnas y no las ocho de la plantilla USIL: de un curso externo solo se
 * guardan código, nombre y créditos. Pedir ciclo, horas o carácter sería pedir
 * datos que se tiran, que es la forma más rápida de que la gente abandone la
 * plantilla y vuelva a mandar el PDF.
 */
class MallaExternaPlantillaExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            new PlantillaCursosExternosSheet,
            new PlantillaCursosExternosInstruccionesSheet,
        ];
    }
}

/** Hoja 1: datos a llenar. */
class PlantillaCursosExternosSheet implements FromArray, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Cursos';
    }

    public function headings(): array
    {
        return ['codigo', 'nombre', 'creditos'];
    }

    public function array(): array
    {
        // Filas de ejemplo (elimínelas y agregue los cursos de la malla oficial).
        return [
            ['MAT101', 'Cálculo I', 4],
            ['PRG101', 'Fundamentos de Programación', 3],
            ['', 'Seminario de investigación (sin código ni créditos)', ''],
        ];
    }
}

/** Hoja 2: instrucciones y validaciones. */
class PlantillaCursosExternosInstruccionesSheet implements FromArray, WithHeadings, WithTitle
{
    public function title(): string
    {
        return 'Instrucciones';
    }

    public function headings(): array
    {
        return ['Plantilla de cursos de malla externa — USIL Convalidaciones'];
    }

    public function array(): array
    {
        return array_map(fn ($l) => [$l], [
            '',
            'Cómo usar esta plantilla:',
            '1) Vaya a la hoja "Cursos".',
            '2) Borre las filas de ejemplo y agregue una fila por cada curso de la malla oficial.',
            '3) Guarde el archivo y súbalo con el botón "Subir Excel de cursos".',
            '',
            'Columnas (no cambie los nombres de la fila de cabecera):',
            'codigo     (opcional)    Código del curso en su institución. Máx. 30 caracteres.',
            'nombre     (OBLIGATORIO) Nombre del curso tal como figura en la malla. Máx. 200 caracteres.',
            'creditos   (opcional)    Número mayor o igual a 0 (admite decimales, ej. 2.5).',
            '',
            'Reglas:',
            '- Transcriba el nombre tal como aparece en la malla oficial, sin abreviarlo.',
            '- Las filas sin nombre se omiten y se le indicará el número de línea para corregirlas.',
            '- El código y los créditos pueden quedar vacíos si la malla no los indica.',
            '- Antes de guardar podrá revisar en pantalla la lista completa.',
            '',
            'El PDF de la malla oficial se sigue adjuntando: esta plantilla es su transcripción,',
            'no su reemplazo.',
        ]);
    }
}
