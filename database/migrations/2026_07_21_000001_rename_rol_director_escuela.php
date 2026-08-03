<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Renombra 'Director de Escuela' → 'Director de Carrera' conservando el id
 * del rol (los usuarios ya asignados quedan intactos).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->where('nombre', 'Director de Escuela')->update(['nombre' => 'Director de Carrera']);
    }

    public function down(): void
    {
        DB::table('roles')->where('nombre', 'Director de Carrera')->update(['nombre' => 'Director de Escuela']);
    }
};
