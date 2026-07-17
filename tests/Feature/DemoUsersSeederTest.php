<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoUsersSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_cuentas_de_admision(): void
    {
        $this->seed(\Database\Seeders\RoleSeeder::class);
        $this->seed(\Database\Seeders\DemoUsersSeeder::class);

        $this->assertDatabaseHas('usuarios', ['email' => 'asesor.demo@usil.edu.pe']);
        $this->assertDatabaseHas('usuarios', ['email' => 'ejecutivo.demo@usil.edu.pe']);
        $this->assertDatabaseMissing('usuarios', ['email' => 'servicios.demo@usil.edu.pe']);
    }
}
