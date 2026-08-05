<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Congela en la convalidación los responsables del memorándum.
 *
 * Los firmantes y la unidad viven en Configuración y son editables. Al leerlos
 * en vivo, cambiarlos reescribía los documentos ya emitidos que hiciera falta
 * regenerar. Se guardan con la convalidación: cada memorándum conserva a quien
 * lo firmó, aunque después cambie el director.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('convalidaciones', function (Blueprint $table) {
            $table->json('responsables')->nullable()->after('memorandum_pdf_path');
        });
    }

    public function down(): void
    {
        Schema::table('convalidaciones', function (Blueprint $table) {
            $table->dropColumn('responsables');
        });
    }
};
