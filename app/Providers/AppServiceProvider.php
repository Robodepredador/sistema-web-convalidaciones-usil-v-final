<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    /**
     * Aquí se registraba una policy sobre Carrera que nunca llegó a invocarse:
     * el control de acceso real lo hacen el middleware `permission` (qué puede
     * hacer el rol) y AlcanceService (sobre qué datos). Se retiró junto con la
     * clase para que la matriz de autorización tenga un solo sitio donde mirar.
     */
    public function boot(): void
    {
        //
    }
}
