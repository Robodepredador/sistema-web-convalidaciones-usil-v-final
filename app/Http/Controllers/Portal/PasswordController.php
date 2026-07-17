<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/** Cambio de contraseña del postulante en el portal (primer acceso). */
class PasswordController extends Controller
{
    public function mostrar()
    {
        return inertia('Portal/CambiarPassword');
    }

    public function actualizar(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'password' => ['required', 'confirmed',
                Password::min(8)->letters()->numbers()->mixedCase()->symbols()],
        ]);

        $postulante = Auth::guard('postulante')->user();
        $postulante->forceFill([
            'password_hash'         => Hash::make($datos['password']),
            'debe_cambiar_password' => false,
        ])->save();

        AuditoriaService::registrar('editar', 'postulantes', $postulante->id, null, ['cambio_password' => true]);

        return redirect()->route('portal.seguimiento')->with('status', 'Contraseña actualizada.');
    }
}
