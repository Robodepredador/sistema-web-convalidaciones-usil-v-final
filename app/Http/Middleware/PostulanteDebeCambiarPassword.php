<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Fuerza el cambio de contraseña del postulante antes de acceder al portal. */
class PostulanteDebeCambiarPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        $postulante = Auth::guard('postulante')->user();

        if ($postulante && $postulante->debe_cambiar_password) {
            return redirect()->route('portal.password.cambiar.form');
        }

        return $next($request);
    }
}
