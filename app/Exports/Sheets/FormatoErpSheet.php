<?php

namespace App\Exports\Sheets;

use App\Models\Simulacion;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Hoja "Formato ERP": tabla plana lista para importar la convalidación al ERP
 * (una fila por curso convalidado, con códigos y datos del alumno).
 */
class FormatoErpSheet implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    private const NAVY = '1F2A44';

    private int $nFilas = 0;

    public function __construct(private Simulacion $s) {}

    public function title(): string
    {
        return 'Formato ERP';
    }

    public function columnWidths(): array
    {
        return ['A' => 16, 'B' => 16, 'C' => 8, 'D' => 14, 'E' => 40, 'F' => 10, 'G' => 40, 'H' => 8];
    }

    public function array(): array
    {
        $s = $this->s;
        $convalidados = $s->detalles->filter(fn ($d) => $d->clasificacion === 'convalidable' && ! $d->excluido);
        $this->nFilas = $convalidados->count();

        $filas = [
            ['Codigo_Alumno', 'Documento', 'Ciclo', 'Codigo_USIL', 'Curso_USIL', 'Creditos', 'Curso_Convalidado', 'Nota'],
        ];

        foreach ($convalidados as $d) {
            $filas[] = [
                $s->postulante?->codigo ?? '',
                "{$s->tipo_documento} {$s->numero_documento}",
                $d->cursoUsil?->ciclo?->numero,
                $d->cursoUsil?->codigo,
                $d->cursoUsil?->nombre,
                (float) $d->creditos_reconocidos,
                $d->nombre_origen,
                $d->nota_origen,
            ];
        }

        return $filas;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $sheet->getStyle('A1:H1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::NAVY);
                $sheet->getStyle('A1:H1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A1:H'.(1 + $this->nFilas))->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
            },
        ];
    }
}
