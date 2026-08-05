<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Por qué un curso de origen no se convalida.
 *
 * Hasta ahora la clasificación del curso de origen se decidía sola al cargarlo
 * y nadie podía cambiarla ni justificarla. Un curso que el evaluador dejaba sin
 * emparejar desaparecía del Excel: no entra en la hoja de convalidados (no
 * tiene destino) ni en la de descartados (no está marcado). El motivo lo puede
 * poner el evaluador o venir de la regla que lo descartó automáticamente.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('simulacion_detalle', function (Blueprint $table) {
            $table->string('motivo', 300)->nullable()->after('clasificacion');
        });
    }

    public function down(): void
    {
        Schema::table('simulacion_detalle', function (Blueprint $table) {
            $table->dropColumn('motivo');
        });
    }
};
