<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreUsuarioRequest;
use App\Http\Requests\UpdateUsuarioRequest;
use App\Mail\AccesoSistemaMail;
use App\Models\Carrera;
use App\Models\Facultad;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditoriaService;
use App\Support\EntregaCredenciales;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * CU-10: Gestionar Usuarios y Roles (Administrador).
 * RF-39 (roles), RF-40 (permisos por carrera), RF-42 (primer acceso).
 */
class UsuarioController extends Controller
{
    public function index(Request $request)
    {
        $usuarios = User::with('rol')
            ->when($request->q, fn ($x, $v) => $x->where(fn ($w) => $w->where('nombre', 'like', "%{$v}%")->orWhere('email', 'like', "%{$v}%")))
            ->when($request->rol_id, fn ($x, $v) => $x->where('rol_id', $v))
            ->when($request->estado === 'activo', fn ($x) => $x->where('activo', true))
            ->when($request->estado === 'inactivo', fn ($x) => $x->where('activo', false))
            ->orderBy('nombre')
            ->paginate(10)->withQueryString()
            ->through(fn (User $u) => [
                'id' => $u->id,
                'nombre' => $u->nombre,
                'email' => $u->email,
                'rol' => $u->rol?->nombre,
                'activo' => $u->activo,
                'primer_acceso' => $u->primer_acceso,
            ]);

        return inertia('Usuarios/Index', [
            'usuarios' => $usuarios,
            'activos' => User::where('activo', true)->count(),
            'roles' => Role::orderBy('nombre')->get(['id', 'nombre']),
            'filtros' => $request->only(['q', 'rol_id', 'estado']),
        ]);
    }

    public function create()
    {
        return inertia('Usuarios/Form', [
            'usuario' => null,
            'roles' => $this->rolesConAlcance(),
            'carreras' => Carrera::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'facultades' => Facultad::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    /** Roles con su alcance (para que el formulario muestre el selector correcto). */
    private function rolesConAlcance()
    {
        return Role::orderBy('nombre')->get(['id', 'nombre'])
            ->map(fn (Role $r) => ['id' => $r->id, 'nombre' => $r->nombre, 'alcance' => $r->alcance()]);
    }

    public function store(StoreUsuarioRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        // Contraseña temporal: el usuario la cambia en el primer acceso (RF-42).
        $temporal = Str::password(12);

        $user = User::create([
            'nombre' => $datos['nombre'],
            'email' => $datos['email'],
            'password_hash' => Hash::make($temporal),
            'rol_id' => $datos['rol_id'],
            'activo' => $datos['activo'] ?? true,
            'primer_acceso' => true,
        ]);

        $this->sincronizarCarreras($user, $datos);

        AuditoriaService::registrar('crear', 'usuarios', $user->id, null, ['email' => $user->email]);

        return redirect()->route('usuarios.index')
            ->with('status', 'Usuario creado. '.$this->enviarCredenciales($user, $temporal));
    }

    public function edit(User $usuario)
    {
        return inertia('Usuarios/Form', [
            'usuario' => [
                'id' => $usuario->id,
                'nombre' => $usuario->nombre,
                'email' => $usuario->email,
                'rol_id' => $usuario->rol_id,
                'activo' => $usuario->activo,
                'carreras' => $usuario->carrerasPermitidas()->pluck('carreras.id'),
                'facultades' => $usuario->facultadesPermitidas()->pluck('facultades.id'),
            ],
            'roles' => $this->rolesConAlcance(),
            'carreras' => Carrera::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'facultades' => Facultad::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function update(UpdateUsuarioRequest $request, User $usuario): RedirectResponse
    {
        $datos = $request->validated();
        $previos = $usuario->only(['nombre', 'email', 'rol_id', 'activo']);

        $usuario->update([
            'nombre' => $datos['nombre'],
            'email' => $datos['email'],
            'rol_id' => $datos['rol_id'],
            'activo' => $datos['activo'] ?? $usuario->activo,
        ]);

        $this->sincronizarCarreras($usuario, $datos);

        AuditoriaService::registrar('editar', 'usuarios', $usuario->id, $previos, $datos);

        return redirect()->route('usuarios.index')->with('status', 'Usuario actualizado.');
    }

    public function estado(Request $request, User $usuario): RedirectResponse
    {
        // Un administrador no puede desactivar su propia cuenta.
        abort_if($usuario->id === $request->user()->id && $usuario->activo, 422,
            'No puede desactivar su propia cuenta.');

        $usuario->update(['activo' => ! $usuario->activo]);
        AuditoriaService::registrar('editar', 'usuarios', $usuario->id, null, ['activo' => $usuario->activo]);

        return back()->with('status', $usuario->activo ? 'Usuario activado.' : 'Usuario desactivado.');
    }

    public function resetPassword(User $usuario): RedirectResponse
    {
        // Genera una contraseña temporal y fuerza el cambio en el próximo acceso (RF-42).
        $temporal = Str::password(12);

        $usuario->forceFill([
            'password_hash' => Hash::make($temporal),
            'primer_acceso' => true,
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
        ])->save();

        AuditoriaService::registrar('editar', 'usuarios', $usuario->id, null, ['reset_password' => true]);

        return back()->with('status',
            'Contraseña restablecida. '.$this->enviarCredenciales($usuario, $temporal));
    }

    /**
     * Entrega la contraseña temporal y devuelve el aviso que debe leer el
     * administrador. No rompe el alta si el envío falla —el usuario ya está
     * creado— pero tampoco afirma que se envió cuando no fue así.
     */
    private function enviarCredenciales(User $usuario, string $temporal): string
    {
        return EntregaCredenciales::enviar(
            $usuario->email,
            new AccesoSistemaMail($usuario, route('login'), $temporal),
            $temporal,
        );
    }

    public function destroy(User $usuario): RedirectResponse
    {
        // No se elimina: se desactiva para preservar trazabilidad histórica.
        $usuario->update(['activo' => false]);

        AuditoriaService::registrar('editar', 'usuarios', $usuario->id, null, ['activo' => false]);

        return redirect()->route('usuarios.index')->with('status', 'Usuario desactivado.');
    }

    private function sincronizarCarreras(User $user, array $datos): void
    {
        // Alcance según el rol: carrera (Coordinador/Director), facultad (Decano) o ninguno.
        $rol = Role::find($datos['rol_id']);
        $alcance = $rol?->alcance();

        if ($alcance === 'carrera') {
            $user->carrerasPermitidas()->sync($datos['carreras'] ?? []);
            $user->facultadesPermitidas()->detach();
        } elseif ($alcance === 'facultad') {
            $user->facultadesPermitidas()->sync($datos['facultades'] ?? []);
            $user->carrerasPermitidas()->detach();
        } else {
            $user->carrerasPermitidas()->detach();
            $user->facultadesPermitidas()->detach();
        }
    }
}
