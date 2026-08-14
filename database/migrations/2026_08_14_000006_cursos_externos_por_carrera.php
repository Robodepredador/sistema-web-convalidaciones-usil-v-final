<?php

use App\Services\ConvalidacionEngine;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los cursos externos cuelgan de la carrera externa, no de la malla.
 * El mismo curso sirve sin importar de qué versión de plan provenga; atarlo a
 * la malla obligaba al especialista a repetir el registro por cada versión.
 *
 * Elegimos una columna materializada mantenida por un observer para nombre_normalizado
 * en lugar de una columna generada de MySQL, porque la normalización completa
 * (quitar puntuación) no se puede expresar puramente en SQL.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Comprobación previa (obligatoria)
        $duplicados = DB::select('
            SELECT me.carrera_externa_id, ce.nombre, COUNT(*) as c
            FROM cursos_externos ce
            JOIN mallas_externas me ON ce.malla_externa_id = me.id
            GROUP BY me.carrera_externa_id, ce.nombre
            HAVING c > 1
        ');

        if (! empty($duplicados)) {
            $nombres = collect($duplicados)->map(fn ($d) => $d->nombre)->implode(', ');
            throw new RuntimeException('Existen cursos duplicados por carrera externa. Deduplicar primero. Cursos: '.$nombres);
        }

        // 2 y 3. Añadir carrera_externa_id nullable y nombre_normalizado
        Schema::table('cursos_externos', function (Blueprint $table) {
            if (! Schema::hasColumn('cursos_externos', 'carrera_externa_id')) {
                $table->unsignedBigInteger('carrera_externa_id')->nullable()->after('malla_externa_id');
            }
            if (! Schema::hasColumn('cursos_externos', 'nombre_normalizado')) {
                $table->string('nombre_normalizado')->nullable()->after('nombre');
            }
        });

        // Poblar carrera_externa_id y nombre_normalizado
        $engine = new ConvalidacionEngine;
        $cursos = DB::table('cursos_externos')->get();
        foreach ($cursos as $curso) {
            $malla = DB::table('mallas_externas')->where('id', $curso->malla_externa_id)->first();
            $carrera_id = $malla ? $malla->carrera_externa_id : null;
            $normalizado = $engine->normaliza($curso->nombre);

            DB::table('cursos_externos')->where('id', $curso->id)->update([
                'carrera_externa_id' => $carrera_id,
                'nombre_normalizado' => $normalizado,
            ]);
        }

        // 4. carrera_externa_id a NOT NULL con su FK
        Schema::table('cursos_externos', function (Blueprint $table) {
            $table->unsignedBigInteger('carrera_externa_id')->nullable(false)->change();
            $table->foreign('carrera_externa_id', 'fk_curso_externo_carrera')->references('id')->on('carreras_externas')->cascadeOnDelete();
        });

        // 5. malla_externa_id a NULLABLE con ON DELETE SET NULL, retirar UNIQUE viejo
        Schema::table('cursos_externos', function (Blueprint $table) {
            $table->dropForeign(['malla_externa_id']);
            $table->dropUnique('uq_curso_externo_malla_nombre');
        });

        Schema::table('cursos_externos', function (Blueprint $table) {
            $table->unsignedBigInteger('malla_externa_id')->nullable()->change();
            $table->foreign('malla_externa_id')->references('id')->on('mallas_externas')->nullOnDelete();

            // 6. UNIQUE (carrera_externa_id, nombre_normalizado) y UNIQUE (id, carrera_externa_id)
            $table->unique(['carrera_externa_id', 'nombre_normalizado'], 'uq_curso_externo_carrera_nombre');
            $table->unique(['id', 'carrera_externa_id'], 'uq_curso_externo_id_carrera');
        });
    }

    public function down(): void
    {
        Schema::table('cursos_externos', function (Blueprint $table) {
            $table->dropForeign(['malla_externa_id']);
            $table->dropForeign('fk_curso_externo_carrera');
        });

        Schema::table('cursos_externos', function (Blueprint $table) {
            $table->dropUnique('uq_curso_externo_carrera_nombre');
            $table->dropUnique('uq_curso_externo_id_carrera');
        });

        Schema::table('cursos_externos', function (Blueprint $table) {
            $table->unsignedBigInteger('malla_externa_id')->nullable(false)->change();
            $table->foreign('malla_externa_id')->references('id')->on('mallas_externas')->cascadeOnDelete();
            $table->unique(['malla_externa_id', 'nombre'], 'uq_curso_externo_malla_nombre');

            $table->dropColumn('carrera_externa_id');
            $table->dropColumn('nombre_normalizado');
        });
    }
};
