<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ámbito de la política de cursos no convalidables.
 *
 *   carrera_id NULL  → regla institucional, la administra el Superusuario.
 *   carrera_id X     → regla de esa carrera, la administra su Coordinador.
 *
 * La regla de la carrera PISA a la institucional para la misma palabra clave,
 * en los dos sentidos: puede añadir una exclusión propia, o desactivar
 * localmente una general (Ingeniería sí convalida Física aunque la regla
 * institucional diga que no).
 *
 * El único índice era por clave; con el ámbito, lo que no puede repetirse es el
 * par (clave, carrera).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cursos_no_convalidables', function (Blueprint $table) {
            $table->foreignId('carrera_id')->nullable()->after('id')
                ->constrained('carreras')->cascadeOnDelete();
            $table->unique(['clave_normalizada', 'carrera_id'], 'uq_no_convalidable_clave_carrera');
        });
    }

    public function down(): void
    {
        Schema::table('cursos_no_convalidables', function (Blueprint $table) {
            $table->dropUnique('uq_no_convalidable_clave_carrera');
            $table->dropConstrainedForeignId('carrera_id');
        });
    }
};
