<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $rolAdmin = Role::where('nombre', Role::ADMIN)->firstOrFail();

        User::updateOrCreate(
            ['email' => 'admin@usil.edu.pe'],
            [
                'nombre' => 'Administrador del Sistema',
                'password_hash' => Hash::make('Admin#2026'), // forzará cambio en primer acceso
                'rol_id' => $rolAdmin->id,
                'activo' => true,
                'primer_acceso' => true,
            ]
        );
    }
}
