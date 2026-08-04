<?php

namespace App\Services;

use App\Models\Carrera;
use App\Models\Postulante;
use App\Models\User;

/**
 * Resuelve el ALCANCE de datos de un usuario según su rol:
 *  - global   → ve todas las carreras (Superusuario, Asesor, Ejecutivo, Auditor, Consulta).
 *  - carrera  → solo sus carreras asignadas (Coordinador, Director de Carrera).
 *  - facultad → todas las carreras de su(s) facultad(es) (Decano).
 *
 * `carrerasVisibles()` filtra los LISTADOS; `autorizarCarrera()` y
 * `autorizarPostulante()` protegen el acceso a un REGISTRO concreto. Los dos
 * lados son necesarios: filtrar el listado no impide entrar por la URL directa.
 */
class AlcanceService
{
    /** IDs de carreras visibles para el usuario. null = sin restricción (todas). */
    public static function carrerasVisibles(User $user): ?array
    {
        return match ($user->alcance()) {
            'global' => null,
            'facultad' => Carrera::whereIn('facultad_id',
                $user->facultadesPermitidas()->pluck('facultades.id')->all() ?: [0])->pluck('id')->all(),
            default => $user->carrerasPermitidas()->pluck('carreras.id')->all(),
        };
    }

    /**
     * ¿El usuario puede ver/operar sobre esta carrera?
     * Una carrera nula solo la alcanza quien no tiene restricción de alcance.
     */
    public static function alcanzaCarrera(User $user, ?int $carreraId): bool
    {
        $ids = self::carrerasVisibles($user);

        if ($ids === null) {
            return true;
        }

        return $carreraId !== null && in_array($carreraId, $ids, true);
    }

    /** Igual que alcanzaCarrera(), pero corta la petición con 403. */
    public static function autorizarCarrera(User $user, ?int $carreraId): void
    {
        abort_unless(self::alcanzaCarrera($user, $carreraId), 403,
            'Este expediente pertenece a una carrera fuera de su alcance.');
    }

    /**
     * ¿El postulante está dentro del alcance? Lo está si alguna de las carreras
     * que solicitó (postulante_destinos) es visible para el usuario. Es el mismo
     * criterio que usa PostulanteController::index, para que la URL directa y el
     * listado no puedan discrepar.
     */
    public static function alcanzaPostulante(User $user, Postulante $postulante): bool
    {
        $ids = self::carrerasVisibles($user);

        if ($ids === null) {
            return true;
        }

        return $postulante->destinos()->whereIn('carrera_id', $ids ?: [0])->exists();
    }

    /** Igual que alcanzaPostulante(), pero corta la petición con 403. */
    public static function autorizarPostulante(User $user, Postulante $postulante): void
    {
        abort_unless(self::alcanzaPostulante($user, $postulante), 403,
            'Este postulante está fuera de su alcance.');
    }
}
