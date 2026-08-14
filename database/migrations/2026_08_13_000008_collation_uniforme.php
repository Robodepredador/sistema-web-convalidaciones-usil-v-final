<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * El esquema declaraba utf8mb4_0900_ai_ci por defecto mientras todas las
 * tablas existentes usan utf8mb4_unicode_ci. Hoy no falla nada porque están
 * alineadas entre sí; el problema es la próxima tabla que alguien cree sin
 * collation explícito: nacerá con la otra y el primer JOIN por texto contra
 * una tabla vieja fallará con "Illegal mix of collations", o resolverá pero
 * sin poder usar el índice.
 *
 * Se alinea el DEFAULT del esquema con lo que las tablas ya son. No se
 * convierten las tablas: eso reescribiría cada índice de texto sin ganar nada.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER DATABASE `'.DB::getDatabaseName().'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(): void
    {
        DB::statement('ALTER DATABASE `'.DB::getDatabaseName().'` CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci');
    }
};
