<?php

use App\Models\CursoNoConvalidable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * La política de cursos no convalidables pasa a vivir solo en la base de datos.
 *
 * Estaba partida en dos: una tabla gestionable desde Configuración y una lista
 * fija dentro de ConvalidacionEngine que no se podía consultar ni desactivar
 * sin desplegar código. Se traslada la lista fija a la tabla; las claves
 * redundantes (plurales y variantes) se omiten porque la comparación pasó a
 * admitir el plural y a exigir palabra completa.
 */
return new class extends Migration
{
    /** clave normalizada => [etiqueta legible, motivo] */
    private const CLAVES = [
        'ingles' => ['Inglés', 'Idiomas'],
        'english' => ['English', 'Idiomas'],
        'idioma extranjero' => ['Idioma extranjero', 'Idiomas'],
        'comunicacion en ingles' => ['Comunicación en inglés', 'Idiomas'],
        'informatica' => ['Informática', 'Informática básica'],
        'computacion basica' => ['Computación básica', 'Informática básica'],
        'tecnologias de la informacion' => ['Tecnologías de la información', 'Informática básica'],
        'ofimatica' => ['Ofimática', 'Informática básica'],
        'office' => ['Office', 'Informática básica'],
        'herramientas ofimaticas' => ['Herramientas ofimáticas', 'Informática básica'],
        'microsoft office' => ['Microsoft Office', 'Informática básica'],
        'investigacion cientifica' => ['Investigación científica', 'Investigación'],
        'metodologia de la investigacion' => ['Metodología de la investigación', 'Investigación'],
        'seminario de investigacion' => ['Seminario de investigación', 'Investigación'],
        'taller de investigacion' => ['Taller de investigación', 'Investigación'],
        'practica' => ['Práctica', 'Prácticas'],
        'practica profesional' => ['Práctica profesional', 'Prácticas'],
        'practica preprofesional' => ['Práctica preprofesional', 'Prácticas'],
        'fisica' => ['Física', 'Ciencia básica'],
        'fisica general' => ['Física general', 'Ciencia básica'],
        'fisica mecanica' => ['Física mecánica', 'Ciencia básica'],
        'cultura fisica' => ['Cultura física', 'Deportes y cultura física'],
        'deportes' => ['Deportes', 'Deportes y cultura física'],
        'educacion fisica' => ['Educación física', 'Deportes y cultura física'],
        'actividades deportivas' => ['Actividades deportivas', 'Deportes y cultura física'],
        'mantenimiento de equipos' => ['Mantenimiento de equipos', 'Mantenimiento y reparación'],
        'mantenimiento de computadoras' => ['Mantenimiento de computadoras', 'Mantenimiento y reparación'],
        'mantenimiento de hardware' => ['Mantenimiento de hardware', 'Mantenimiento y reparación'],
        'reparacion de equipos' => ['Reparación de equipos', 'Mantenimiento y reparación'],
        'reparacion de computadoras' => ['Reparación de computadoras', 'Mantenimiento y reparación'],
        'reparacion de hardware' => ['Reparación de hardware', 'Mantenimiento y reparación'],
        'arte' => ['Arte', 'Arte y cultura'],
        'cultura artistica' => ['Cultura artística', 'Arte y cultura'],
        'actividades artisticas' => ['Actividades artísticas', 'Arte y cultura'],
        'apreciacion artistica' => ['Apreciación artística', 'Arte y cultura'],
        'diseno grafico' => ['Diseño gráfico', 'Diseño'],
        'diseno asistido' => ['Diseño asistido', 'Diseño'],
        'ilustracion digital' => ['Ilustración digital', 'Diseño'],
        'insercion laboral' => ['Inserción laboral', 'Empleabilidad'],
        'empleabilidad' => ['Empleabilidad', 'Empleabilidad'],
        'desarrollo profesional' => ['Desarrollo profesional', 'Empleabilidad'],
        'orientacion laboral' => ['Orientación laboral', 'Empleabilidad'],
        'preparacion para el empleo' => ['Preparación para el empleo', 'Empleabilidad'],
    ];

    public function up(): void
    {
        $existentes = DB::table('cursos_no_convalidables')->pluck('clave_normalizada')->all();
        $ahora = now();

        $nuevas = [];
        foreach (self::CLAVES as $clave => [$etiqueta, $motivo]) {
            if (in_array($clave, $existentes, true)) {
                continue;
            }
            $nuevas[] = [
                'palabra_clave' => $etiqueta,
                'clave_normalizada' => $clave,
                'motivo' => $motivo,
                'activo' => true,
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        if ($nuevas !== []) {
            DB::table('cursos_no_convalidables')->insert($nuevas);
        }

        // La lista se cachea para siempre y esta inserción no pasa por el modelo.
        CursoNoConvalidable::limpiarCache();
    }

    public function down(): void
    {
        // Solo las institucionales que traía el código: las reglas propias de
        // una carrera no las puso esta migración y no le corresponde borrarlas.
        DB::table('cursos_no_convalidables')
            ->whereIn('clave_normalizada', array_keys(self::CLAVES))
            ->whereNull('carrera_id')
            ->delete();

        CursoNoConvalidable::limpiarCache();
    }
};
