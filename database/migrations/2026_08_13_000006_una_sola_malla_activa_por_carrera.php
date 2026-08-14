<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `activa` era un booleano suelto: nada impedía marcar dos mallas de la misma
 * carrera a la vez. ConvalidacionEngine::mallaDeCarrera() se apoya en ese
 * campo para elegir "la" malla vigente de la carrera.
 *
 * El desempate de ese método (activa, anio, id, todos descendentes) es
 * determinista, así que dos postulantes evaluados hoy obtienen la misma malla:
 * el riesgo no es que el resultado varíe entre consultas. Es que con dos
 * activas la que gana el desempate no tiene por qué ser la que el
 * coordinador considera vigente, y basta cargar una malla nueva para que la
 * ganadora cambie sin que nadie lo decida ni lo note. Una carrera tiene una
 * malla vigente: eso es una regla del negocio y le corresponde a la base
 * sostenerla, no al ORDER BY de una consulta.
 *
 * Mismo recurso que ya usa `activa_unica` en esta tabla: una columna generada
 * que vale 1 solo cuando la fila cuenta (activa y no borrada) y NULL cuando
 * no. Como los NULL no chocan entre sí en un índice único de InnoDB, el
 * índice solo compara las filas activas entre sí.
 *
 * Ojo con el nombre parecido: `activa_unica` NO controla `activa`, solo
 * excluye las filas con borrado lógico del índice de la clave natural
 * (carrera_id, anio, version). Son dos columnas con propósitos distintos.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL no tiene DDL transaccional: si el ALTER del índice único
        // abortara por datos duplicados, la columna generada ya se habría
        // creado pero la migración no quedaría registrada como aplicada. Se
        // comprueba antes de tocar el esquema, para poder abortar limpio en
        // vez de dejar la base a medio migrar.
        $this->abortarSiHayDatosIncompatibles();

        // Guarda de idempotencia: tolera una corrida previa que haya
        // abortado a medio camino (la columna ya creada, el índice no).
        if (! Schema::hasColumn('mallas_curriculares', 'vigente_flag')) {
            Schema::table('mallas_curriculares', function (Blueprint $table) {
                $table->boolean('vigente_flag')
                    ->nullable()
                    ->virtualAs('IF(activa = 1 AND deleted_at IS NULL, 1, NULL)')
                    ->after('activa_unica');
            });
        }

        if (! Schema::hasIndex('mallas_curriculares', 'uq_malla_vigente_por_carrera')) {
            Schema::table('mallas_curriculares', function (Blueprint $table) {
                $table->unique(['carrera_id', 'vigente_flag'], 'uq_malla_vigente_por_carrera');
            });
        }
    }

    public function down(): void
    {
        // Guardadas por el mismo motivo que las creaciones de up(): si down()
        // aborta a medio camino, Laravel no borra la fila de la migración, y
        // el siguiente rollback debe poder reintentar sin romper con el error
        // 1091 ("check that column/key exists") ni el 1060 ("Duplicate
        // column name") de intentar soltar algo que ya no está.
        if (Schema::hasIndex('mallas_curriculares', 'uq_malla_vigente_por_carrera')) {
            Schema::table('mallas_curriculares', function (Blueprint $table) {
                $table->dropUnique('uq_malla_vigente_por_carrera');
            });
        }

        if (Schema::hasColumn('mallas_curriculares', 'vigente_flag')) {
            Schema::table('mallas_curriculares', function (Blueprint $table) {
                $table->dropColumn('vigente_flag');
            });
        }
    }

    /**
     * Comprueba, antes de tocar el esquema, que ninguna carrera tiene ya dos
     * mallas activas. No desactiva ninguna de forma automática: elegir cuál
     * malla queda vigente es una decisión académica, no algo que competa a
     * una migración. El operador debe decidir a mano y reintentar.
     */
    private function abortarSiHayDatosIncompatibles(): void
    {
        $carrerasConDosActivas = DB::select(
            'SELECT carrera_id, COUNT(*) total FROM mallas_curriculares
             WHERE activa = 1 AND deleted_at IS NULL
             GROUP BY carrera_id HAVING total > 1'
        );

        if ($carrerasConDosActivas !== []) {
            $filas = collect($carrerasConDosActivas)
                ->map(fn ($f) => "carrera_id={$f->carrera_id} ({$f->total} mallas activas)")
                ->implode(' | ');

            throw new RuntimeException(
                'No se puede aplicar la migración: hay carreras con más de una malla '.
                'curricular activa a la vez. Decida a mano cuál debe quedar vigente y '.
                "desactive las demás antes de reintentar. Carreras en conflicto: {$filas}."
            );
        }
    }
};
