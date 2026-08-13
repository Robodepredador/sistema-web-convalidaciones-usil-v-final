<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Siembra de la instalación. Idempotente: se puede volver a ejecutar.
 *
 * Todo lo que hay aquí es catálogo maestro sin el cual el proceso no arranca.
 * Hasta la auditoría del 10/08/2026 faltaba SuneduSeeder, así que una
 * instalación recién hecha se quedaba con CERO instituciones de origen: no se
 * podía registrar ni un solo postulante y nada lo explicaba.
 *
 * Lo que NO se siembra, a propósito:
 *   - Mallas y planes de estudio USIL (CargarPlanEstudiosJsonSeeder): las carga
 *     el coordinador desde la aplicación, que es donde vive esa competencia.
 *   - Carreras de cada institución externa (CarrerasExternasSeeder): catálogo
 *     genérico que conviene ajustar caso por caso. Disponible a mano si se
 *     quiere partir de algo: `php artisan db:seed --class=CarrerasExternasSeeder`.
 *   - Postulantes de ejemplo (PostulanteSeeder): datos de prueba.
 *   - DemoUsersSeeder se invoca pero él mismo se omite en producción.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,              // 8 perfiles y su matriz de permisos
            TipoInstitucionSeeder::class,   // Universidad / Instituto
            SuneduSeeder::class,            // instituciones de origen licenciadas
            AdminUserSeeder::class,         // administrador inicial (contraseña al azar)
            EstructuraSeeder::class,        // sedes y modalidades
            UsilPregradoSeeder::class,      // facultades y programas de USIL
            DemoUsersSeeder::class,         // cuentas demo — nunca en producción
        ]);
    }
}
