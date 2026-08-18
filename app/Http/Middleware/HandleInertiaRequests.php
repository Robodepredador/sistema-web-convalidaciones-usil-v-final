<?php

namespace App\Http\Middleware;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            // ponytail: solo el guard 'web' (staff) tiene rol/alcance/permisos; el
            // guard 'postulante' comparte un modelo distinto sin esos campos.
            'auth' => [
                'user' => $user instanceof User ? [
                    'id' => $user->id,
                    'nombre' => $user->nombre,
                    'email' => $user->email,
                    'rol' => $user->rol?->nombre,
                    'alcance' => $user->alcance(),
                    // Lista de permisos para gating de menú/botones en el frontend.
                    'permisos' => $user->esAdministrador()
                        ? ['*']
                        : $user->permisosClaves(),
                ] : null,
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'error' => fn () => $request->session()->get('error'),
                'reset_url' => fn () => $request->session()->get('reset_url'),
                'credenciales_generadas' => fn () => $request->session()->get('credenciales_generadas'),
            ],
        ]);
    }
}
