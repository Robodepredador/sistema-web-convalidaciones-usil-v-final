<?php

namespace Database\Seeders;

use App\Models\CursoNoConvalidable;
use App\Models\CursoUsil;
use App\Services\ConvalidacionEngine;
use Illuminate\Database\Seeder;

/**
 * Configura la política de convalidación:
 *   1. Marca como NO convalidables ciertos cursos USIL (destino) en todas las carreras.
 *   2. Siembra la lista de materias de ORIGEN que nunca se convalidan.
 */
class CursosNoConvalidablesSeeder extends Seeder
{
    public function run(): void
    {
        $engine = app(ConvalidacionEngine::class);

        // 1) Cursos USIL que no se ofrecen como destino de convalidación.
        $patronesUsil = ['english', 'ingles', 'inglés', 'proyecto para computación', 'proyecto para computacion'];
        $marcados = 0;
        foreach (CursoUsil::all() as $curso) {
            $n = $engine->normaliza($curso->nombre);
            foreach ($patronesUsil as $p) {
                if (str_contains($n, $engine->normaliza($p))) {
                    $curso->update(['convalidable' => false]);
                    $marcados++;
                    break;
                }
            }
        }
        $this->command?->info("✓ {$marcados} cursos USIL marcados como no convalidables (Inglés, Proyecto para computación).");

        // 2) Materias de origen que nunca se convalidan (lista gestionable).
        $lista = [
            ['Química', 'Ciencia básica no aplicable al plan'],
            ['Química General', 'Ciencia básica'],
            ['Química Aplicada', 'Ciencia básica'],
            ['Laboratorio de Química', 'Laboratorio de ciencia básica'],
            ['Física', 'Ciencia básica no aplicable al plan'],
            ['Física I', 'Mecánica'],
            ['Física II', 'Electricidad y Magnetismo'],
            ['Física III', 'Ondas y Termodinámica'],
            ['Mecánica', 'Física'],
            ['Electricidad y Magnetismo', 'Física'],
            ['Ondas y Termodinámica', 'Física'],
            ['Laboratorio de Física', 'Laboratorio de ciencia básica'],
            ['Física para Computación', 'Ciencia básica'],
            ['Optimización', 'No forma parte del plan de convalidación'],
            ['Investigación Operativa', 'No aplicable al plan'],
            ['Electrónica Digital', 'Hardware/electrónica'],
            ['Electrónica Analógica', 'Hardware/electrónica'],
            ['Diseño de Circuitos', 'Hardware/electrónica'],
            ['Hacking Ético', 'Especialización no convalidable'],
            ['Pentesting', 'Especialización no convalidable'],
            ['Prácticas Preprofesionales', 'Prácticas'],
            ['Metodología de la Investigación Científica', 'Investigación'],
        ];

        foreach ($lista as [$palabra, $motivo]) {
            CursoNoConvalidable::updateOrCreate(
                ['clave_normalizada' => $engine->normaliza($palabra)],
                ['palabra_clave' => $palabra, 'motivo' => $motivo, 'activo' => true],
            );
        }
        CursoNoConvalidable::limpiarCache();
        $this->command?->info('✓ '.count($lista).' materias de origen sembradas en la lista de no convalidables.');
    }
}
