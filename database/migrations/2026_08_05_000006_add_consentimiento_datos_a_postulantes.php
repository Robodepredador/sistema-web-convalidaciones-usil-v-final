<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Consentimiento para el tratamiento de datos personales.
 *
 * Art. 15 del Reglamento de Admisión: el postulante otorga su consentimiento
 * «de manera expresa e inequívoca», dejando constancia de que los datos pueden
 * comprender información de carácter sensible (Ley N.º 29733). El sistema no
 * registraba nada de eso, y sin embargo envía el récord académico completo
 * —nombre, documento y notas— al proveedor de IA.
 *
 * Se deja NULL en los expedientes ya registrados: dar por otorgado un
 * consentimiento que nadie declaró sería inventarlo. Se completa al editarlos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->timestamp('consentimiento_datos_en')->nullable()->after('observaciones');
        });
    }

    public function down(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->dropColumn('consentimiento_datos_en');
        });
    }
};
