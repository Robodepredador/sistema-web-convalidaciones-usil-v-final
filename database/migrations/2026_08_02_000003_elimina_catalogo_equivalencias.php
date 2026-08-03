<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BD-07. El catálogo de equivalencias curso↔curso queda descartado por decisión
 * de TI. Se retira su esquema muerto.
 *
 * NO se toca `simulacion_detalle`: ahí vive el mapeo curso externo ↔ curso USIL
 * de cada convalidación, que es el registro histórico de lo realmente aprobado.
 * Solo se suelta la FK a la tabla que desaparece.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Guarda de seguridad: si alguien llegó a usar el catálogo, no se destruye.
        if (Schema::hasTable('equivalencias') && DB::table('equivalencias')->exists()) {
            throw new RuntimeException(
                'La tabla `equivalencias` tiene datos. Expórtelos antes de eliminar el catálogo.'
            );
        }

        if (Schema::hasColumn('simulacion_detalle', 'equivalencia_id')) {
            Schema::table('simulacion_detalle', function (Blueprint $table) {
                $table->dropForeign(['equivalencia_id']);
                $table->dropColumn('equivalencia_id');
            });
        }

        Schema::dropIfExists('equivalencias');

        // El origen 'catalogo' provenía del catálogo eliminado; ya no es alcanzable.
        DB::statement("ALTER TABLE simulacion_detalle MODIFY origen
            ENUM('automatico','manual','ia','similitud') NOT NULL DEFAULT 'automatico'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE simulacion_detalle MODIFY origen
            ENUM('automatico','manual','ia','similitud','catalogo') NOT NULL DEFAULT 'automatico'");

        Schema::create('equivalencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrera_externa_id')->constrained('carreras_externas');
            $table->foreignId('carrera_usil_id')->constrained('carreras');
            $table->foreignId('curso_externo_id')->constrained('cursos_externos');
            $table->foreignId('curso_usil_id')->constrained('cursos_usil');
            $table->enum('tipo_equivalencia', ['completa', 'parcial'])->default('completa');
            $table->enum('origen', ['manual', 'ia'])->default('manual');
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('simulacion_detalle', function (Blueprint $table) {
            $table->foreignId('equivalencia_id')->nullable()->constrained('equivalencias');
        });
    }
};
