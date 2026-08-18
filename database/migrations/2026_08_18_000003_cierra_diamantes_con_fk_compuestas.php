<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cierra dos «relaciones diamante»: caminos por los que una fila llega al mismo
 * ancestro por dos rutas distintas y nada impide que ambas discrepen.
 *
 * Se replica el patrón que ya funciona en `equivalencias`, donde
 * `fk_equivalencia_externo` obliga a que la carrera externa propagada coincida
 * con la del curso externo. Aquí se cierran los dos casos equivalentes:
 *
 *  1. postulantes(carrera_externa_id, institucion_origen_id)
 *       -> carreras_externas(id, institucion_id)
 *     Hoy se puede registrar a alguien "que viene de la UNMSM" con una carrera
 *     que pertenece a otra universidad. El expediente entero —y la
 *     preconvalidación que sale de él— quedaría atribuido a la institución
 *     equivocada.
 *
 *  2. simulaciones(malla_usil_id, carrera_usil_id)
 *       -> mallas_curriculares(id, carrera_id)
 *     Hoy se puede emitir una preconvalidación que dice evaluar la carrera A
 *     contra la malla de la carrera B. Es el peor de los dos: ese documento lo
 *     firma la universidad.
 *
 * Por qué SOLO estos dos y no los seis detectados:
 *
 *  - simulaciones <-> carrera externa del postulante: NO se ata. El asesor puede
 *    corregir la carrera de origen de un postulante ya registrado
 *    (PostulanteController la acepta al actualizar), y la simulación debe
 *    conservar contra qué se evaluó. Atarlas bloquearía la corrección o
 *    reescribiría el historial en silencio. Es una instantánea legítima.
 *  - postulante_destinos <-> postulantes.carrera_destino_id: NO se ata. La tabla
 *    es uno-a-muchos —tiene único (postulante_id, carrera_id)—, así que un
 *    postulante puede postular a varias carreras. Atarla obligaría a que todos
 *    sus destinos fueran el mismo. La incoherencia real es que
 *    `postulantes.carrera_destino_id` duplique uno de ellos; retirar esa columna
 *    toca 39 puntos del código y cuatro pantallas Vue: es trabajo de Fase 2.
 *  - simulacion_detalle <-> malla, y cursos_usil.prerequisito_id <-> malla: NO se
 *    atan. `cursos_usil` no tiene `malla_id` propio (cuelga de `ciclos`), así que
 *    la FK compuesta exigiría desnormalizar esa columna y mantenerla en sync.
 *    Eso es un cambio de modelo, no una restricción. Hoy lo cubre el código
 *    (SimulacionController rechaza un curso de otra carrera, con prueba propia).
 *
 * Las columnas de `postulantes` son NULLABLE y así siguen: con MySQL, una FK
 * compuesta con algún valor NULL no se comprueba, de modo que los expedientes en
 * borrador —sin institución ni carrera todavía— siguen guardándose igual.
 *
 * ANTES de crear cada restricción se reparan las filas que ya discrepen,
 * haciendo mandar al padre. Sin esto, una instalación con datos incoherentes
 * abortaría la migración a mitad del despliegue.
 */
return new class extends Migration
{
    public function up(): void
    {
        // --- 1. postulantes -> carreras_externas ---------------------------
        // La institución la manda la carrera externa: es su dueña real.
        DB::statement('UPDATE postulantes p
            INNER JOIN carreras_externas ce ON ce.id = p.carrera_externa_id
            SET p.institucion_origen_id = ce.institucion_id
            WHERE p.carrera_externa_id IS NOT NULL
              AND p.institucion_origen_id <> ce.institucion_id');

        Schema::table('carreras_externas', function (Blueprint $table) {
            $table->unique(['id', 'institucion_id'], 'uq_carrera_externa_id_institucion');
        });

        Schema::table('postulantes', function (Blueprint $table) {
            $table->foreign(['carrera_externa_id', 'institucion_origen_id'], 'fk_postulante_carrera_institucion')
                ->references(['id', 'institucion_id'])->on('carreras_externas');
        });

        // --- 2. simulaciones -> mallas_curriculares -------------------------
        // La carrera la manda la malla: la simulación se corrió contra ella.
        DB::statement('UPDATE simulaciones s
            INNER JOIN mallas_curriculares m ON m.id = s.malla_usil_id
            SET s.carrera_usil_id = m.carrera_id
            WHERE s.carrera_usil_id <> m.carrera_id');

        Schema::table('mallas_curriculares', function (Blueprint $table) {
            $table->unique(['id', 'carrera_id'], 'uq_malla_id_carrera');
        });

        Schema::table('simulaciones', function (Blueprint $table) {
            $table->foreign(['malla_usil_id', 'carrera_usil_id'], 'fk_simulacion_malla_carrera')
                ->references(['id', 'carrera_id'])->on('mallas_curriculares');
        });
    }

    public function down(): void
    {
        Schema::table('simulaciones', function (Blueprint $table) {
            $table->dropForeign('fk_simulacion_malla_carrera');
        });

        Schema::table('mallas_curriculares', function (Blueprint $table) {
            $table->dropUnique('uq_malla_id_carrera');
        });

        Schema::table('postulantes', function (Blueprint $table) {
            $table->dropForeign('fk_postulante_carrera_institucion');
        });

        Schema::table('carreras_externas', function (Blueprint $table) {
            $table->dropUnique('uq_carrera_externa_id_institucion');
        });
    }
};
