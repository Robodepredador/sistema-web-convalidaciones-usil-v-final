<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Histórico de equivalencias para llevarlo impreso como guía de evaluación.
 */
class HistorialEquivalenciasExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(private Collection $filas) {}

    public function collection(): Collection
    {
        return $this->filas;
    }

    public function headings(): array
    {
        return [
            'Curso de origen', 'Institución de origen', 'Carrera de origen',
            'Código USIL', 'Convalidado con (USIL)', 'Carrera USIL destino',
            'Veces', 'Con memorándum confirmado',
        ];
    }

    public function map($fila): array
    {
        return [
            $fila->origen_nombre,
            $fila->institucion,
            $fila->carrera_externa,
            $fila->codigo_usil,
            $fila->curso_usil,
            $fila->carrera_usil,
            (int) $fila->veces,
            (int) $fila->confirmadas,
        ];
    }
}
