<?php

namespace App\Exports\Sheets;

use App\Models\MallaCurricular;
use App\Models\Simulacion;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/** Hoja principal: cabecera institucional + cursos convalidados. */
class PreconvalidacionSheet implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    private const NAVY = '1F2A44';

    public function __construct(private Simulacion $s) {}

    public function title(): string
    {
        return 'Preconvalidación';
    }

    public function columnWidths(): array
    {
        return ['A' => 8, 'B' => 45, 'C' => 48, 'D' => 10];
    }

    public function array(): array
    {
        $s = $this->s;
        $malla = MallaCurricular::find($s->malla_usil_id);

        $fac = $s->carreraUsil?->facultad?->nombre ?? 'Ingeniería';
        $fac = Str::startsWith(mb_strtolower($fac), 'facultad') ? $fac : 'Facultad de '.$fac;

        $convalidados = $s->detalles->filter(fn ($d) => $d->curso_usil_id && ! $d->excluido);

        $filas = [
            [$fac],
            ['Carrera Profesional: '.($s->carreraUsil?->nombre ?? '')],
            ['Plan de Estudios: '.($malla?->anio ?? '')],
            [],
            ['', 'Alumno:', trim("{$s->nombres} {$s->apellidos}")],
            ['', 'Código:', $s->postulante?->codigo ?? '-'],
            ['', 'Año - Semestre de Ingreso:', $s->ciclo_postulacion],
            ['', 'Convalidación - Institución de Procedencia:', $s->universidad_origen ?? $s->postulante?->institucionOrigen?->nombre ?? ''],
            ['', 'Carrera de Procedencia:', $s->carreraExterna?->nombre ?? ''],
            ['', 'Fecha de Revisión:', now()->format('d/m/Y')],
            [],
            ['Ciclo', 'Curso USIL', 'Curso Convalidado', 'Créditos'],
        ];

        $total = 0;
        foreach ($convalidados as $d) {
            $filas[] = [
                $d->cursoUsil?->ciclo?->numero,
                $d->cursoUsil?->nombre,
                $d->nombre_origen,
                (float) $d->creditos_reconocidos,
            ];
            $total += (float) $d->creditos_reconocidos;
        }
        $filas[] = ['', '', 'Total de créditos convalidados', $total];

        return $filas;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $ultima = $sheet->getHighestRow();

                // Título combinado y centrado (siempre en las 3 primeras filas).
                foreach (['A1:D1', 'A2:D2', 'A3:D3'] as $rango) {
                    $sheet->mergeCells($rango);
                }
                $sheet->getStyle('A1:D3')->getFont()->setBold(true)->setSize(12);
                $sheet->getStyle('A1:D3')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

                // Detecta la fila de encabezado de la tabla (col A = "Ciclo"), robusto ante filas en blanco.
                $hr = null;
                for ($r = 4; $r <= $ultima; $r++) {
                    if ($sheet->getCell("A{$r}")->getValue() === 'Ciclo') {
                        $hr = $r;
                        break;
                    }
                }
                if (! $hr) {
                    return;
                }

                // Etiquetas de datos (col B, filas entre la 4 y el encabezado) en negrita.
                for ($r = 4; $r < $hr; $r++) {
                    if (trim((string) $sheet->getCell("B{$r}")->getValue()) !== '') {
                        $sheet->getStyle("B{$r}")->getFont()->setBold(true);
                    }
                }

                // Encabezado de tabla: fondo azul, texto blanco.
                $sheet->getStyle("A{$hr}:D{$hr}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::NAVY);
                $sheet->getStyle("A{$hr}:D{$hr}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');

                // Bordes de toda la tabla + total en negrita/gris.
                $sheet->getStyle("A{$hr}:D{$ultima}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
                $sheet->getStyle("A{$ultima}:D{$ultima}")->getFont()->setBold(true);
                $sheet->getStyle("A{$ultima}:D{$ultima}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F2F2F2');

                // Centrar ciclo y créditos.
                $sheet->getStyle("A{$hr}:A{$ultima}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("D{$hr}:D{$ultima}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            },
        ];
    }
}
