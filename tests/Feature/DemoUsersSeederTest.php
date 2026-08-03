<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoUsersSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_cuentas_de_admision(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(DemoUsersSeeder::class);

        $this->assertDatabaseHas('usuarios', ['email' => 'asesor.demo@usil.edu.pe']);
        $this->assertDatabaseHas('usuarios', ['email' => 'ejecutivo.demo@usil.edu.pe']);
        $this->assertDatabaseMissing('usuarios', ['email' => 'servicios.demo@usil.edu.pe']);
    }

    /**
     * BLOQUEANTE de seguridad: el RUNBOOK ejecuta `db:seed --force` en producción.
     * Sin esta guarda se creaba admin.demo@usil.edu.pe (Superusuario) con la
     * contraseña pública Demo#1234 y sin cambio forzado de clave.
     */
    public function test_no_crea_cuentas_demo_en_produccion(): void
    {
        $this->seed(RoleSeeder::class);

        $this->app['env'] = 'production';
        $this->artisan('db:seed', ['--class' => DemoUsersSeeder::class, '--force' => true]);

        $this->assertDatabaseMissing('usuarios', ['email' => 'admin.demo@usil.edu.pe']);
        $this->assertEquals(0, User::where('email', 'like', '%.demo@%')->count());
    }

    /** El seeder maestro (RUNBOOK: `db:seed --force`) tampoco arrastra demos. */
    public function test_database_seeder_no_arrastra_demos_en_produccion(): void
    {
        $this->app['env'] = 'production';
        $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true]);

        $this->assertEquals(0, User::where('email', 'like', '%.demo@%')->count());
        // El administrador inicial sí debe existir, con cambio de clave forzado.
        $this->assertDatabaseHas('usuarios', ['email' => 'admin@usil.edu.pe', 'primer_acceso' => true]);
    }
}
