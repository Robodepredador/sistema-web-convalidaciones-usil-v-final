<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DashboardAdmisionTest extends TestCase
{
    use RefreshDatabase;

    private function usuario(string $rolNombre): User
    {
        $this->seed(RoleSeeder::class);
        $rol = Role::where('nombre', $rolNombre)->firstOrFail();

        return User::create([
            'nombre' => $rolNombre, 'email' => uniqid().'@usil.edu.pe',
            'password_hash' => Hash::make('x'), 'rol_id' => $rol->id,
            'activo' => true, 'primer_acceso' => false,
        ]);
    }

    public function test_dashboard_asesor_ok(): void
    {
        $this->actingAs($this->usuario(Role::ASESOR))->get('/')->assertOk();
    }

    public function test_dashboard_ejecutivo_ok(): void
    {
        $this->actingAs($this->usuario(Role::EJECUTIVO))->get('/')->assertOk();
    }
}
