<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un postulante puede tener VARIAS carreras destino, y cada una avanza por el
 * flujo de equivalencias a su propio ritmo. Ese estado es del destino, no del
 * postulante: guardarlo también en el padre obliga a elegir cuál de los N
 * destinos representa, y la respuesta correcta es "ninguno".
 *
 * Las tres columnas quedaron sin lectura en toda la aplicación cuando se creó
 * postulante_destinos; solo seguían en $fillable. Se retiran.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->dropForeign(['equivalencias_revisado_por']);
            $table->dropIndex('postulantes_estado_equivalencias_index');
            $table->dropColumn(['estado_equivalencias', 'equivalencias_revisado_por', 'equivalencias_revisado_en']);
        });
    }

    public function down(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->enum('estado_equivalencias', ['pendiente', 'en_revision', 'aprobada'])
                ->default('pendiente')->after('revisado_en');
            $table->foreignId('equivalencias_revisado_por')->nullable()
                ->after('estado_equivalencias')->constrained('usuarios')->nullOnDelete();
            $table->timestamp('equivalencias_revisado_en')->nullable()->after('equivalencias_revisado_por');
            $table->index('estado_equivalencias', 'postulantes_estado_equivalencias_index');
        });
    }
};
