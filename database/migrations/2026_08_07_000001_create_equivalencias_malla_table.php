<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Criterio declarado por el coordinador: qué curso de una malla externa equivale a
 * qué curso de una malla USIL, antes de que existan expedientes que lo respalden.
 *
 * NO es la tabla `equivalencias` que se eliminó en BD-07, aunque cubra la misma
 * necesidad. Aquella colgaba de `carrera_externa_id` —la carrera, no la versión de
 * la malla—, así que al publicarse un plan nuevo sus pares seguían aplicando en
 * silencio al plan nuevo. Ese era su defecto real. Esta cuelga de las dos versiones
 * de malla: un plan nuevo trae cursos con ids nuevos, los pares viejos dejan de
 * coincidir y se ve.
 *
 * Sin `softDeletes`, a contracorriente del resto del proyecto: un índice único
 * convive mal con el borrado lógico, porque la fila borrada sigue ocupando la
 * combinación y volver a crear el mismo par fallaría. Es el problema que la
 * migración 2026_07_13_000001 tuvo que arreglar en `mallas_curriculares` con una
 * columna generada, y no vale la pena repetirlo. El borrado se audita en
 * `auditoria_log`, que guarda el par en su payload. Un mapeo es una declaración
 * vigente, no un registro histórico: la historia vive en `simulacion_detalle`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equivalencias_malla', function (Blueprint $table) {
            $table->id();

            // El par en sí.
            $table->foreignId('curso_externo_id')->constrained('cursos_externos')->cascadeOnDelete();
            $table->foreignId('curso_usil_id')->constrained('cursos_usil')->cascadeOnDelete();

            // Derivables de los cursos, pero necesarias como clave de índice: MySQL no
            // indexa a través de un join. No pueden desincronizarse porque un curso
            // nunca cambia de malla, se crea dentro de una.
            $table->foreignId('malla_externa_id')->constrained('mallas_externas')->cascadeOnDelete();
            $table->foreignId('malla_usil_id')->constrained('mallas_curriculares')->cascadeOnDelete();

            $table->foreignId('usuario_id')->nullable()->constrained('usuarios');
            $table->timestamps();

            // Regla 1 a 1, POR PAR DE MALLAS. Es la misma que la simulación ya exige,
            // de modo que el catálogo nunca puede proponer algo que luego se rechace.
            // La interfaz avisa antes, pero la garantía está aquí: dos coordinadores
            // guardando a la vez no pueden crear el duplicado.
            $table->unique(['curso_externo_id', 'malla_usil_id'], 'uq_eqm_externo_destino');
            $table->unique(['curso_usil_id', 'malla_externa_id'], 'uq_eqm_usil_origen');

            $table->engine = 'InnoDB';
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equivalencias_malla');
    }
};
