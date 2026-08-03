<?php

namespace Database\Seeders;

use App\Models\InstitucionExterna;
use App\Models\TipoInstitucion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Asigna a cada institución externa un catálogo estándar de carreras de
 * procedencia (universidades / institutos). Idempotente e inserción por lote.
 *
 * Nota: SUNEDU licencia universidades; no hay una lista única descargable de
 * carreras por universidad, por lo que se usa el catálogo nacional común y el
 * coordinador puede ajustar las carreras de cada institución.
 *
 * Ejecutar: php artisan db:seed --class=CarrerasExternasSeeder
 */
class CarrerasExternasSeeder extends Seeder
{
    public function run(): void
    {
        $catUniversidad = [
            'Administración', 'Administración de Negocios Internacionales', 'Arquitectura', 'Biología',
            'Ciencias de la Comunicación', 'Contabilidad', 'Derecho', 'Economía', 'Educación', 'Enfermería',
            'Estadística', 'Farmacia y Bioquímica', 'Física', 'Gastronomía y Gestión Culinaria',
            'Ingeniería Agroindustrial', 'Ingeniería Ambiental', 'Ingeniería Civil', 'Ingeniería de Minas',
            'Ingeniería de Sistemas', 'Ingeniería de Software', 'Ingeniería Electrónica', 'Ingeniería Industrial',
            'Ingeniería Mecánica', 'Ingeniería Química', 'Marketing', 'Matemática', 'Medicina Humana',
            'Medicina Veterinaria', 'Nutrición', 'Obstetricia', 'Odontología', 'Psicología', 'Química',
            'Sociología', 'Trabajo Social', 'Turismo y Hotelería',
        ];

        $catInstituto = [
            'Administración de Empresas', 'Administración Bancaria', 'Computación e Informática', 'Contabilidad',
            'Diseño Gráfico', 'Electrónica Industrial', 'Enfermería Técnica', 'Gastronomía y Arte Culinario',
            'Gestión Logística', 'Marketing', 'Mecatrónica Automotriz', 'Producción Agropecuaria',
            'Redes y Comunicaciones', 'Secretariado Ejecutivo',
        ];

        $tipoUniv = TipoInstitucion::where('nombre', 'Universidad')->value('id');
        $ahora = now();
        $totalNuevas = 0;

        InstitucionExterna::with('tipo')->chunk(50, function ($instituciones) use ($catUniversidad, $catInstituto, $tipoUniv, $ahora, &$totalNuevas) {
            foreach ($instituciones as $inst) {
                $catalogo = $inst->tipo_id === $tipoUniv ? $catUniversidad : $catInstituto;

                // Evita duplicados (comparación sin distinción de may/min).
                $existentes = $inst->carreras()->pluck('nombre')
                    ->map(fn ($n) => mb_strtolower($n))->all();

                $nuevas = [];
                foreach ($catalogo as $nombre) {
                    if (! in_array(mb_strtolower($nombre), $existentes, true)) {
                        $nuevas[] = [
                            'institucion_id' => $inst->id,
                            'nombre' => $nombre,
                            'created_at' => $ahora,
                            'updated_at' => $ahora,
                        ];
                    }
                }

                if ($nuevas) {
                    DB::table('carreras_externas')->insert($nuevas);
                    $totalNuevas += count($nuevas);
                }
            }
        });

        $this->command->info("Carreras de procedencia asignadas: {$totalNuevas} nuevas en ".InstitucionExterna::count().' instituciones.');
    }
}
