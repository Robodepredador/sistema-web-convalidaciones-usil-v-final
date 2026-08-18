<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retira «Plan de Estudios», una entidad que nunca llegó a funcionar.
 *
 * Se concibió como el nivel intermedio entre el Programa Académico y la Malla
 * Curricular. Se construyó entera —tabla, modelo, controlador, dos pantallas y
 * la clave foránea en `mallas_curriculares`— y quedó inerte de punta a punta:
 *
 *   - La pantalla que la gestionaba NUNCA tuvo ruta: era inalcanzable desde el
 *     primer commit (se retiró el 18/08 junto con su controlador).
 *   - `mallas_curriculares.plan_estudio_id` no se escribe en ningún punto del
 *     código: es NULL en el 100% de las filas y lo seguiría siendo.
 *   - `planes_estudio` no la puebla ningún seeder, así que una instalación
 *     nueva la deja vacía para siempre.
 *
 * En la práctica la malla cuelga directamente de la carrera, que es como
 * funciona el proceso real. La entidad intermedia no aportaba nada y sí
 * confundía: aparecía en el diccionario de datos y en el modelo entidad-relación
 * como si formara parte del flujo.
 *
 * Ojo con el nombre: `CargarPlanEstudiosJsonSeeder` NO se retira. Pese a
 * llamarse así, no toca esta tabla —carga mallas, ciclos y cursos desde
 * `database/data/plan_estudios.json`— y sigue siendo útil.
 *
 * `down()` recrea la estructura, no los datos: no había ninguno que perder.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('mallas_curriculares', 'plan_estudio_id')) {
            Schema::table('mallas_curriculares', function (Blueprint $table) {
                $table->dropConstrainedForeignId('plan_estudio_id');
            });
        }

        Schema::dropIfExists('planes_estudio');
    }

    public function down(): void
    {
        Schema::create('planes_estudio', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 30)->unique();
            $table->foreignId('carrera_id')->constrained('carreras');
            $table->foreignId('modalidad_id')->constrained('modalidades');
            $table->string('nombre', 150);
            $table->year('anio');
            $table->string('version', 20);
            $table->boolean('activo')->default(true);
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['carrera_id', 'anio', 'version']);
            $table->engine = 'InnoDB';
        });

        Schema::table('mallas_curriculares', function (Blueprint $table) {
            $table->foreignId('plan_estudio_id')->nullable()->after('carrera_id')
                ->constrained('planes_estudio')->nullOnDelete();
        });
    }
};
