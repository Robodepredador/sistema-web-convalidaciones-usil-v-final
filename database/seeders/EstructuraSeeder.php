<?php

namespace Database\Seeders;

use App\Models\Modalidad;
use App\Models\UnidadNegocio;
use Illuminate\Database\Seeder;

/**
 * Datos base de la Estructura Institucional: sedes (código y dirección) y
 * modalidades de estudio. Los Programas de Estudios se cargan aparte en
 * UsilPregradoSeeder; los Planes de Estudios y Mallas Curriculares se dan
 * de alta manualmente desde la UI.
 *
 * Ejecutar: php artisan db:seed --class=EstructuraSeeder
 */
class EstructuraSeeder extends Seeder
{
    public function run(): void
    {
        // --- Sedes: completar código y dirección de las existentes ---
        $sedes = [
            ['USIL Lima', 'SEDE-LIMA', 'Av. La Fontana 550, La Molina, Lima'],
            ['USIL Virtual', 'SEDE-VIRT', 'Plataforma de educación a distancia'],
        ];
        foreach ($sedes as [$nombre, $codigo, $direccion]) {
            UnidadNegocio::updateOrCreate(['nombre' => $nombre], ['codigo' => $codigo, 'direccion' => $direccion]);
        }
        // Cualquier otra sede sin código recibe uno generado.
        UnidadNegocio::whereNull('codigo')->get()->each(function (UnidadNegocio $s) {
            $s->update(['codigo' => 'SEDE-'.str_pad((string) $s->id, 3, '0', STR_PAD_LEFT)]);
        });

        // --- Modalidades ---
        foreach ([['PRE', 'Presencial'], ['SEM', 'Semipresencial'], ['VIRT', 'Virtual']] as [$codigo, $nombre]) {
            Modalidad::updateOrCreate(['codigo' => $codigo], ['nombre' => $nombre, 'activo' => true]);
        }

        $this->command->info('Estructura sembrada: 2 sedes con código y 3 modalidades.');
    }
}
