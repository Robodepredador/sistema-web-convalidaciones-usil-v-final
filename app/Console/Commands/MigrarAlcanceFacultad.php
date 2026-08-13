<?php

namespace App\Console\Commands;

use App\Models\Carrera;
use App\Models\Role;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Migra a los Coordinadores y Directores de alcance por carrera a alcance por
 * facultad. Para cada usuario afectado, identifica las facultades a las que
 * pertenecen sus carreras asignadas y las registra en `permisos_facultad`,
 * limpiando después `permisos_carrera`.
 */
class MigrarAlcanceFacultad extends Command
{
    protected $signature = 'usuarios:migrar-alcance-facultad';

    protected $description = 'Migra Coordinadores y Directores de permisos_carrera a permisos_facultad';

    public function handle(): int
    {
        $roles = Role::whereIn('nombre', [Role::COORDINADOR, Role::DIRECTOR])->pluck('id');

        if ($roles->isEmpty()) {
            $this->warn('No se encontraron los roles Coordinador/Director.');

            return self::FAILURE;
        }

        $usuarios = User::whereIn('rol_id', $roles)
            ->whereHas('carrerasPermitidas')
            ->with('carrerasPermitidas')
            ->get();

        if ($usuarios->isEmpty()) {
            $this->info('No hay usuarios con carreras asignadas que migrar.');

            return self::SUCCESS;
        }

        $migrados = 0;

        foreach ($usuarios as $user) {
            // Obtener las facultades únicas de las carreras asignadas.
            $facultadIds = $user->carrerasPermitidas
                ->pluck('facultad_id')
                ->filter()
                ->unique()
                ->values()
                ->all();

            if (empty($facultadIds)) {
                $this->warn("  ⚠ {$user->nombre} ({$user->email}): sus carreras no tienen facultad asignada, se omite.");

                continue;
            }

            // Asignar facultades y limpiar carreras.
            $user->facultadesPermitidas()->sync($facultadIds);
            $user->carrerasPermitidas()->detach();

            $this->line("  ✓ {$user->nombre} ({$user->email}) → facultades: ".implode(', ', $facultadIds));
            $migrados++;
        }

        $this->info("Migración completada: {$migrados} usuario(s) trasladados a alcance por facultad.");

        return self::SUCCESS;
    }
}
