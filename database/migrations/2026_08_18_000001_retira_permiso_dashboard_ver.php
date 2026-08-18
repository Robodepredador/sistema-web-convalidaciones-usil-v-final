<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Retira `dashboard.ver`, un permiso que ningún sitio exige.
 *
 * La ruta `/` no lo comprueba: cualquier usuario autenticado ve el panel, y de
 * hecho el login aterriza ahí. Los cinco roles lo tenían concedido, así que
 * tampoco distinguía a nadie de nadie.
 *
 * Se eligió borrarlo antes que aplicarlo a la ruta. Exigirlo habría hecho cierta
 * la matriz al precio de un callejón sin salida: un rol nuevo al que se olvide
 * concederle el permiso entra al sistema y recibe un 403 en la primera pantalla,
 * sin ninguna pista de por qué.
 *
 * Misma razón que en la retirada del 10/08: un permiso que no se puede ejercer
 * solo desinforma a quien lea la matriz de roles.
 */
return new class extends Migration
{
    public function up(): void
    {
        // `rol_permiso` tiene cascadeOnDelete sobre permiso_id: borrar aquí
        // arrastra las asignaciones por rol sin dejar huérfanos.
        DB::table('permisos')->where('clave', 'dashboard.ver')->delete();
    }

    public function down(): void
    {
        DB::table('permisos')->updateOrInsert(
            ['clave' => 'dashboard.ver'],
            ['modulo' => 'Panel', 'descripcion' => 'Ver el panel principal', 'updated_at' => now(), 'created_at' => now()],
        );

        // Las asignaciones por rol NO se restauran aquí: dependen de
        // Permiso::POR_ROL, que vive en el código. Vuelven al revertir el código
        // y ejecutar `php artisan db:seed --class=RoleSeeder`.
    }
};
