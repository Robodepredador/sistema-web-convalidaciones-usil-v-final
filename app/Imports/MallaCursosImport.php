<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Lee el Excel de cursos a una colección normalizada.
 * Columnas esperadas (heading row): ciclo, codigo, nombre, creditos.
 *
 * El archivo puede tener varias hojas (p.ej. "Cursos" + "Instrucciones").
 * Sin WithMultipleSheets, collection() se invoca para CADA hoja y la última
 * sobreescribe las filas de la primera, por lo que $filas terminaba con el
 * contenido de "Instrucciones" en vez de los cursos reales.
 *
 * Con WithMultipleSheets solo se procesa la hoja "Cursos" (índice 0) y las
 * demás se ignoran.
 */
class MallaCursosImport implements WithMultipleSheets
{
    /** @var CursosSheetImport  Importador de la hoja de cursos. */
    private CursosSheetImport $cursosSheet;

    public function __construct()
    {
        $this->cursosSheet = new CursosSheetImport;
    }

    public function sheets(): array
    {
        return [
            // Índice 0 → primera hoja ("Cursos").
            0 => $this->cursosSheet,
        ];
    }

    /**
     * Acceso a las filas leídas de la hoja de cursos.
     * Se accede con $import->filas como antes; al no estar declarada como
     * propiedad propia, __get la delega al sub-importer.
     */
    public function __get(string $name)
    {
        if ($name === 'filas') {
            return $this->cursosSheet->filas;
        }

        throw new \RuntimeException("Propiedad inexistente: {$name}");
    }
}

/**
 * Importador de la hoja individual de cursos.
 */
class CursosSheetImport implements ToCollection, WithHeadingRow
{
    public Collection $filas;

    public function __construct()
    {
        $this->filas = collect();
    }

    public function collection(Collection $rows): void
    {
        $this->filas = $rows;
    }
}
