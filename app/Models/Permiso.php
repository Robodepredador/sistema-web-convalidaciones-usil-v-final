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
        // 'dashboard.ver' se retiró: la ruta `/` no lo exigía —el login aterriza
        // ahí y todo usuario autenticado ve el panel— y los cinco roles lo
        // tenían. No gateaba nada y desinformaba a quien leyera la matriz.
        // Solicitudes (postulantes / expedientes)
        'solicitudes.ver' => ['Solicitudes', 'Ver solicitudes de convalidación'],
        'solicitudes.crear' => ['Solicitudes', 'Registrar postulantes/solicitudes'],
        'solicitudes.editar' => ['Solicitudes', 'Editar datos del expediente'],
        // Sigue vigente: gatea POST /postulantes/{id}/revisar (aprobar/observar
        // el expediente de Admisión). No es la cadena de aprobación académica que
        // esta tarea retira -esa es evaluacion.aprobar/observar/reasignar/proponer-
        // así que no sale con ellos.
        'solicitudes.validar' => ['Solicitudes', 'Validar datos básicos del expediente'],
        // Evaluación académica (equivalencias + simulación)
        'evaluacion.ver' => ['Evaluación', 'Ver evaluaciones y equivalencias'],
        'evaluacion.editar' => ['Evaluación', 'Registrar/editar equivalencias y mapeo'],
        // Convalidación. Solo lectura: el sistema dejó de emitir el memorándum
        // oficial —ese acto se gestiona fuera— así que ya no hay nada que
        // confirmar ni anular aquí. La pantalla sobrevive como historial.
        'convalidacion.ver' => ['Convalidación', 'Ver el historial de preconvalidaciones'],
        // Catálogos maestros
        'catalogos.gestionar' => ['Catálogos', 'Gestionar mallas e instituciones'],
        'estructura.gestionar' => ['Catálogos', 'Gestionar la estructura institucional'],
        // Mallas oficiales de las instituciones de origen. Permiso propio (y no
        // 'evaluacion.ver') para poder retirárselo a un rol sin quitarle Simulaciones.
        // Catálogo de equivalencias: qué curso externo vale por qué curso USIL,
        // registrado una vez por el Especialista, sin importar el expediente.
        // Reservado para la pantalla del especialista (Task B3): todavía sin
        // consumidor, a propósito -a diferencia de 'auditoria.ver' más abajo,
        // que se retiró por lo mismo, este permiso llega antes que su pantalla,
        // no después de perderla-.
        'equivalencias.gestionar' => ['Equivalencias', 'Registrar el catálogo de equivalencias por curso'],
        // Administración
        'usuarios.gestionar' => ['Administración', 'Gestionar usuarios, roles y alcance'],
        // Sin ruta hoy: la pantalla de Configuración es la del motor de IA, que
        // se entrega apagado (ver README, «Motor de IA»). El permiso se conserva
        // —no se retira como 'dashboard.ver'— porque su pantalla existe y vuelve
        // entera el día que se decida encender la IA.
        'configuracion.gestionar' => ['Administración', 'Configurar parámetros del sistema'],
        // 'auditoria.ver' se retiró: `auditoria_log` se sigue escribiendo, pero no
        // existe pantalla para consultarlo. Un permiso que no se puede ejercer
        // solo desinforma a quien lea la matriz de roles. Vuelve con la pantalla.
    ];

    // -------- Permisos por rol --------
    public const POR_ROL = [
        Role::SUPERUSUARIO => ['*'], // todos
        // Especialista en Convalidaciones: registra la política una vez (mallas
        // propias y equivalencias contra cualquier institución). No opera
        // expedientes ni simulaciones: eso es del Administrativo.
        Role::ESPECIALISTA => [
            'catalogos.gestionar',
            'equivalencias.gestionar',
        ],
        // Administrativo de Facultad (antes Coordinador de Carrera): aplica la
        // política del Especialista sobre las simulaciones de sus carreras. Ya
        // no gestiona catálogos ni mallas externas ni accede al módulo de postulantes de admisión.
        Role::ADMINISTRATIVO => [
            'evaluacion.ver', 'evaluacion.editar',
            'convalidacion.ver',
        ],
        // Asesor de Admisión: registra postulantes y sus documentos; no evalúa ni aprueba.
        Role::ASESOR => [
            'solicitudes.ver', 'solicitudes.crear', 'solicitudes.editar',
        ],
        // Ejecutivo Comercial de Admisión: revisa, aprueba u observa el expediente
        // de Admisión; puede corregir datos. Ajeno a la cadena de aprobación
        // académica que esta tarea retira. Conserva 'solicitudes.validar' pese a
        // que el brief la retiraba: gatea POST /postulantes/{id}/revisar, una
        // ruta viva, y esa decisión queda para la Task A2 junto con el resto del
        // flujo de revisión de Admisión.
        Role::EJECUTIVO => [
            'solicitudes.ver', 'solicitudes.crear', 'solicitudes.editar', 'solicitudes.validar',
        ],
    ];
}
