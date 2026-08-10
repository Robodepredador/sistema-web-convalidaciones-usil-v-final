<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tablas de infraestructura del framework (caché y colas). Llevan la fecha
 * 0001_01_01 del esqueleto de Laravel a propósito: tienen que crearse ANTES que
 * las del dominio.
 *
 * No es cosmético. Con CACHE_STORE=database, la migración que siembra los cursos
 * no convalidables invalida la caché de CursoNoConvalidable al escribir, y si la
 * tabla `cache` todavía no existe la instalación entera se cae con
 * «Base table or view not found: 1146 Table 'cache' doesn't exist».
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};
