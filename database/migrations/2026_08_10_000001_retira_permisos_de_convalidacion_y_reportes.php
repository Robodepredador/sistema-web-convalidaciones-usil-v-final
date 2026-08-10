<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Retira del RBAC los permisos que dejaron de tener ruta que los aplique.
 *
 *  - convalidacion.confirmar / convalidacion.anular → el sistema ya no emite el
 *    memorándum oficial; ese acto se gestiona fuera. Queda `convalidacion.ver`,
 *    que sigue abriendo la pantalla como historial de preconvalidaciones.
 *  - reportes.ver / reportes.exportar → el módulo de Reportes se retiró.
 *  - auditoria.ver → `auditoria_log` se sigue escribiendo, pero no hay pantalla
 *    para consultarlo. Vuelve cuando exista.
 *
 * Hace falta una migración y no basta el seeder: RoleSeeder crea y actualiza
 * permisos, y `sync()` corrige las asignaciones por rol, pero nunca borra una
 * clave que salió del catálogo. Sin esto quedarían filas huérfanas en `permisos`
 * de las instalaciones ya existentes.
 *
 * No se tocan las claves `memo_*` de `configuraciones`: son datos que alguien
 * escribió a mano y borrarlos no se puede deshacer. Quedan inertes.
 */
return new class extends Migration
{
    private const RETIRADOS = [
        'convalidacion.confirmar',
        'convalidacion.anular',
        'reportes.ver',
        'reportes.exportar',
        'auditoria.ver',
    ];

    /** Lo que se recrea en down(), tal como estaba en el catálogo. */
    private const CATALOGO_PREVIO = [
        'convalidacion.confirmar' => ['Convalidación', 'Confirmar convalidación y memorándum'],
        'convalidacion.anular' => ['Convalidación', 'Anular una convalidación'],
        'reportes.ver' => ['Reportes', 'Ver reportes e indicadores'],
        'reportes.exportar' => ['Reportes', 'Exportar reportes'],
        'auditoria.ver' => ['Administración', 'Consultar auditoría y trazabilidad'],
    ];

    public function up(): void
    {
        // `rol_permiso` tiene cascadeOnDelete sobre permiso_id: borrar aquí
        // arrastra las asignaciones por rol sin dejar huérfanos.
        DB::table('permisos')->whereIn('clave', self::RETIRADOS)->delete();

        // La pantalla dejó de listar convalidaciones confirmadas para listar el
        // historial de preconvalidaciones. Se corrige aquí y no solo en el
        // seeder, porque el procedimiento de actualización ya no lo ejecuta.
        DB::table('permisos')->where('clave', 'convalidacion.ver')
            ->update(['descripcion' => 'Ver el historial de preconvalidaciones']);
    }

    public function down(): void
    {
        foreach (self::CATALOGO_PREVIO as $clave => [$modulo, $descripcion]) {
            DB::table('permisos')->updateOrInsert(
                ['clave' => $clave],
                ['modulo' => $modulo, 'descripcion' => $descripcion, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        DB::table('permisos')->where('clave', 'convalidacion.ver')
            ->update(['descripcion' => 'Ver convalidaciones confirmadas']);

        // Las asignaciones por rol NO se restauran aquí: dependen de
        // Permiso::POR_ROL, que vive en el código. Vuelven al revertir el código
        // y ejecutar `php artisan db:seed --class=RoleSeeder`.
    }
};
