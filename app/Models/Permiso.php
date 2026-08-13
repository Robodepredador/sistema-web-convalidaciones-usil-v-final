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
        // Convalidación. Solo lectura: el sistema dejó de emitir el memorándum
        // oficial —ese acto se gestiona fuera— así que ya no hay nada que
        // confirmar ni anular aquí. La pantalla sobrevive como historial.
        'convalidacion.ver' => ['Convalidación', 'Ver el historial de preconvalidaciones'],
        // Catálogos maestros
        'catalogos.gestionar' => ['Catálogos', 'Gestionar mallas e instituciones'],
        'estructura.gestionar' => ['Catálogos', 'Gestionar la estructura institucional'],
        // Mallas oficiales de las instituciones de origen. Permiso propio (y no
        // 'evaluacion.ver') para poder retirárselo a un rol sin quitarle Simulaciones.
        'mallas_externas.gestionar' => ['Catálogos', 'Registrar mallas oficiales de instituciones externas'],
        // Administración
        'usuarios.gestionar' => ['Administración', 'Gestionar usuarios, roles y alcance'],
        'configuracion.gestionar' => ['Administración', 'Configurar parámetros del sistema'],
        // 'auditoria.ver' se retiró: `auditoria_log` se sigue escribiendo, pero no
        // existe pantalla para consultarlo. Un permiso que no se puede ejercer
        // solo desinforma a quien lea la matriz de roles. Vuelve con la pantalla.
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
            'dashboard.ver', 'solicitudes.ver', 'solicitudes.editar', 'solicitudes.validar',
        ],
        // Evalúa simulaciones de sus carreras; los postulantes los gestiona Admisión.
        // Con 'mallas_externas.gestionar' desde 2026-08-07: el mapeo de equivalencias
        // arranca subiendo la malla de la institución de origen, así que sin él
        // dependería de otro rol para el primer paso de su propio flujo. Nota: ese
        // permiso NO tiene alcance por carrera, de modo que podrá registrar mallas de
        // cualquier institución, no solo de las que le tocan.
        Role::COORDINADOR => [
            'dashboard.ver', 'catalogos.gestionar', 'mallas_externas.gestionar',
            'evaluacion.editar', 'evaluacion.ver', 'convalidacion.ver',
        ],
        Role::DIRECTOR => [
            'dashboard.ver', 'estructura.gestionar', 'catalogos.gestionar',
            'evaluacion.ver', 'convalidacion.ver', 'evaluacion.editar', 'evaluacion.proponer'
        ],
        Role::DECANO => [
            'dashboard.ver', 'convalidacion.ver', 'estructura.gestionar',
        ],
        Role::AUDITOR => [
            'dashboard.ver', 'solicitudes.ver', 'evaluacion.ver', 'convalidacion.ver',
        ],
        Role::CONSULTA => [
            'dashboard.ver',
        ],
    ];
}
