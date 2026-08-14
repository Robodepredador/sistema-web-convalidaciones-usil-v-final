<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El catálogo de equivalencias existía pero la simulación no lo consultaba
 * nunca: el coordinador decidía libremente, expediente por expediente. Y el
 * modelo lo forzaba a decidir, porque dos restricciones únicas impedían que un
 * curso USIL tuviera más de un equivalente.
 *
 * El cliente pide lo contrario: el especialista registra, tras comparar
 * sílabos, TODAS las opciones válidas —POO puede convalidarse con tres cursos
 * distintos de SENATI—, y el administrativo escoge dentro de esa lista según lo
 * que el estudiante llevó de verdad. Un curso externo puede además servir para
 * varios cursos USIL; el cliente lo confirmó sabiendo que eso otorga créditos
 * de dos cursos por uno.
 *
 * Queda una sola restricción: no registrar dos veces el mismo par. Y como la
 * clave es el par, no hace falta un id sustituto.
 *
 * No hay tabla de cabecera. La propuesta anterior agrupaba por (malla externa,
 * malla USIL), pero ese agrupamiento no existe en el trabajo real del
 * especialista: agrega equivalencias de una en una, durante meses, según van
 * llegando estudiantes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE TABLE equivalencias (
            curso_usil_id      BIGINT UNSIGNED NOT NULL,
            curso_externo_id   BIGINT UNSIGNED NOT NULL,
            carrera_externa_id BIGINT UNSIGNED NOT NULL,
            registrado_por_id  BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
            PRIMARY KEY (curso_usil_id, curso_externo_id),
            KEY ix_equivalencia_externo (curso_externo_id),
            KEY ix_equivalencia_carrera (carrera_externa_id, curso_usil_id),
            KEY ix_equivalencia_autor   (registrado_por_id),
            CONSTRAINT fk_equivalencia_usil FOREIGN KEY (curso_usil_id)
                REFERENCES cursos_usil (id) ON DELETE CASCADE,
            CONSTRAINT fk_equivalencia_externo FOREIGN KEY (curso_externo_id, carrera_externa_id)
                REFERENCES cursos_externos (id, carrera_externa_id) ON DELETE CASCADE,
            CONSTRAINT fk_equivalencia_autor FOREIGN KEY (registrado_por_id)
                REFERENCES usuarios (id) ON DELETE SET NULL
        ) ENGINE=InnoDB');

        // Traspaso de lo que hubiera en el modelo viejo (1 fila en desarrollo).
        if (Schema::hasTable('equivalencias_malla')) {
            DB::statement('INSERT IGNORE INTO equivalencias
                (curso_usil_id, curso_externo_id, carrera_externa_id, registrado_por_id, created_at, updated_at)
                SELECT em.curso_usil_id, em.curso_externo_id, ce.carrera_externa_id,
                       em.usuario_id, em.created_at, em.updated_at
                FROM equivalencias_malla em
                INNER JOIN cursos_externos ce ON ce.id = em.curso_externo_id');

            Schema::drop('equivalencias_malla');
        }
    }

    public function down(): void
    {
        // El modelo viejo no puede representar lo que este permite: si un curso
        // USIL tiene varias opciones, solo cabe una. Se conserva la de menor id
        // de curso externo y se pierde el resto. La reversión es estructural,
        // no de datos.
        DB::statement('CREATE TABLE IF NOT EXISTS equivalencias_malla (
            id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            curso_externo_id BIGINT UNSIGNED NOT NULL,
            curso_usil_id    BIGINT UNSIGNED NOT NULL,
            malla_externa_id BIGINT UNSIGNED NOT NULL,
            malla_usil_id    BIGINT UNSIGNED NOT NULL,
            usuario_id       BIGINT UNSIGNED NULL,
            created_at TIMESTAMP NULL, updated_at TIMESTAMP NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uq_eqm_externo_destino (curso_externo_id, malla_usil_id),
            UNIQUE KEY uq_eqm_usil_origen (curso_usil_id, malla_externa_id)
        ) ENGINE=InnoDB');

        Schema::dropIfExists('equivalencias');
    }
};
