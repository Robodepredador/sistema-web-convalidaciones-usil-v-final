<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Limpieza menor del esquema, detectada al auditar la coherencia con el código.
 *
 * 1. `postulantes.pais_residencia` — varchar(60) que se añadió al alinear el
 *    proceso con la normativa de traslado externo y que NADA escribe ni lee: no
 *    aparece en ningún formulario, controlador, exportación ni pantalla. Ha
 *    estado siempre NULL y lo estaría siempre.
 *
 * 2. Dos índices redundantes: cada uno es prefijo de otro índice compuesto que
 *    ya lo cubre, así que MySQL nunca los elige y solo cuestan escrituras.
 *
 *      - equivalencias.ix_equivalencia_externo (curso_externo_id) está contenido
 *        en fk_equivalencia_externo (curso_externo_id, carrera_externa_id).
 *      - mallas_curriculares_carrera_id_index (carrera_id) está contenido en
 *        mallas_curriculares_carrera_id_anio_version_activa_unica_unique.
 *
 *    Las claves foráneas que dependían de ellos siguen cubiertas: en ambos casos
 *    la columna sigue siendo la primera del índice compuesto, que es lo único
 *    que MySQL exige.
 *
 * NO se tocan las columnas del memorándum en `convalidaciones`
 * (`memorandum_numero`, `memorandum_pdf_path`, `motivo_anulacion`). El módulo se
 * retiró el 10/08, pero la tabla se conserva a propósito como historial: hay
 * instalaciones con filas reales ahí y borrar esas columnas destruiría el
 * registro de los memorandos ya emitidos. Quedan inertes, que es lo correcto.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('postulantes', 'pais_residencia')) {
            Schema::table('postulantes', function (Blueprint $table) {
                $table->dropColumn('pais_residencia');
            });
        }

        Schema::table('equivalencias', function (Blueprint $table) {
            $table->dropIndex('ix_equivalencia_externo');
        });

        Schema::table('mallas_curriculares', function (Blueprint $table) {
            $table->dropIndex('mallas_curriculares_carrera_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('mallas_curriculares', function (Blueprint $table) {
            $table->index('carrera_id', 'mallas_curriculares_carrera_id_index');
        });

        Schema::table('equivalencias', function (Blueprint $table) {
            $table->index('curso_externo_id', 'ix_equivalencia_externo');
        });

        Schema::table('postulantes', function (Blueprint $table) {
            $table->string('pais_residencia', 60)->nullable();
        });
    }
};
