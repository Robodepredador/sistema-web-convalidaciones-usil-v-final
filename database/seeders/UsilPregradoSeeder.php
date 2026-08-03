<?php

namespace Database\Seeders;

use App\Models\Carrera;
use App\Models\Facultad;
use App\Models\UnidadNegocio;
use Illuminate\Database\Seeder;

/**
 * Carga las facultades y carreras (programas académicos) de PREGRADO de USIL,
 * según el listado oficial "Carrera de interés" en https://usil.edu.pe/pregrado/
 * (9 facultades, 40 carreras — validado 2026-07-13, incluye la nueva Facultad
 * de Ingeniería e Inteligencia Artificial).
 *
 * Idempotente: si una carrera ya existe por nombre, solo actualiza su facultad
 * (preserva su código y relaciones, p. ej. ISI). Las nuevas se crean con un
 * código generado. Ejecutar: php artisan db:seed --class=UsilPregradoSeeder
 */
class UsilPregradoSeeder extends Seeder
{
    public function run(): void
    {
        $sede = UnidadNegocio::firstOrCreate(['nombre' => 'USIL Lima'], ['codigo' => 'SEDE-LIMA']);

        // [codigo facultad, nombre facultad, [ [nombre carrera, título profesional, ciclos], ... ] ]
        $estructura = [
            ['FIN', 'Facultad de Ingeniería e Inteligencia Artificial', [
                ['Ingeniería Agroindustrial y Negocios Internacionales', 'Ingeniero Agroindustrial', 10],
                ['Ingeniería Ambiental', 'Ingeniero Ambiental', 10],
                ['Ingeniería Biomédica', 'Ingeniero Biomédico', 10],
                ['Ingeniería Civil', 'Ingeniero Civil', 10],
                ['Ingeniería Empresarial', 'Ingeniero Empresarial', 10],
                ['Ingeniería en Ciberseguridad', 'Ingeniero en Ciberseguridad', 10],
                ['Ingeniería en Industrias Alimentarias', 'Ingeniero en Industrias Alimentarias', 10],
                ['Ingeniería Industrial y Comercial', 'Ingeniero Industrial y Comercial', 10],
                ['Ingeniería Mecatrónica', 'Ingeniero Mecatrónico', 10],
                ['Ingeniería de Sistemas de Información', 'Ingeniero de Sistemas de Información', 10],
                ['Ingeniería de Software', 'Ingeniero de Software', 10],
                ['Ingeniería en Inteligencia Artificial', 'Ingeniero en Inteligencia Artificial', 10],
                ['Ciencia de Datos', 'Licenciado en Ciencia de Datos', 10],
                ['Ciencia de Datos e Inteligencia Artificial', 'Licenciado en Ciencia de Datos e Inteligencia Artificial', 10],
            ]],
            ['FCE', 'Facultad de Ciencias Empresariales', [
                ['Administración', 'Licenciado en Administración', 10],
                ['Administración y Emprendimiento', 'Licenciado en Administración y Emprendimiento', 10],
                ['Administración y Finanzas Corporativas', 'Licenciado en Administración y Finanzas Corporativas', 10],
                ['Digital Business Management', 'Licenciado en Digital Business Management', 10],
                ['Economía y Finanzas', 'Economista', 10],
                ['Economía y Negocios Internacionales', 'Economista', 10],
                ['International Business', 'Licenciado en International Business', 10],
                ['Marketing', 'Licenciado en Marketing', 10],
            ]],
            ['FCS', 'Facultad de Ciencias de la Salud', [
                ['Ciencias de la Actividad Física y del Deporte', 'Licenciado en Ciencias de la Actividad Física y del Deporte', 10],
                ['Enfermería', 'Licenciado en Enfermería', 10],
                ['Medicina Humana', 'Médico Cirujano', 14],
                ['Nutrición y Dietética', 'Licenciado en Nutrición y Dietética', 10],
                ['Psicología', 'Licenciado en Psicología', 10],
                ['Tecnología Médica en Terapia Física y Rehabilitación', 'Licenciado en Tecnología Médica', 10],
            ]],
            ['FAHTG', 'Facultad de Administración Hotelera, Turismo y Gastronomía', [
                ['Administración Hotelera', 'Licenciado en Administración Hotelera', 10],
                ['Administración en Turismo', 'Licenciado en Administración en Turismo', 10],
                ['Arte Culinario', 'Licenciado en Arte Culinario', 10],
                ['Gestión e Innovación en Gastronomía', 'Licenciado en Gestión e Innovación en Gastronomía', 10],
            ]],
            ['FAYH', 'Facultad de Artes y Humanidades', [
                ['Arte y Diseño Empresarial', 'Licenciado en Arte y Diseño Empresarial', 10],
                ['Música', 'Licenciado en Música', 10],
            ]],
            ['FDER', 'Facultad de Derecho', [
                ['Derecho', 'Abogado', 12],
                ['Relaciones Internacionales', 'Licenciado en Relaciones Internacionales', 10],
            ]],
            ['FEDU', 'Facultad de Educación', [
                ['Educación Inicial', 'Licenciado en Educación Inicial', 10],
                ['Educación Secundaria con Especialidad en Inglés', 'Licenciado en Educación Secundaria', 10],
            ]],
            ['FCOM', 'Facultad de Comunicación', [
                ['Comunicaciones', 'Licenciado en Comunicaciones', 10],
            ]],
            ['FARQ', 'Facultad de Arquitectura', [
                ['Arquitectura, Urbanismo y Territorio', 'Arquitecto', 10],
            ]],
        ];

        $totalCarreras = 0;

        foreach ($estructura as [$facCodigo, $facNombre, $carreras]) {
            $facultad = Facultad::updateOrCreate(
                ['codigo' => $facCodigo],
                ['unidad_negocio_id' => $sede->id, 'nombre' => $facNombre, 'activo' => true]
            );

            $i = 0;
            foreach ($carreras as [$nombre, , $ciclos]) {
                $i++;
                $existente = Carrera::where('nombre', $nombre)->first();

                if ($existente) {
                    // Preserva el código y las relaciones; solo reubica bajo su facultad.
                    $existente->update(['facultad_id' => $facultad->id, 'activo' => true]);
                } else {
                    Carrera::create([
                        'codigo' => $facCodigo.'-'.str_pad((string) $i, 2, '0', STR_PAD_LEFT),
                        'facultad_id' => $facultad->id,
                        'nombre' => $nombre,
                        'max_ciclos' => $ciclos,
                        'activo' => true,
                    ]);
                }
                $totalCarreras++;
            }
        }

        $this->command->info("Pregrado USIL: 9 facultades y {$totalCarreras} carreras cargadas en la Sede USIL Lima.");
    }
}
