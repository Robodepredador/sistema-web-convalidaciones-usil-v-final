<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Informe de Auditoría Técnica (2026-08-02): restricciones que faltaban para que
 * la base de datos garantice por sí misma las reglas del negocio.
 *
 *  BD-01  memorandum_numero único (número de resolución oficial irrepetible).
 *  BD-04  mallas_externas única por (carrera, año, versión).
 *  BD-06  índices de consulta en auditoria_log.
 *  BD-10  cursos_externos único por (malla, nombre).
 *
 * Los datos ya afectados (BD-02, BD-03) se sanean en la migración siguiente.
 */
return new class extends Migration
{
    public function up(): void
    {
        // BD-04: eliminar duplicados existentes antes de imponer la restricción.
        // Se conserva la malla más reciente de cada grupo (mayor id).
        $duplicadas = DB::table('mallas_externas')
            ->select(DB::raw('MAX(id) as conservar'), 'carrera_externa_id', 'anio', 'version')
            ->groupBy('carrera_externa_id', 'anio', 'version')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicadas as $g) {
            $obsoletas = DB::table('mallas_externas')
                ->where('carrera_externa_id', $g->carrera_externa_id)
                ->where('anio', $g->anio)
                ->where('version', $g->version)
                ->where('id', '<>', $g->conservar)
                ->pluck('id');

            // Solo se descartan las que ningún curso convalidado esté usando.
            $enUso = DB::table('cursos_externos')
                ->whereIn('malla_externa_id', $obsoletas)
                ->join('simulacion_detalle', 'simulacion_detalle.curso_externo_id', '=', 'cursos_externos.id')
                ->distinct()
                ->pluck('cursos_externos.malla_externa_id');

            $borrables = $obsoletas->diff($enUso);
            if ($borrables->isNotEmpty()) {
                // cursos_externos cae por ON DELETE CASCADE.
                DB::table('mallas_externas')->whereIn('id', $borrables)->delete();
            }
        }

        Schema::table('convalidaciones', function (Blueprint $table) {
            // BD-01: dos expedientes no pueden compartir número de memorándum.
            $table->unique('memorandum_numero', 'uq_convalidaciones_memorandum');
        });

        Schema::table('mallas_externas', function (Blueprint $table) {
            $table->unique(['carrera_externa_id', 'anio', 'version'], 'uq_malla_externa_carrera_anio_version');
        });

        Schema::table('cursos_externos', function (Blueprint $table) {
            // BD-10: reprocesar una malla con IA no debe duplicar sus cursos.
            $table->unique(['malla_externa_id', 'nombre'], 'uq_curso_externo_malla_nombre');
        });

        Schema::table('auditoria_log', function (Blueprint $table) {
            // BD-06: la traza se consulta por registro afectado y por fecha.
            $table->index(['tabla_afectada', 'registro_id'], 'idx_auditoria_registro');
            $table->index('created_at', 'idx_auditoria_fecha');
        });
    }

    public function down(): void
    {
        Schema::table('convalidaciones', fn (Blueprint $t) => $t->dropUnique('uq_convalidaciones_memorandum'));
        Schema::table('mallas_externas', fn (Blueprint $t) => $t->dropUnique('uq_malla_externa_carrera_anio_version'));
        Schema::table('cursos_externos', fn (Blueprint $t) => $t->dropUnique('uq_curso_externo_malla_nombre'));
        Schema::table('auditoria_log', function (Blueprint $t) {
            $t->dropIndex('idx_auditoria_registro');
            $t->dropIndex('idx_auditoria_fecha');
        });
    }
};
