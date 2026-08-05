<?php

use App\Models\CursoNoConvalidable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Deja legible el nombre de las reglas institucionales.
 *
 * Al traer la política del código a la base de datos se guardó como etiqueta la
 * clave ya normalizada, que es la forma con la que se compara: sin tildes y en
 * minúscula. En la pantalla del plan de estudios eso se lee «diseno grafico».
 * La clave de comparación no cambia; solo el texto que se muestra.
 */
return new class extends Migration
{
    /** clave normalizada => etiqueta legible */
    private const ETIQUETAS = [
        'ingles' => 'Inglés',
        'idioma extranjero' => 'Idioma extranjero',
        'comunicacion en ingles' => 'Comunicación en inglés',
        'informatica' => 'Informática',
        'computacion basica' => 'Computación básica',
        'tecnologias de la informacion' => 'Tecnologías de la información',
        'ofimatica' => 'Ofimática',
        'herramientas ofimaticas' => 'Herramientas ofimáticas',
        'microsoft office' => 'Microsoft Office',
        'investigacion cientifica' => 'Investigación científica',
        'metodologia de la investigacion' => 'Metodología de la investigación',
        'seminario de investigacion' => 'Seminario de investigación',
        'taller de investigacion' => 'Taller de investigación',
        'practica' => 'Práctica',
        'practica profesional' => 'Práctica profesional',
        'practica preprofesional' => 'Práctica preprofesional',
        'fisica' => 'Física',
        'fisica general' => 'Física general',
        'fisica mecanica' => 'Física mecánica',
        'cultura fisica' => 'Cultura física',
        'educacion fisica' => 'Educación física',
        'actividades deportivas' => 'Actividades deportivas',
        'mantenimiento de equipos' => 'Mantenimiento de equipos',
        'mantenimiento de computadoras' => 'Mantenimiento de computadoras',
        'mantenimiento de hardware' => 'Mantenimiento de hardware',
        'reparacion de equipos' => 'Reparación de equipos',
        'reparacion de computadoras' => 'Reparación de computadoras',
        'reparacion de hardware' => 'Reparación de hardware',
        'cultura artistica' => 'Cultura artística',
        'actividades artisticas' => 'Actividades artísticas',
        'apreciacion artistica' => 'Apreciación artística',
        'diseno grafico' => 'Diseño gráfico',
        'diseno asistido' => 'Diseño asistido',
        'ilustracion digital' => 'Ilustración digital',
        'insercion laboral' => 'Inserción laboral',
        'desarrollo profesional' => 'Desarrollo profesional',
        'orientacion laboral' => 'Orientación laboral',
        'preparacion para el empleo' => 'Preparación para el empleo',
    ];

    public function up(): void
    {
        foreach (self::ETIQUETAS as $clave => $etiqueta) {
            DB::table('cursos_no_convalidables')
                ->where('clave_normalizada', $clave)
                ->whereNull('carrera_id')
                // Solo las que quedaron con la clave como etiqueta: si alguien ya
                // le puso un nombre propio, se respeta.
                ->where('palabra_clave', $clave)
                ->update(['palabra_clave' => $etiqueta]);
        }

        CursoNoConvalidable::limpiarCache();
    }

    public function down(): void
    {
        foreach (self::ETIQUETAS as $clave => $etiqueta) {
            DB::table('cursos_no_convalidables')
                ->where('clave_normalizada', $clave)
                ->whereNull('carrera_id')
                ->where('palabra_clave', $etiqueta)
                ->update(['palabra_clave' => $clave]);
        }

        CursoNoConvalidable::limpiarCache();
    }
};
