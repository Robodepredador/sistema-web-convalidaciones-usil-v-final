<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Los tres catálogos de mayor volumen no tenían clave natural declarada. Se
 * cargan por importación —SUNEDU, Excel de mallas—, y una importación que se
 * corta a la mitad y se reintenta duplica filas en silencio. El usuario se
 * encuentra después dos instituciones con el mismo nombre y ninguna forma de
 * saber cuál tiene los datos buenos.
 *
 * Verificado antes de aplicar: hoy no hay duplicados en ninguna de las tres,
 * así que las restricciones se aplican sin saneo previo.
 *
 * cursos_usil se restringe por (ciclo_id, codigo) y no por malla, porque hoy el
 * curso no conoce su malla: llega a ella por el ciclo. La Fase 2 propaga
 * plan_estudio_id hasta el curso y eleva la restricción a su nivel correcto.
 *
 * Límite conocido y aceptado: instituciones_externas.pais es NULLABLE, e InnoDB
 * trata cada NULL de un índice único como distinto de cualquier otro NULL. Dos
 * instituciones con el mismo nombre y país NULL no chocan entre sí: la
 * unicidad solo muerde cuando el país está informado. No se corrige aquí —no
 * es el alcance de esta tarea—; si se quisiera cerrar ese hueco, el patrón es
 * el mismo que usó 2026_08_13_000004 con carrera_key: una columna generada
 * que colapse el NULL a un valor fijo antes de indexar.
 */
