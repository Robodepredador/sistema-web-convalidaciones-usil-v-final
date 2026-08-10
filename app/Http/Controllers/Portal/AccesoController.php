<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Postulante;
use App\Rules\Correo;
use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Acceso del postulante al portal de seguimiento (guard 'postulante').
 */
class AccesoController extends Controller
{
    public function mostrar()
    {
        return inertia('Portal/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'email' => Correo::reglas(),
            'password' => ['required', 'string'],
        ]);

        $postulante = Postulante::where('email', $datos['email'])->first();

        if (! $postulante || ! $postulante->acceso_habilitado || ! Hash::check($datos['password'], (string) $postulante->password_hash)) {
            throw ValidationException::withMessages([
                'email' => 'Credenciales inválidas o acceso deshabilitado.',
            ]);
        }

        Auth::guard('postulante')->login($postulante, $request->boolean('remember'));
        $postulante->forceFill(['ultimo_acceso' => now()])->save();
        $request->session()->regenerate();

        AuditoriaService::registrar('login', 'postulantes', $postulante->id);

        return redirect()->route('portal.seguimiento');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::guard('postulante')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login');
    }
}
