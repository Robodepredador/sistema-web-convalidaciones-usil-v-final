<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SimulacionController;
use App\Models\Convalidacion;
use App\Models\Simulacion;
use Illuminate\Support\Facades\Auth;

/**
 * Portal del postulante: consulta del PDF de preconvalidación.
 */
class PreconvalidacionController extends Controller
{
    /**
     * Muestra el PDF de una simulación propia ya confirmada.
     *
     * Dos puertas independientes: primero propiedad (una simulación ajena da
     * 404, no 403, para no confirmar que existe) y después el estado, porque
     * mientras Admisión no confirme la convalidación el resultado aún puede
     * cambiar y el postulante no debe verlo.
     */
    public function ver(int $simulacion)
    {
        $postulante = Auth::guard('postulante')->user();

        $sim = Simulacion::where('postulante_id', $postulante->id)->findOrFail($simulacion);

        abort_unless(
            $sim->convalidacion?->estado === Convalidacion::CONFIRMADA,
            403,
            'Tu convalidación aún no está confirmada.'
        );

        return app(SimulacionController::class)->renderPdf($sim, 'inline');
    }
}
