<?php

namespace App\Services;

use App\Models\AuditoriaLog;
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
        // ponytail: usuario_id solo aplica al staff (guard 'web'); el guard
        // 'postulante' comparte el mismo Auth::id() pero no referencia `usuarios`.
        $usuario = Auth::user();

        AuditoriaLog::create([
            'usuario_id' => $usuario instanceof User ? $usuario->id : null,
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
