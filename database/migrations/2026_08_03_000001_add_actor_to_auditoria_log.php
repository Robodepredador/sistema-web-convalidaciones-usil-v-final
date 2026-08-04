<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BD-09 (informe 2026-08-02, no corregido entonces): las acciones del portal
 * del postulante quedaban con `usuario_id = null`, indistinguibles de una
 * acción del sistema.
 *
 * `usuario_id` tiene FK a `usuarios` y no puede apuntar a `postulantes`, así que
 * se añade el par (actor_tipo, actor_id) para identificar al autor sea cual sea
 * su guard. `usuario_id` se conserva intacto para no romper las consultas ni la
 * FK existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auditoria_log', function (Blueprint $table) {
            $table->enum('actor_tipo', ['usuario', 'postulante', 'sistema'])
                ->default('sistema')->after('usuario_id');
            $table->unsignedBigInteger('actor_id')->nullable()->after('actor_tipo');
            $table->index(['actor_tipo', 'actor_id'], 'idx_auditoria_actor');
        });

        // Traza histórica: las filas con usuario_id son del personal.
        DB::table('auditoria_log')->whereNotNull('usuario_id')->update([
            'actor_tipo' => 'usuario',
            'actor_id' => DB::raw('usuario_id'),
        ]);

        // Las de login del portal se reconocen por su tabla afectada.
        DB::table('auditoria_log')
            ->whereNull('usuario_id')
            ->where('tabla_afectada', 'postulantes')
            ->whereIn('accion', ['login', 'logout'])
            ->update(['actor_tipo' => 'postulante', 'actor_id' => DB::raw('registro_id')]);
    }

    public function down(): void
    {
        Schema::table('auditoria_log', function (Blueprint $table) {
            $table->dropIndex('idx_auditoria_actor');
            $table->dropColumn(['actor_tipo', 'actor_id']);
        });
    }
};
