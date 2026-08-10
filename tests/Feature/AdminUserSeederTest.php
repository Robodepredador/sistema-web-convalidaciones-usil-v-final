<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * El seeder del administrador es idempotente de verdad.
 *
 * El runbook manda ejecutar `db:seed` en cada actualización. Con `updateOrCreate`
 * y la contraseña escrita en el código, cada despliegue reponía una credencial
 * publicada en el repositorio y volvía a marcar `primer_acceso`.
 */
class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_el_administrador_con_contrasena_aleatoria(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(AdminUserSeeder::class);

        $admin = User::where('email', AdminUserSeeder::EMAIL)->firstOrFail();

        $this->assertTrue($admin->activo);
        $this->assertTrue((bool) $admin->primer_acceso, 'Debe forzar el cambio en el primer acceso (RF-42).');
        $this->assertSame(Role::ADMIN, $admin->rol->nombre);

        // La contraseña que estaba en el repositorio ya no sirve.
        $this->assertFalse(Hash::check('Admin#2026', $admin->password_hash),
            'El seeder volvió a usar la contraseña publicada en el código.');
    }

    public function test_resembrar_no_toca_al_administrador_existente(): void
    {
        $this->seed(RoleSeeder::class);
        $this->seed(AdminUserSeeder::class);

        // Situación real: el administrador ya entró y cambió su contraseña.
        $admin = User::where('email', AdminUserSeeder::EMAIL)->firstOrFail();
        $admin->forceFill([
            'password_hash' => Hash::make('LaQueEligioElCliente#2026'),
            'primer_acceso' => false,
        ])->save();

        // Un redespliegue vuelve a ejecutar los seeders.
        $this->seed(AdminUserSeeder::class);

        $admin->refresh();
        $this->assertTrue(Hash::check('LaQueEligioElCliente#2026', $admin->password_hash),
            'FUGA: re-sembrar restableció la contraseña del administrador.');
        $this->assertFalse((bool) $admin->primer_acceso,
            'Re-sembrar volvió a exigir el cambio de contraseña a un administrador que ya lo hizo.');
        $this->assertSame(1, User::where('email', AdminUserSeeder::EMAIL)->count());
    }
}
