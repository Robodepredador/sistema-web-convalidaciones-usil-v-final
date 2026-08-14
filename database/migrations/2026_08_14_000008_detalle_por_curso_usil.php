<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // En la fase C, la granularidad de la tabla simulacion_detalle se invierte:
        // Pasa de ser "un curso de origen" a "un curso de USIL".
        // Un curso sin convalidar (que antes se creaba sin destino)
        // ahora será una fila con `curso_usil_id` y `curso_externo_id` en nulo.
        
        // Primero purgamos cualquier detalle huérfano (que no tenía asignación a USIL)
        // ya que bajo el nuevo paradigma ya no tiene sentido.
        DB::table('simulacion_detalle')->whereNull('curso_usil_id')->delete();

        Schema::table('simulacion_detalle', function (Blueprint $table) {
            $table->unsignedBigInteger('curso_usil_id')->nullable(false)->change();
            
            // `curso_origen_nombre` puede ser nulo ahora si el curso USIL no se convalida.
            $table->string('curso_origen_nombre', 200)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('simulacion_detalle', function (Blueprint $table) {
            $table->unsignedBigInteger('curso_usil_id')->nullable()->change();
            $table->string('curso_origen_nombre', 200)->nullable(false)->change();
        });
    }
};
