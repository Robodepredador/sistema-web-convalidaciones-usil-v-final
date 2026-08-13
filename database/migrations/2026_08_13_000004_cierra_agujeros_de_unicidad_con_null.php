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
 *
 * down() no es un deshacer completo: devuelve `version` a NULLABLE, pero no
 * tiene forma de saber qué filas eran NULL antes de up(), así que no
 * restaura ese estado; quedan todas con '1'.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL no tiene DDL transaccional: si un ALTER a medio camino
        // fallara por datos duplicados, el esquema quedaría a medio migrar y
        // la migración no quedaría registrada. Se comprueba todo antes de
        // tocar nada, para poder abortar limpio en vez de romper el esquema.
        $this->abortarSiHayDatosIncompatibles();

        DB::statement("UPDATE mallas_externas SET version = '1' WHERE version IS NULL");
        DB::statement("ALTER TABLE mallas_externas MODIFY version VARCHAR(20) NOT NULL DEFAULT '1'");

        // Tolera una corrida previa que haya abortado a medio camino: si el
        // índice viejo ya no existe o la columna ya se creó, no se repite el
        // paso.
        if ($this->existeIndice('cursos_no_convalidables', 'uq_no_convalidable_clave_carrera')) {
            Schema::table('cursos_no_convalidables', function (Blueprint $table) {
                $table->dropUnique('uq_no_convalidable_clave_carrera');
            });
        }

        if (! Schema::hasColumn('cursos_no_convalidables', 'carrera_key')) {
            Schema::table('cursos_no_convalidables', function (Blueprint $table) {
                // Virtual, no stored: carrera_id es hijo de una FK con ON DELETE
                // CASCADE, y MySQL/InnoDB rechaza (error 1215) una columna generada
                // STORED cuya expresión depende de la columna hija de una FK con
                // CASCADE. Virtual sí se permite y MySQL 8 indexa columnas virtuales
                // sin problema, así que no hace falta tocar la FK.
                $table->unsignedBigInteger('carrera_key')
                    ->virtualAs('IFNULL(carrera_id, 0)')->after('carrera_id');
            });
        }

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

    /**
     * Comprueba, antes de tocar el esquema, que los datos existentes no van
     * a chocar con las restricciones nuevas. No deduplica nada de forma
     * automática: son datos del usuario y no es una decisión que corresponda
     * tomar aquí; el operador debe deduplicar a mano y reintentar.
     */
    private function abortarSiHayDatosIncompatibles(): void
    {
        $mallasDuplicadas = DB::select(
            "SELECT carrera_externa_id, anio, COUNT(*) total
             FROM mallas_externas
             GROUP BY carrera_externa_id, anio, COALESCE(version, '1')
             HAVING total > 1"
        );

        if ($mallasDuplicadas !== []) {
            $filas = collect($mallasDuplicadas)
                ->map(fn ($f) => "carrera_externa_id={$f->carrera_externa_id}, anio={$f->anio} ({$f->total} filas)")
                ->implode(' | ');

            throw new RuntimeException(
                "No se puede aplicar la migración: hay mallas externas que colapsarían en la ".
                "misma fila al volver 'version' obligatoria (toda fila con version NULL pasa a ".
                "'1'). Deduplique a mano antes de reintentar. Filas en conflicto ".
                "(carrera_externa_id, anio): {$filas}."
            );
        }

        $largoMaximo = DB::selectOne('SELECT MAX(LENGTH(version)) largo FROM mallas_externas')->largo;

        if ($largoMaximo !== null && $largoMaximo > 20) {
            throw new RuntimeException(
                "No se puede aplicar la migración: mallas_externas.version tiene un valor de ".
                "{$largoMaximo} caracteres, y el destino de la migración es VARCHAR(20). Corrija ".
                'o acorte ese valor antes de reintentar.'
            );
        }

        $reglasDuplicadas = DB::select(
            'SELECT clave_normalizada, IFNULL(carrera_id, 0) ambito, COUNT(*) total
             FROM cursos_no_convalidables
             GROUP BY clave_normalizada, ambito
             HAVING total > 1'
        );

        if ($reglasDuplicadas !== []) {
            $filas = collect($reglasDuplicadas)
                ->map(fn ($f) => "clave_normalizada='{$f->clave_normalizada}', ambito=".
                    ($f->ambito == 0 ? 'institucional' : "carrera_id={$f->ambito}")." ({$f->total} filas)")
                ->implode(' | ');

            throw new RuntimeException(
                "No se puede aplicar la migración: hay reglas de cursos no convalidables ".
                "duplicadas para la misma clave dentro del mismo ámbito (misma carrera, o ambas ".
                "institucionales). Deduplique a mano antes de reintentar. Filas en conflicto: {$filas}."
            );
        }
    }

    /** information_schema.STATISTICS es donde MySQL expone si un índice existe. */
    private function existeIndice(string $tabla, string $indice): bool
    {
        return DB::selectOne(
            'SELECT COUNT(*) total FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND INDEX_NAME = ?',
            [$tabla, $indice]
        )->total > 0;
    }
};
