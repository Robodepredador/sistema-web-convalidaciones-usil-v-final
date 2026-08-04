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
        // Detrás de un proxy TLS (Nginx en docker-compose.prod, el balanceador en
        // Railway) el tráfico llega a la app como HTTP plano. Sin esto:
        //   - url()/route() emitirían http:// bajo un dominio https → contenido
        //     mixto y los assets de Vite bloqueados por el navegador;
        //   - AuditoriaService registraría la IP del proxy en `ip_origen`, la
        //     misma para todos, y la trazabilidad de RNF-08 dejaría de servir.
        // Se confía en cualquier proxy porque en ambos despliegues el balanceador
        // es el único camino de entrada: la app no se expone directamente.
        $middleware->trustProxies(at: '*');

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
