<?php

namespace Database\Seeders;

use App\Models\Carrera;
use App\Models\Facultad;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Cuentas demo del login (contraseña Demo#1234), una por perfil.
 * Idempotente y no destructivo: usa updateOrCreate, así se puede correr en
 * cualquier momento (incluido dentro de DatabaseSeeder) sin borrar usuarios reales.
 *
 * NUNCA en producción: la contraseña es pública (aparece en la pantalla de login
 * y en el repositorio) y una de las cuentas es Superusuario. La guarda vive aquí
 * —y no en DatabaseSeeder— para que también proteja la invocación directa
 * `php artisan db:seed --class=DemoUsersSeeder --force`.
 */
class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('DemoUsersSeeder omitido: no se crean cuentas demo en producción.');

            return;
        }

        $usuariosDemo = [
            ['email' => 'admin.demo@usil.edu.pe',          'rol' => Role::SUPERUSUARIO,   'nombre' => 'Superusuario Demo'],
            ['email' => 'especialista.demo@usil.edu.pe',   'rol' => Role::ESPECIALISTA,   'nombre' => 'Especialista Demo'],
            ['email' => 'administrativo.demo@usil.edu.pe', 'rol' => Role::ADMINISTRATIVO, 'nombre' => 'Administrativo Demo'],
            ['email' => 'asesor.demo@usil.edu.pe',         'rol' => Role::ASESOR,         'nombre' => 'Asesor Demo'],
            ['email' => 'ejecutivo.demo@usil.edu.pe',      'rol' => Role::EJECUTIVO,      'nombre' => 'Ejecutivo Demo'],
        ];

        foreach ($usuariosDemo as $u) {
            $rol = Role::where('nombre', $u['rol'])->first();
            if (! $rol) {
                continue;
            }

            $user = User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'nombre' => $u['nombre'],
                    'password_hash' => Hash::make('Demo#1234'),
                    'rol_id' => $rol->id,
                    'activo' => true,
                    'primer_acceso' => false, // Pueden navegar sin cambiar clave de inmediato.
                ]
            );

            // Alcance de datos: sin asignaciones, los roles con alcance carrera/facultad
            // ven los listados vacíos. Las cuentas demo reciben todo lo existente.
            if ($rol->alcance() === 'carrera') {
                $user->carrerasPermitidas()->sync(Carrera::pluck('id'));
            } elseif ($rol->alcance() === 'facultad') {
                $user->facultadesPermitidas()->sync(Facultad::pluck('id'));
            }
        }
    }
}
