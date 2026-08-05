<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aprobación provisional del expediente.
 *
 * El Reglamento de Admisión (Art. 24) exige la presentación de TODOS los
 * documentos para que el postulante sea apto. La modalidad admite una vía
 * temporal —récord de notas con declaración jurada mientras llegan el
 * certificado y los sílabos—, y hasta ahora el sistema no distinguía una cosa
 * de la otra: aprobaba igual con cero documentos. La marca deja constancia de
 * que la aprobación está condicionada a regularizar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->boolean('revision_provisional')->default(false)->after('revision_estado');
        });
    }

    public function down(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->dropColumn('revision_provisional');
        });
    }
};
