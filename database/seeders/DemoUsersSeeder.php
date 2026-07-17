<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Crea las 7 cuentas demo del login (contraseña Demo#1234), una por perfil.
 * Idempotente y no destructivo: usa updateOrCreate, así se puede correr en
 * cualquier momento (incluido dentro de DatabaseSeeder) sin borrar usuarios reales.
 */
class DemoUsersSeeder extends Seeder
{
    public function run(): void
    {
        $usuariosDemo = [
            ['email' => 'admin.demo@usil.edu.pe',      'rol' => Role::SUPERUSUARIO, 'nombre' => 'Superusuario Demo'],
            ['email' => 'coord.demo@usil.edu.pe',      'rol' => Role::COORDINADOR,  'nombre' => 'Coordinador Demo'],
            ['email' => 'decano.demo@usil.edu.pe',     'rol' => Role::DECANO,       'nombre' => 'Decano Demo'],
            ['email' => 'consulta.demo@usil.edu.pe',   'rol' => Role::CONSULTA,     'nombre' => 'Consulta Demo'],
            ['email' => 'asesor.demo@usil.edu.pe',     'rol' => Role::ASESOR,       'nombre' => 'Asesor Demo'],
            ['email' => 'ejecutivo.demo@usil.edu.pe',  'rol' => Role::EJECUTIVO,    'nombre' => 'Ejecutivo Demo'],
            ['email' => 'director.demo@usil.edu.pe',   'rol' => Role::DIRECTOR,     'nombre' => 'Director Demo'],
            ['email' => 'auditor.demo@usil.edu.pe',    'rol' => Role::AUDITOR,      'nombre' => 'Auditor Demo'],
        ];

        foreach ($usuariosDemo as $u) {
            $rol = Role::where('nombre', $u['rol'])->first();
            if (! $rol) {
                continue;
            }

            $user = User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'nombre'        => $u['nombre'],
                    'password_hash' => Hash::make('Demo#1234'),
                    'rol_id'        => $rol->id,
                    'activo'        => true,
                    'primer_acceso' => false, // Pueden navegar sin cambiar clave de inmediato.
                ]
            );

            // Alcance de datos: sin asignaciones, los roles con alcance carrera/facultad
            // ven los listados vacíos. Las cuentas demo reciben todo lo existente.
            if ($rol->alcance() === 'carrera') {
                $user->carrerasPermitidas()->sync(\App\Models\Carrera::pluck('id'));
            } elseif ($rol->alcance() === 'facultad') {
                $user->facultadesPermitidas()->sync(\App\Models\Facultad::pluck('id'));
            }
        }
    }
}
