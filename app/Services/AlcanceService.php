<?php

namespace App\Services;

use App\Models\Carrera;
use App\Models\User;

/**
 * Resuelve el ALCANCE de datos de un usuario según su rol:
 *  - global   → ve todas las carreras (Superusuario, Servicios, Auditor, Consulta).
 *  - carrera  → solo sus carreras asignadas (Coordinador, Director de Carrera).
 *  - facultad → todas las carreras de su(s) facultad(es) (Decano).
 *
 * Devuelve la lista de carrera_id visibles, o null cuando no hay restricción.
 */
class AlcanceService
{
    /** IDs de carreras visibles para el usuario. null = sin restricción (todas). */
    public static function carrerasVisibles(User $user): ?array
    {
        switch ($user->alcance()) {
            case 'global':
                return null;

            case 'facultad':
                $facultades = $user->facultadesPermitidas()->pluck('facultades.id')->all();

                return Carrera::whereIn('facultad_id', $facultades ?: [0])->pluck('id')->all();

            case 'carrera':
            default:
                return $user->carrerasPermitidas()->pluck('carreras.id')->all();
        }
    }

    /** ¿El usuario puede ver/operar sobre esta carrera? */
    public static function alcanzaCarrera(User $user, ?int $carreraId): bool
    {
        if ($carreraId === null) {
            return true;
        }
        $ids = self::carrerasVisibles($user);

        return $ids === null || in_array($carreraId, $ids, true);
    }
}
