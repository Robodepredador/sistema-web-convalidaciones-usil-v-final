<?php

use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // RF-39 / RBAC: control de acceso por rol.
        $middleware->alias([
            'role' => EnsureRole::class,
            'permission' => EnsurePermission::class,
            'postulante.cambiar' => \App\Http\Middleware\PostulanteDebeCambiarPassword::class,
        ]);

        // Inertia: comparte datos y maneja respuestas del lado servidor.
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // Redirección de invitados: el portal del postulante usa su propio login.
        $middleware->redirectGuestsTo(fn (Request $request) =>
            $request->is('portal*') ? route('portal.login') : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
