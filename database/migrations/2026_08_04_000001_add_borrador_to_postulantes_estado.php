<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 'borrador' como estado propio del expediente.
 *
 * Hasta ahora "Guardar borrador" solo relajaba la validación: el registro nacía
 * 'nuevo', indistinguible de uno completo, y entraba a la cola de revisión del
 * Ejecutivo aunque le faltaran correo, carrera destino o ciclo.
 */
return new class extends Migration
{
    private const COMPLETOS = "'nuevo','en_evaluacion','admitido','rechazado','matriculado'";

    public function up(): void
    {
        DB::statement("ALTER TABLE postulantes MODIFY estado ENUM('borrador',".self::COMPLETOS.") NOT NULL DEFAULT 'nuevo'");
    }

    public function down(): void
    {
        // Estrechar el enum descartaría las filas en 'borrador': se reencauzan antes.
        DB::table('postulantes')->where('estado', 'borrador')->update(['estado' => 'nuevo']);
        DB::statement('ALTER TABLE postulantes MODIFY estado ENUM('.self::COMPLETOS.") NOT NULL DEFAULT 'nuevo'");
    }
};
