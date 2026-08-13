<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El postulante entra al portal con su correo. Con un índice no único, dos
 * registros podían compartirlo y el proveedor de autenticación resolvía a
 * cualquiera de los dos: quien entra ve el expediente de otra persona.
 *
 * El correo sigue siendo opcional. En un índice único de InnoDB cada NULL es
 * distinto, así que N postulantes sin correo conviven sin problema; lo que
 * queda prohibido es repetir un correo real.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->dropIndex('postulantes_email_index');
            $table->unique('email', 'uq_postulantes_email');
        });
    }

    public function down(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->dropUnique('uq_postulantes_email');
            $table->index('email', 'postulantes_email_index');
        });
    }
};
