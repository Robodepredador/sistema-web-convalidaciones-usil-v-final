<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditoriaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Autenticación (CU-09 / RF-38, RF-41, RF-42).
 * Reglas: contraseña cifrada (bcrypt), bloqueo tras 5 intentos fallidos,
 * cambio forzado de contraseña en el primer acceso.
 */
class LoginController extends Controller
{
    private const MAX_INTENTOS = 5;

    private const MINUTOS_BLOQUEO = 15;

    public function mostrar()
    {
        // Solo en entorno local: accesos rápidos para pruebas (no se exponen en producción).
        $usuariosDemo = app()->environment('local') ? [
            ['label' => 'Superusuario', 'email' => 'admin.demo@usil.edu.pe', 'password' => 'Demo#1234'],
            ['label' => 'Asesor de Admisión', 'email' => 'asesor.demo@usil.edu.pe', 'password' => 'Demo#1234'],
            ['label' => 'Ejecutivo Comercial de Admisión', 'email' => 'ejecutivo.demo@usil.edu.pe', 'password' => 'Demo#1234'],
            ['label' => 'Coordinador — Fac. Ingeniería e IA', 'email' => 'coord.demo@usil.edu.pe', 'password' => 'Demo#1234'],
            ['label' => 'Director — Fac. Ingeniería e IA', 'email' => 'director.demo@usil.edu.pe', 'password' => 'Demo#1234'],
            ['label' => 'Decano — Fac. Ingeniería e IA', 'email' => 'decano.demo@usil.edu.pe', 'password' => 'Demo#1234'],
        ] : [];

        return inertia('Auth/Login', ['usuariosDemo' => $usuariosDemo]);
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        /** @var User|null $user */
        $user = User::where('email', $datos['email'])->first();

        if (! $user || ! $user->activo) {
            throw ValidationException::withMessages([
                'email' => 'Credenciales inválidas o cuenta inactiva.',
            ]);
        }

        // RF-41: bloqueo temporal tras intentos fallidos
        if ($user->estaBloqueado()) {
            throw ValidationException::withMessages([
                'email' => 'Cuenta bloqueada temporalmente. Intente más tarde.',
            ]);
        }

        if (! Hash::check($datos['password'], $user->password_hash)) {
            $user->increment('intentos_fallidos');

            if ($user->intentos_fallidos >= self::MAX_INTENTOS) {
                $user->bloqueado_hasta = now()->addMinutes(self::MINUTOS_BLOQUEO);
                $user->save();
            }

            throw ValidationException::withMessages([
                'email' => 'Credenciales inválidas.',
            ]);
        }

        // Rol inhabilitado: se valida con la contraseña ya verificada para no
        // revelar el perfil de una cuenta a quien no conoce sus credenciales.
        if (in_array($user->rol?->nombre, Role::SIN_ACCESO, true)) {
            throw ValidationException::withMessages([
                'email' => 'El perfil asignado no tiene acceso al sistema.',
            ]);
        }

        // Éxito: limpiar contadores y abrir sesión
        $user->forceFill([
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
        ])->save();

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

        AuditoriaService::registrar('login', 'usuarios', $user->id);

        // RF-42: forzar cambio de contraseña en el primer acceso
        if ($user->primer_acceso) {
            return redirect()->route('password.cambiar.form');
        }

        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        AuditoriaService::registrar('logout', 'usuarios', Auth::id());

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
