<?php

use App\Http\Middleware\EnsurePermission;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\PostulanteDebeCambiarPassword;
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
        // NO se confía en ningún proxy. Aquí había `trustProxies(at: '*')`,
        // necesario cuando la app vivía detrás de Nginx o del balanceador de
        // Railway. El despliegue es ahora Apache sirviendo directamente y
        // terminando TLS él mismo, así que ese ajuste dejó de hacer falta y pasó
        // a ser un riesgo: confiando en cualquier origen, un cliente puede
        // falsear la cabecera X-Forwarded-For y ensuciar el `ip_origen` de
        // `auditoria_log`, que es la trazabilidad de RNF-08.
        //
        // Si algún día se pone un proxy o balanceador delante, hay que declararlo
        // aquí con sus IP reales (ver deploy/RUNBOOK.md, §9).

        // RF-39 / RBAC: control de acceso por rol.
        $middleware->alias([
            'role' => EnsureRole::class,
            'permission' => EnsurePermission::class,
            'postulante.cambiar' => PostulanteDebeCambiarPassword::class,
        ]);

        // Inertia: comparte datos y maneja respuestas del lado servidor.
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        // Redirección de invitados: el portal del postulante usa su propio login.
        $middleware->redirectGuestsTo(fn (Request $request) => $request->is('portal*') ? route('portal.login') : route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
