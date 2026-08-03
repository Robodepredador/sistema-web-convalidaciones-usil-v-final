<?php

namespace App\Exports\Sheets;

use App\Models\Simulacion;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/** Hoja de cursos NO convalidados (no convalidables + desaprobados). */
class NoConvalidadosSheet implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    private const NAVY = '1F2A44';

    public function __construct(private Simulacion $s) {}

    public function title(): string
    {
        return 'Cursos no convalidados';
    }

    public function columnWidths(): array
    {
        return ['A' => 45, 'B' => 10, 'C' => 10, 'D' => 30];
    }

    public function array(): array
    {
        $noConv = $this->s->detalles->filter(fn ($d) => $d->clasificacion === 'no_convalidable');
        $desap = $this->s->detalles->filter(fn ($d) => $d->clasificacion === 'desaprobado');

        $filas = [
            ['Cursos no considerados para convalidación'],
            [],
            ['Curso de origen', 'Nota', 'Créditos', 'Motivo'],
        ];

        foreach ($noConv as $d) {
            $filas[] = [$d->nombre_origen, $d->nota_origen, $d->creditos_origen !== null ? (float) $d->creditos_origen : '', 'No convalidable'];
        }
        foreach ($desap as $d) {
            $filas[] = [$d->nombre_origen, $d->nota_origen, $d->creditos_origen !== null ? (float) $d->creditos_origen : '', 'Desaprobado'];
        }
        if ($noConv->isEmpty() && $desap->isEmpty()) {
            $filas[] = ['Sin cursos descartados.', '', '', ''];
        }

        return $filas;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $ultima = $sheet->getHighestRow();

                $sheet->mergeCells('A1:D1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12);

                // Encabezado de la tabla (col A = "Curso de origen").
                $hr = null;
                for ($r = 1; $r <= $ultima; $r++) {
                    if ($sheet->getCell("A{$r}")->getValue() === 'Curso de origen') {
                        $hr = $r;
                        break;
                    }
                }
                if (! $hr) {
                    return;
                }

                $sheet->getStyle("A{$hr}:D{$hr}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::NAVY);
                $sheet->getStyle("A{$hr}:D{$hr}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle("A{$hr}:D{$ultima}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("B{$hr}:C{$ultima}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}
