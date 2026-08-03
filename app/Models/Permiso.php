<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Permiso granular (acción sobre un módulo). Se agrupan por rol vía rol_permiso.
 */
class Permiso extends Model
{
    protected $table = 'permisos';

    protected $fillable = ['clave', 'modulo', 'descripcion'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'rol_permiso', 'permiso_id', 'rol_id');
    }

    // -------- Catálogo de permisos (clave => [modulo, descripción]) --------
    public const CATALOGO = [
        // Panel
        'dashboard.ver' => ['Panel', 'Ver el panel principal'],
        // Solicitudes (postulantes / expedientes)
        'solicitudes.ver' => ['Solicitudes', 'Ver solicitudes de convalidación'],
        'solicitudes.crear' => ['Solicitudes', 'Registrar postulantes/solicitudes'],
        'solicitudes.editar' => ['Solicitudes', 'Editar datos del expediente'],
        'solicitudes.validar' => ['Solicitudes', 'Validar datos básicos del expediente'],
        'solicitudes.asignar' => ['Solicitudes', 'Asignar solicitud a un coordinador'],
        // Evaluación académica (equivalencias + simulación)
        'evaluacion.ver' => ['Evaluación', 'Ver evaluaciones y equivalencias'],
        'evaluacion.editar' => ['Evaluación', 'Registrar/editar equivalencias y mapeo'],
        'evaluacion.proponer' => ['Evaluación', 'Generar propuesta de preconvalidación'],
        'evaluacion.aprobar' => ['Evaluación', 'Aprobar la evaluación'],
        'evaluacion.observar' => ['Evaluación', 'Observar / devolver para corrección'],
        'evaluacion.reasignar' => ['Evaluación', 'Reasignar evaluaciones'],
        // Convalidación final
        'convalidacion.ver' => ['Convalidación', 'Ver convalidaciones confirmadas'],
        'convalidacion.confirmar' => ['Convalidación', 'Confirmar convalidación y memorándum'],
        'convalidacion.anular' => ['Convalidación', 'Anular una convalidación'],
        // Catálogos maestros
        'catalogos.gestionar' => ['Catálogos', 'Gestionar mallas e instituciones'],
        'estructura.gestionar' => ['Catálogos', 'Gestionar la estructura institucional'],
        // Mallas oficiales de las instituciones de origen. Permiso propio (y no
        // 'evaluacion.ver') para poder retirárselo a un rol sin quitarle Simulaciones.
        'mallas_externas.gestionar' => ['Catálogos', 'Registrar mallas oficiales de instituciones externas'],
        // Reportes
        'reportes.ver' => ['Reportes', 'Ver reportes e indicadores'],
        'reportes.exportar' => ['Reportes', 'Exportar reportes'],
        // Administración
        'usuarios.gestionar' => ['Administración', 'Gestionar usuarios, roles y alcance'],
        'configuracion.gestionar' => ['Administración', 'Configurar parámetros del sistema'],
        'auditoria.ver' => ['Administración', 'Consultar auditoría y trazabilidad'],
    ];

    // -------- Permisos por rol --------
    public const POR_ROL = [
        Role::SUPERUSUARIO => ['*'], // todos
        // Asesor de Admisión: registra postulantes y sus documentos; no evalúa ni aprueba.
        Role::ASESOR => [
            'dashboard.ver', 'solicitudes.ver', 'solicitudes.crear', 'solicitudes.editar',
        ],
        // Ejecutivo Comercial de Admisión: revisa, aprueba u observa; puede corregir datos.
        Role::EJECUTIVO => [
            'dashboard.ver', 'solicitudes.ver', 'solicitudes.editar', 'solicitudes.validar', 'reportes.ver',
        ],
        // Evalúa simulaciones de sus carreras; los postulantes los gestiona Admisión.
        // Sin 'mallas_externas.gestionar': no registra mallas de otras instituciones.
        Role::COORDINADOR => [
            'dashboard.ver', 'evaluacion.ver', 'evaluacion.editar',
            'evaluacion.proponer', 'catalogos.gestionar', 'reportes.ver',
        ],
        Role::DIRECTOR => [
            'dashboard.ver', 'solicitudes.ver', 'solicitudes.asignar', 'evaluacion.ver',
            'evaluacion.editar', 'evaluacion.proponer', 'evaluacion.aprobar', 'evaluacion.observar',
            'evaluacion.reasignar', 'catalogos.gestionar', 'mallas_externas.gestionar', 'reportes.ver',
        ],
        Role::DECANO => [
            'dashboard.ver', 'solicitudes.ver', 'evaluacion.ver', 'evaluacion.editar',
            'evaluacion.proponer', 'evaluacion.aprobar', 'convalidacion.ver',
            'convalidacion.confirmar', 'convalidacion.anular', 'mallas_externas.gestionar',
            'reportes.ver', 'reportes.exportar',
        ],
        Role::AUDITOR => [
            'dashboard.ver', 'solicitudes.ver', 'evaluacion.ver', 'convalidacion.ver',
            'auditoria.ver', 'reportes.ver', 'reportes.exportar',
        ],
        Role::CONSULTA => [
            'dashboard.ver', 'reportes.ver',
        ],
    ];
}
