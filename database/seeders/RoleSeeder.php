<?php

namespace Database\Seeders;

use App\Models\Permiso;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        // Los 5 perfiles del flujo real del cliente (idempotente).
        $roles = [
            Role::SUPERUSUARIO => 'Administrador total del sistema',
            Role::ESPECIALISTA => 'Registra mallas USIL y equivalencias de sus carreras asignadas',
            Role::ADMINISTRATIVO => 'Atiende las simulaciones de sus carreras asignadas',
            Role::ASESOR => 'Registra al postulante, sus datos y documentos (Admisión)',
            Role::EJECUTIVO => 'Revisa y aprueba/observa los expedientes de admisión',
        ];

        foreach ($roles as $nombre => $descripcion) {
            Role::updateOrCreate(['nombre' => $nombre], ['descripcion' => $descripcion]);
        }

        // Catálogo de permisos.
        foreach (Permiso::CATALOGO as $clave => [$modulo, $descripcion]) {
            Permiso::updateOrCreate(['clave' => $clave], ['modulo' => $modulo, 'descripcion' => $descripcion]);
        }

        // Asignación de permisos por rol.
        $todos = Permiso::pluck('id', 'clave');
        foreach (Permiso::POR_ROL as $rolNombre => $claves) {
            $rol = Role::where('nombre', $rolNombre)->first();
            if (! $rol) {
                continue;
            }
            $ids = $claves === ['*']
                ? $todos->values()->all()
                : collect($claves)->map(fn ($c) => $todos[$c] ?? null)->filter()->values()->all();

            $rol->permisos()->sync($ids);
        }
    }
}
