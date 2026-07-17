<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->enum('revision_estado', ['pendiente', 'aprobada', 'observada'])
                ->default('pendiente')->after('estado');
            $table->text('revision_observaciones')->nullable()->after('revision_estado');
            $table->foreignId('revisado_por')->nullable()->after('revision_observaciones')
                ->constrained('usuarios')->nullOnDelete();
            $table->timestamp('revisado_en')->nullable()->after('revisado_por');
            $table->index('revision_estado');
        });
    }

    public function down(): void
    {
        Schema::table('postulantes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('revisado_por');
            $table->dropColumn(['revision_estado', 'revision_observaciones', 'revisado_en']);
        });
    }
};
