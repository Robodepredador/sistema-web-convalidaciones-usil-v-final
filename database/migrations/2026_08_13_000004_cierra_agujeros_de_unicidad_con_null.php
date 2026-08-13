<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * InnoDB considera cada NULL distinto de cualquier otro dentro de un índice
 * único. Dos restricciones del esquema quedaban por eso desactivadas en la
 * práctica, justo en el caso que más importaba:
 *
 *   - mallas_externas: con `version` NULL se podía cargar N veces la misma
 *     malla de la misma carrera y el mismo año.
 *   - cursos_no_convalidables: con `carrera_id` NULL (regla institucional) se
 *     podía repetir indefinidamente la misma palabra clave.
 *
 * Se resuelven distinto porque el problema es distinto. En mallas externas la
 * versión sí tiene un valor por defecto sensato ('1'), así que basta con
 * volverla obligatoria. En no convalidables el NULL es información —significa
 * "institucional"— y no se puede eliminar: se añade una columna generada que
 * lo proyecta a 0 y el índice se construye sobre ella.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE mallas_externas SET version = '1' WHERE version IS NULL");
        DB::statement("ALTER TABLE mallas_externas MODIFY version VARCHAR(20) NOT NULL DEFAULT '1'");

        Schema::table('cursos_no_convalidables', function (Blueprint $table) {
            $table->dropUnique('uq_no_convalidable_clave_carrera');
        });

        Schema::table('cursos_no_convalidables', function (Blueprint $table) {
            // Virtual, no stored: carrera_id es hijo de una FK con ON DELETE
            // CASCADE, y MySQL/InnoDB rechaza (error 1215) una columna generada
            // STORED cuya expresión depende de la columna hija de una FK con
            // CASCADE. Virtual sí se permite y MySQL 8 indexa columnas virtuales
            // sin problema, así que no hace falta tocar la FK.
            $table->unsignedBigInteger('carrera_key')
                ->virtualAs('IFNULL(carrera_id, 0)')->after('carrera_id');
        });

        Schema::table('cursos_no_convalidables', function (Blueprint $table) {
            $table->unique(['clave_normalizada', 'carrera_key'], 'uq_no_convalidable_clave_carrera');
        });
    }

    public function down(): void
    {
        Schema::table('cursos_no_convalidables', function (Blueprint $table) {
            $table->dropUnique('uq_no_convalidable_clave_carrera');
        });

        Schema::table('cursos_no_convalidables', function (Blueprint $table) {
            $table->dropColumn('carrera_key');
        });

        Schema::table('cursos_no_convalidables', function (Blueprint $table) {
            $table->unique(['clave_normalizada', 'carrera_id'], 'uq_no_convalidable_clave_carrera');
        });

        DB::statement('ALTER TABLE mallas_externas MODIFY version VARCHAR(255) NULL');
    }
};
