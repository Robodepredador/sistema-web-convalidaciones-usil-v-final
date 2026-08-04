<?php

namespace App\Services;

use App\Models\AuditoriaLog;
use App\Models\Postulante;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Registra toda operación relevante en auditoria_log (RNF-08).
 */
class AuditoriaService
{
    public static function registrar(
        string $accion,
        string $tablaAfectada,
        ?int $registroId = null,
        ?array $anteriores = null,
        ?array $nuevos = null
    ): void {
        // `usuario_id` tiene FK a `usuarios`: solo puede llevar al personal
        // (guard 'web'). Para no perder al autor cuando actúa el postulante o un
        // proceso del sistema, el par (actor_tipo, actor_id) identifica a
        // cualquiera de los tres. Ver migración 2026_08_03_000001.
        $usuario = Auth::user();
        $postulante = Auth::guard('postulante')->user();

        [$actorTipo, $actorId] = match (true) {
            $usuario instanceof User => ['usuario', $usuario->id],
            $postulante instanceof Postulante => ['postulante', $postulante->id],
            default => ['sistema', null],
        };

        AuditoriaLog::create([
            'usuario_id' => $usuario instanceof User ? $usuario->id : null,
            'actor_tipo' => $actorTipo,
            'actor_id' => $actorId,
            'accion' => $accion,
            'tabla_afectada' => $tablaAfectada,
            'registro_id' => $registroId,
            'valores_anteriores' => $anteriores,
            'valores_nuevos' => $nuevos,
            'ip_origen' => Request::ip(),
            'created_at' => now(),
        ]);
    }
}
