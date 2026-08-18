<?php

namespace App\Http\Controllers\Estructura;

use App\Http\Controllers\Controller;
use App\Models\Carrera;
use App\Models\Facultad;
use App\Models\MallaCurricular;
use App\Models\Modalidad;
use App\Models\UnidadNegocio;

/**
 * Módulo: Gestión de la Estructura Institucional.
 * Sede → Facultad → Programa Académico → Modalidad → Malla Curricular.
 */
class EstructuraController extends Controller
{
    public function index()
    {
        return inertia('Estructura/Index', [
            'conteos' => [
                'sedes' => UnidadNegocio::count(),
                'facultades' => Facultad::count(),
                'programas' => Carrera::count(),
                'modalidades' => Modalidad::count(),
                'mallas' => MallaCurricular::count(),
            ],
        ]);
    }
}
