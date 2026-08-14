<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * El sistema deja de depender de un proveedor externo de IA.
 *
 * El motor existía para proponerle al evaluador qué curso equivalía a cuál. En
 * el modelo nuevo esa decisión ya no es suya: el especialista declara de
 * antemano, tras comparar sílabos, todas las opciones válidas, y el
 * administrativo solo escoge dentro de esa lista. No queda nada que sugerir.
 *
 * Con el motor fuera, dos columnas pierden su significado:
 *
 *   - `simulaciones.metodo` distinguía 'manual' de 'ia'. Si todo es manual, no
 *     distingue nada.
 *   - `simulacion_detalle.confianza` guardaba el porcentaje de certeza del
 *     emparejamiento automático. Sin emparejamiento automático no hay certeza
 *     que medir.
 *
 * `simulacion_detalle.origen` SE CONSERVA con sus cuatro valores en vez de
 * colapsarla: las filas ya registradas dicen de dónde salieron, y reescribir
 * ese historial para que todas digan 'manual' seria falsearlo. Lo que cambia es
 * que de ahora en adelante solo se escribe 'manual'.
 *
 * `down()` recrea las columnas, NO los datos: los valores de confianza y de
 * método que hubiera no vuelven.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('simulaciones', 'metodo')) {
            Schema::table('simulaciones', function (Blueprint $table) {
                $table->dropColumn('metodo');
            });
        }

        if (Schema::hasColumn('simulacion_detalle', 'confianza')) {
            Schema::table('simulacion_detalle', function (Blueprint $table) {
                $table->dropColumn('confianza');
            });
        }

        // Las claves de IA de la tabla de configuración: hoy está vacía, pero
        // una base ya instalada puede tenerlas guardadas con su API key.
        DB::table('configuraciones')->where('clave', 'like', 'ia\_%')->delete();
    }

    public function down(): void
    {
        if (! Schema::hasColumn('simulaciones', 'metodo')) {
            Schema::table('simulaciones', function (Blueprint $table) {
                $table->enum('metodo', ['manual', 'ia'])->default('manual')->after('estado');
            });
        }

        if (! Schema::hasColumn('simulacion_detalle', 'confianza')) {
            Schema::table('simulacion_detalle', function (Blueprint $table) {
                $table->decimal('confianza', 5, 1)->nullable()->after('motivo');
            });
        }
    }
};
