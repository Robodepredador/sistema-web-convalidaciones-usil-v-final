<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Amplía el motor de simulación para soportar convalidación manual y con IA
 * (pipeline portado del módulo standalone), reutilizando el plan de estudios
 * que ya vive en mallas_curriculares → ciclos → cursos_usil.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('simulaciones', function (Blueprint $table) {
            // Método de generación del expediente.
            $table->enum('metodo', ['manual', 'ia'])->default('manual')->after('estado');
            // Documento fuente cuando la extracción es con IA.
            $table->string('documento_path', 500)->nullable()->after('pdf_path');
            // Datos de contexto de la extracción.
            $table->string('universidad_origen', 200)->nullable()->after('documento_path');
            $table->string('escala_notas', 10)->nullable()->after('universidad_origen');    // 0-20, 0-100, 0-5
            $table->decimal('nota_minima', 5, 2)->nullable()->after('escala_notas');
        });

        Schema::table('simulacion_detalle', function (Blueprint $table) {
            // Snapshot del curso de origen: permite mapear cursos extraídos por IA
            // sin obligar a que existan en cursos_externos.
            $table->string('curso_origen_nombre', 200)->nullable()->after('curso_externo_id');
            $table->string('nota_origen', 20)->nullable()->after('curso_origen_nombre');
            $table->decimal('creditos_origen', 5, 1)->nullable()->after('nota_origen');
            $table->string('ciclo_origen', 30)->nullable()->after('creditos_origen');
            // Clasificación del curso de origen.
            $table->enum('clasificacion', ['convalidable', 'desaprobado', 'no_convalidable'])
                ->default('convalidable')->after('ciclo_origen');
            // Confianza de la sugerencia (0-100), método y sílabo/nombre USIL sugerido.
            $table->decimal('confianza', 5, 1)->nullable()->after('clasificacion');
        });

        // Las FKs de detalle deben poder ser nulas: "no convalidar" no tiene curso USIL,
        // y un curso extraído por IA puede no existir en cursos_externos.
        Schema::table('simulacion_detalle', function (Blueprint $table) {
            $table->unsignedBigInteger('curso_usil_id')->nullable()->change();
            $table->unsignedBigInteger('curso_externo_id')->nullable()->change();
        });

        // Ampliar el enum de 'origen' para incluir la similitud automática.
        DB::statement(
            "ALTER TABLE simulacion_detalle MODIFY origen ENUM('automatico','manual','ia','similitud') NOT NULL DEFAULT 'automatico'"
        );
    }

    public function down(): void
    {
        Schema::table('simulaciones', function (Blueprint $table) {
            $table->dropColumn(['metodo', 'documento_path', 'universidad_origen', 'escala_notas', 'nota_minima']);
        });

        Schema::table('simulacion_detalle', function (Blueprint $table) {
            $table->dropColumn(['curso_origen_nombre', 'nota_origen', 'creditos_origen', 'ciclo_origen', 'clasificacion', 'confianza']);
        });
    }
};
