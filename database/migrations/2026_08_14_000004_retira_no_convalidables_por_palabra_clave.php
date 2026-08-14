<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El modelo nuevo no necesita una segunda fuente de verdad para decidir qué NO
 * se convalida: si el especialista no le registró una equivalencia a un curso,
 * ese curso simplemente no aparece en su catálogo. La tabla de palabras clave
 * (con su ámbito institucional/por carrera) y la casilla
 * `cursos_usil.convalidable` declaraban la negativa por adelantado, por fuera
 * del catálogo real de equivalencias, y con eso podían contradecirlo en vez de
 * derivarse de él.
 *
 * down() recrea la ESTRUCTURA de la tabla y la columna, tal como quedaron tras
 * 2026_08_13_000004_cierra_agujeros_de_unicidad_con_null (la última migración
 * que las tocó), pero NO recupera los DATOS: las reglas de palabra clave que
 * hubiera cargadas en el momento de este rollback se pierden sin rastro.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('cursos_no_convalidables');

        if (Schema::hasColumn('cursos_usil', 'convalidable')) {
            Schema::table('cursos_usil', function (Blueprint $table) {
                $table->dropColumn('convalidable');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('cursos_no_convalidables')) {
            Schema::create('cursos_no_convalidables', function (Blueprint $table) {
                $table->id();
                $table->foreignId('carrera_id')->nullable()
                    ->constrained('carreras')->cascadeOnDelete();
                // Virtual, no stored: ver 2026_08_13_000004 sobre por qué (carrera_id
                // es hijo de una FK con cascadeOnDelete, y MySQL/InnoDB rechaza una
                // columna generada STORED que dependa de ella).
                $table->unsignedBigInteger('carrera_key')->virtualAs('IFNULL(carrera_id, 0)');
                $table->string('palabra_clave', 120);
                $table->string('clave_normalizada', 120)->index();
                $table->string('motivo', 150)->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->unique(['clave_normalizada', 'carrera_key'], 'uq_no_convalidable_clave_carrera');
                $table->engine = 'InnoDB';
            });
        }

        if (! Schema::hasColumn('cursos_usil', 'convalidable')) {
            Schema::table('cursos_usil', function (Blueprint $table) {
                $table->boolean('convalidable')->default(true)->after('es_electivo');
            });
        }
    }
};
