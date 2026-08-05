<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Licenciamiento SUNEDU de la institución de procedencia.
 *
 * El catálogo afirmaba en un comentario que solo traía universidades
 * licenciadas, pero no había dónde registrarlo: ni para las que se dan de alta
 * a mano, ni para las que pierden la licencia. Es un dato del proceso —el
 * requisito de 72 créditos no aplica a universidades no licenciadas, y el
 * certificado de notas exigido es el expedido por SUNEDU—, así que el
 * expediente debe poder mostrarlo.
 *
 * 'desconocido' es el valor de partida: afirmar que todo lo ya cargado está
 * licenciado sería inventar el dato.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instituciones_externas', function (Blueprint $table) {
            $table->enum('licenciamiento', ['licenciada', 'no_licenciada', 'desconocido'])
                ->default('desconocido')->after('gestion');
            $table->string('licenciamiento_resolucion', 120)->nullable()->after('licenciamiento');
        });
    }

    public function down(): void
    {
        Schema::table('instituciones_externas', function (Blueprint $table) {
            $table->dropColumn(['licenciamiento', 'licenciamiento_resolucion']);
        });
    }
};
