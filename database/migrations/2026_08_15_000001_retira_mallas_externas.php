<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * La «malla externa» era una versión de plan de estudios intercalada entre la
 * carrera externa y sus cursos. Dejó de tener función cuando el especialista
 * pasó a acumular nombres de curso sin importar de qué versión vengan: para
 * decidir si «Algoritmia Básica» convalida con Programación Orientada a
 * Objetos da igual que el estudiante la llevara con la malla de 2019 o la de
 * 2023, y obligar a registrar la equivalencia una vez por versión multiplicaba
 * el trabajo de comparar sílabos sin aportar nada.
 *
 * La pertenencia real ya se trasladó a `carrera_externa_id`. Verificado antes
 * de aplicar: la tabla está vacía y ninguna fila de cursos_externos conserva
 * un valor en malla_externa_id, así que esto retira un andamio, no datos.
 *
 * Se retira además el permiso `mallas_externas.gestionar` de la tabla: dejarlo
 * solo fuera del catálogo PHP lo mantendría concedido en cualquier base ya
 * instalada, que es el defecto que hubo que corregir en la reducción de roles.
 *
 * `down()` recrea la estructura, NO los datos: las mallas externas que hubieran
 * existido no vuelven, y los cursos quedan con malla_externa_id en nulo.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->abortarSiHayDatosQuePerder();

        if (Schema::hasColumn('cursos_externos', 'malla_externa_id')) {
            Schema::table('cursos_externos', function (Blueprint $table) {
                $table->dropForeign(['malla_externa_id']);
                $table->dropColumn('malla_externa_id');
            });
        }

        Schema::dropIfExists('mallas_externas');

        DB::table('permisos')->where('clave', 'mallas_externas.gestionar')->delete();
    }

    public function down(): void
    {
        if (! Schema::hasTable('mallas_externas')) {
            Schema::create('mallas_externas', function (Blueprint $table) {
                $table->id();
                $table->foreignId('carrera_externa_id')->constrained('carreras_externas')->cascadeOnDelete();
                $table->year('anio');
                $table->string('version', 20)->default('1');
                $table->boolean('activa')->default(true);
                $table->string('pdf_path')->nullable();
                $table->timestamps();
                $table->unique(['carrera_externa_id', 'anio', 'version'], 'uq_malla_externa_carrera_anio_version');
            });
        }

        if (! Schema::hasColumn('cursos_externos', 'malla_externa_id')) {
            Schema::table('cursos_externos', function (Blueprint $table) {
                $table->foreignId('malla_externa_id')->nullable()->after('carrera_externa_id')
                    ->constrained('mallas_externas')->nullOnDelete();
            });
        }

        DB::table('permisos')->insertOrIgnore([
            'clave' => 'mallas_externas.gestionar',
            'modulo' => 'Catálogos',
            'descripcion' => 'Registrar mallas oficiales de instituciones externas',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * La deleción solo es inocua mientras nadie haya cargado datos después de
     * la medición. Si los hay, se aborta antes de tocar el esquema: perderlos
     * en silencio sería peor que no migrar.
     */
    private function abortarSiHayDatosQuePerder(): void
    {
        if (! Schema::hasTable('mallas_externas')) {
            return;
        }

        $mallas = DB::table('mallas_externas')->count();

        $cursosAtados = Schema::hasColumn('cursos_externos', 'malla_externa_id')
            ? DB::table('cursos_externos')->whereNotNull('malla_externa_id')->count()
            : 0;

        if ($mallas > 0 || $cursosAtados > 0) {
            throw new RuntimeException(
                "No se puede retirar el módulo de mallas externas: hay {$mallas} malla(s) registrada(s) y ".
                "{$cursosAtados} curso(s) externo(s) todavía atados a una de ellas. Traslade esos cursos a su ".
                'carrera externa antes de reintentar; esta migración no borra datos de nadie por su cuenta.'
            );
        }
    }
};
