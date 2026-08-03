<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Un postulante puede solicitar convalidación contra UNA O MÁS carreras USIL.
 * Cada destino es una solicitud de simulación independiente, con su propio
 * estado de revisión de equivalencias (CU-03). El primer destino sigue
 * reflejándose en postulantes.carrera_destino_id (destino primario).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('postulante_destinos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('postulante_id')->constrained('postulantes')->cascadeOnDelete();
            $table->foreignId('carrera_id')->constrained('carreras')->cascadeOnDelete();
            $table->enum('estado_equivalencias', ['pendiente', 'en_revision', 'aprobada'])->default('pendiente');
            $table->foreignId('equivalencias_revisado_por')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('equivalencias_revisado_en')->nullable();
            $table->timestamps();
            $table->unique(['postulante_id', 'carrera_id']);
            $table->index('estado_equivalencias');
            $table->engine = 'InnoDB';
        });

        // Backfill: cada postulante con destino primario obtiene su registro,
        // conservando el estado de revisión que ya tenía.
        $postulantes = DB::table('postulantes')
            ->whereNotNull('carrera_destino_id')
            ->whereNull('deleted_at')
            ->get(['id', 'carrera_destino_id', 'estado_equivalencias', 'equivalencias_revisado_por', 'equivalencias_revisado_en']);

        foreach ($postulantes as $p) {
            DB::table('postulante_destinos')->insertOrIgnore([
                'postulante_id' => $p->id,
                'carrera_id' => $p->carrera_destino_id,
                'estado_equivalencias' => $p->estado_equivalencias ?? 'pendiente',
                'equivalencias_revisado_por' => $p->equivalencias_revisado_por,
                'equivalencias_revisado_en' => $p->equivalencias_revisado_en,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('postulante_destinos');
    }
};