return new class extends Migration
{
    public function up(): void
    {
        // MySQL no tiene DDL transaccional: si el segundo o el tercer ALTER
        // abortara por datos duplicados, los índices anteriores ya se habrían
        // creado pero la migración no quedaría registrada como aplicada. Se
        // comprueban los tres conjuntos antes de tocar el esquema, para poder
        // abortar limpio en vez de dejar la base a medio migrar.
        $this->abortarSiHayDatosIncompatibles();

        // Guardas de idempotencia: toleran una corrida previa que haya
        // abortado a medio camino (un índice ya creado, los otros no).
        if (! $this->existeIndice('instituciones_externas', 'uq_institucion_nombre_pais')) {
            Schema::table('instituciones_externas', function (Blueprint $table) {
                $table->unique(['nombre', 'pais'], 'uq_institucion_nombre_pais');
            });
        }

        if (! $this->existeIndice('carreras_externas', 'uq_carrera_externa_institucion_nombre')) {
            Schema::table('carreras_externas', function (Blueprint $table) {
                $table->unique(['institucion_id', 'nombre'], 'uq_carrera_externa_institucion_nombre');
            });
        }

        if (! $this->existeIndice('cursos_usil', 'uq_curso_usil_ciclo_codigo')) {
            Schema::table('cursos_usil', function (Blueprint $table) {
                $table->unique(['ciclo_id', 'codigo'], 'uq_curso_usil_ciclo_codigo');
            });
        }
    }

    public function down(): void
    {
        // ciclo_id e institucion_id son la columna líder de sus índices únicos
        // nuevos Y las columnas de sus FK. En cuanto up() creó los compuestos,
        // MySQL soltó por redundante el índice simple que create_cursos_usil_table
        // y create_carreras_externas_table habían dejado para respaldar esas FK
        // (mismo nombre que la propia constraint: cursos_usil_ciclo_id_foreign,
        // carreras_externas_institucion_id_foreign). Sin ese respaldo, InnoDB
        // rechaza el DROP del compuesto con el error 1553 "needed in a foreign
        // key constraint". Se repone el índice simple antes de soltar cada
        // compuesto para dejar el esquema tal como estaba antes de up().
        //
        // La reposición está guardada con existeIndice(): a diferencia del
        // índice implícito original (que MySQL marca como auto-generado y
        // vuelve a soltar solo si algo lo hace redundante), este se crea de
        // forma explícita y ya no se suelta solo, así que un segundo
        // rollback tras un up()/down() previo lo encontraría ya presente y
        // "Duplicate key name" rompería el down() sin la guarda.
        if (! $this->existeIndice('cursos_usil', 'cursos_usil_ciclo_id_foreign')) {
            Schema::table('cursos_usil', function (Blueprint $table) {
                $table->index('ciclo_id', 'cursos_usil_ciclo_id_foreign');
            });
        }
        Schema::table('cursos_usil', function (Blueprint $table) {
            $table->dropUnique('uq_curso_usil_ciclo_codigo');
        });

        if (! $this->existeIndice('carreras_externas', 'carreras_externas_institucion_id_foreign')) {
            Schema::table('carreras_externas', function (Blueprint $table) {
                $table->index('institucion_id', 'carreras_externas_institucion_id_foreign');
            });
        }
        Schema::table('carreras_externas', function (Blueprint $table) {
            $table->dropUnique('uq_carrera_externa_institucion_nombre');
        });

        Schema::table('instituciones_externas', function (Blueprint $table) {
            $table->dropUnique('uq_institucion_nombre_pais');
        });
    }

    /**
     * Comprueba, antes de tocar el esquema, que los datos existentes no van a
     * chocar con las restricciones nuevas. No deduplica nada de forma
     * automática: son datos del usuario y no es una decisión que corresponda
     * tomar aquí; el operador debe deduplicar a mano y reintentar.
     */
    private function abortarSiHayDatosIncompatibles(): void
    {
        // WHERE pais IS NOT NULL: modela el mismo comportamiento que tendrá el
        // índice único. Dos instituciones con el mismo nombre y pais NULL no
        // violan uq_institucion_nombre_pais (InnoDB no compara NULLs entre sí),
        // así que agruparlas aquí sería una falsa alarma.
        $institucionesDuplicadas = DB::select(
            "SELECT nombre, pais, COUNT(*) total
             FROM instituciones_externas
             WHERE pais IS NOT NULL
             GROUP BY nombre, pais
             HAVING total > 1"
        );

        if ($institucionesDuplicadas !== []) {
            $filas = collect($institucionesDuplicadas)
                ->map(fn ($f) => "nombre='{$f->nombre}', pais='{$f->pais}' ({$f->total} filas)")
                ->implode(' | ');

            throw new RuntimeException(
                'No se puede aplicar la migración: hay instituciones externas duplicadas '.
                '(mismo nombre y mismo país). Deduplique a mano antes de reintentar. Filas en '.
                "conflicto: {$filas}."
            );
        }

        $carrerasDuplicadas = DB::select(
            'SELECT institucion_id, nombre, COUNT(*) total
             FROM carreras_externas
             GROUP BY institucion_id, nombre
             HAVING total > 1'
        );

        if ($carrerasDuplicadas !== []) {
            $filas = collect($carrerasDuplicadas)
                ->map(fn ($f) => "institucion_id={$f->institucion_id}, nombre='{$f->nombre}' ({$f->total} filas)")
                ->implode(' | ');

            throw new RuntimeException(
                'No se puede aplicar la migración: hay carreras externas duplicadas dentro de la '.
                "misma institución. Deduplique a mano antes de reintentar. Filas en conflicto: {$filas}."
            );
        }

        $cursosDuplicados = DB::select(
            'SELECT ciclo_id, codigo, COUNT(*) total
             FROM cursos_usil
             GROUP BY ciclo_id, codigo
             HAVING total > 1'
        );

        if ($cursosDuplicados !== []) {
            $filas = collect($cursosDuplicados)
                ->map(fn ($f) => "ciclo_id={$f->ciclo_id}, codigo='{$f->codigo}' ({$f->total} filas)")
                ->implode(' | ');

            throw new RuntimeException(
                'No se puede aplicar la migración: hay cursos USIL duplicados (mismo ciclo y mismo '.
                "código). Deduplique a mano antes de reintentar. Filas en conflicto: {$filas}."
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
