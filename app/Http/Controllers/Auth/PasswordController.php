<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\CambioPasswordRequest;
use App\Http\Requests\RestablecerPasswordRequest;
use App\Mail\RecuperarPasswordMail;
use App\Models\User;
use App\Rules\Correo;
use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Cambio de contraseña (RF-42: primer acceso) y recuperación (RF-39).
 */
class PasswordController extends Controller
{
    private const MINUTOS_VALIDEZ = 60;

    public function mostrar()
    {
        return inertia('Auth/CambiarPassword');
    }

    public function actualizar(CambioPasswordRequest $request): RedirectResponse
    {
        $user = $request->user();

        $user->forceFill([
            'password_hash' => Hash::make($request->validated()['password']),
            'primer_acceso' => false,
        ])->save();

        AuditoriaService::registrar('editar', 'usuarios', $user->id);

        return redirect()->route('dashboard')
            ->with('status', 'Contraseña actualizada correctamente.');
    }

    // --- RF-39: recuperación de contraseña ---

    public function solicitarForm()
    {
        return inertia('Auth/OlvidePassword');
    }

    public function enviarEnlace(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'email' => Correo::reglas(),
        ]);

        $user = User::where('email', $datos['email'])->where('activo', true)->first();

        // Respuesta neutra: no revelamos si el correo existe.
        if ($user) {
            // Guardamos solo el hash del token; el original viaja en el enlace.
            $token = Str::random(64);
            $user->forceFill([
                'token_recuperacion' => hash('sha256', $token),
                'token_expira' => now()->addMinutes(self::MINUTOS_VALIDEZ),
            ])->save();

            $url = route('password.restablecer.form', [
                'token' => $token,
                'email' => $user->email,
            ]);

            try {
                Mail::to($user->email)->send(new RecuperarPasswordMail($user, $url));
            } catch (\Throwable $e) {
                // Sin SMTP configurado el envío puede fallar; no rompemos el flujo.
                Log::warning('No se pudo enviar el correo de recuperación: '.$e->getMessage());
            }

            AuditoriaService::registrar('editar', 'usuarios', $user->id);

            // En local exponemos el enlace para poder probar sin servidor de correo.
            if (app()->environment('local')) {
                return back()->with('reset_url', route('password.restablecer.form', [
                    'token' => $token,
                    'email' => $user->email,
                ], false));
            }
        }

        return back()->with('status',
            'Si el correo está registrado, te enviamos un enlace para restablecer tu contraseña.');
    }

    public function restablecerForm(Request $request, string $token)
    {
        return inertia('Auth/RestablecerPassword', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function restablecer(RestablecerPasswordRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        $user = User::where('email', $datos['email'])->first();

        $tokenValido = $user
            && $user->token_recuperacion
            && $user->token_expira
            && $user->token_expira->isFuture()
            && hash_equals($user->token_recuperacion, hash('sha256', $datos['token']));

        if (! $tokenValido) {
            throw ValidationException::withMessages([
                'email' => 'El enlace de recuperación es inválido o ha expirado.',
            ]);
        }

        $user->forceFill([
            'password_hash' => Hash::make($datos['password']),
            'token_recuperacion' => null,
            'token_expira' => null,
            'primer_acceso' => false,
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
        ])->save();

        AuditoriaService::registrar('editar', 'usuarios', $user->id);

        return redirect()->route('login')
            ->with('status', 'Contraseña restablecida. Ya puedes iniciar sesión.');
    }
}
