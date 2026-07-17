# División del rol Servicios Académicos: Asesor y Ejecutivo Comercial de Admisión

Fecha: 2026-07-16
Estado: Aprobado (diseño)

## Contexto

Hoy existe un único rol `Servicios Académicos` (`Role::SERVICIOS`, alcance `global`)
que registra postulantes y consulta reportes. El negocio requiere separar esa
función en dos personas con responsabilidades distintas y una etapa de control de
calidad documental **antes** de que el expediente entre a evaluación académica.

## Objetivo

1. Reemplazar `Servicios Académicos` por **Asesor de Admisión** y **Ejecutivo Comercial de Admisión**.
2. Añadir una etapa de revisión: el Ejecutivo aprueba u observa cada expediente.
3. Impedir que el Coordinador **vea** cualquier solicitud que no haya sido aprobada
   por el Ejecutivo Comercial (no solo bloquear la acción: ocultarla).

## Decisiones de negocio (confirmadas)

- El rol actual `Servicios Académicos` se **elimina**; sus usuarios demo migran a Asesor.
- El **Asesor** solo ve/edita los postulantes que él mismo registró (`usuario_id`).
- El **Ejecutivo** ve todos los expedientes; puede aprobar, observar y también corregir datos/documentos.
- La observación se muestra al **asesor** (para corregir) y al **postulante** en el portal de seguimiento.
- El reenvío a revisión tras una observación es una **acción explícita** del asesor (botón), auditable.

## Modelo de datos

La revisión es sobre los datos/documentos del postulante (no depende de la carrera),
por lo que el estado vive en la tabla `postulantes`, no en `postulante_destinos`.
Una sola aprobación habilita todas las simulaciones de ese postulante.

Migración nueva sobre `postulantes`:

| Columna | Tipo | Notas |
|---|---|---|
| `revision_estado` | enum(`pendiente`,`aprobada`,`observada`) | default `pendiente` |
| `revision_observaciones` | text nullable | texto de lo que falta |
| `revisado_por` | FK `usuarios` nullable | quién decidió |
| `revisado_en` | timestamp nullable | cuándo |

## Roles y permisos

Se elimina `Role::SERVICIOS` y se agregan dos constantes de rol, ambas alcance `global`:

| Rol | Constante | Permisos |
|---|---|---|
| Asesor de Admisión | `Role::ASESOR` | `dashboard.ver`, `solicitudes.ver`, `solicitudes.crear`, `solicitudes.editar` |
| Ejecutivo Comercial de Admisión | `Role::EJECUTIVO` | `dashboard.ver`, `solicitudes.ver`, `solicitudes.editar`, `solicitudes.validar`, `reportes.ver` |

Se reutiliza el permiso existente `solicitudes.validar` ("Validar datos básicos del
expediente") para la acción de aprobar/observar. No se crean permisos nuevos.

## Máquina de estados de revisión

```
Asesor registra ──▶ pendiente
                      │  (Ejecutivo revisa)
        ┌─────────────┴─────────────┐
     aprobar                     observar (observaciones obligatorias)
        ▼                            ▼
    aprobada                     observada
   (habilita                        │  Asesor corrige y pulsa "Reenviar a revisión"
   simulaciones)                    └──▶ pendiente
```

- `pendiente` → `aprobada` | `observada`: acción `revisar` del Ejecutivo (`solicitudes.validar`).
- `observada` → `pendiente`: acción `reenviarRevision` del Asesor dueño (`solicitudes.editar`).
- El Ejecutivo puede editar datos del postulante en cualquier momento sin cambiar el estado.
- `observar` exige `revision_observaciones` no vacío.

## Reglas de acceso

### Asesor: solo lo suyo
- `PostulanteController@index`: si el rol es Asesor, filtrar `where('usuario_id', $user->id)`.
- `edit`, `update`, `destroy`, `reenviarRevision`: `abort_unless` de propiedad cuando es Asesor.
- Ejecutivo y Superusuario ven/editan todos.

### Coordinador: solo lo aprobado (requisito central)
El Coordinador llega a los postulantes por la pantalla de Simulaciones (permiso
`evaluacion.ver`), no por el módulo Postulantes. Se oculta lo no aprobado ahí:

- `SimulacionController@index`: filtrar la lista a postulantes con `revision_estado = 'aprobada'`.
- `DashboardController@bandeja`: la bandeja de las etapas de evaluación solo incluye destinos
  de postulantes aprobados.
- Defensa en profundidad: `SimulacionController@crear` y `@persistirSimulacion`
  hacen `abort_unless($postulante->revision_estado === 'aprobada', 403, '…aún no aprobada por el Ejecutivo Comercial…')`.

## Rutas nuevas

Dentro del grupo autenticado, sección de solicitudes:

```php
// Revisión (Ejecutivo Comercial)
Route::middleware('permission:solicitudes.validar')->group(function () {
    Route::post('postulantes/{postulante}/revisar', [PostulanteController::class, 'revisar'])
        ->name('postulantes.revisar');
});
// Reenvío tras observación (Asesor dueño)
Route::middleware('permission:solicitudes.editar')->group(function () {
    Route::post('postulantes/{postulante}/reenviar-revision', [PostulanteController::class, 'reenviarRevision'])
        ->name('postulantes.reenviar-revision');
});
```

## Cambios de UI

- **Postulantes/Index.vue**: badge de estado de revisión (pendiente/aprobada/observada).
  Para el Ejecutivo, filtro por `revision_estado` y acceso a la revisión. Para el Asesor,
  botón "Reenviar a revisión" cuando esté `observada`.
- **Postulantes/Form.vue**:
  - Ejecutivo: panel de revisión con botones Aprobar / Observar + textarea de observaciones.
  - Asesor: recuadro (solo lectura) con las observaciones cuando esté `observada`.
- **Simulaciones/Index.vue**: sin cambios visibles salvo que la lista ya llega filtrada
  a aprobados (el backend hace el filtro).
- **Portal/Seguimiento.vue**: si el postulante está `observada`, mostrar el aviso
  "Documentación observada: {observaciones}".
- **Dashboard.vue**: KPIs y bandeja para Asesor (registradas / observadas por corregir /
  aprobadas) y Ejecutivo (por revisar / observadas / aprobadas), reemplazando el
  `case Role::SERVICIOS`.

## Referencias a actualizar (rename/replace de SERVICIOS)

- `app/Models/Role.php`: constante y mapa `ALCANCE`.
- `app/Models/Permiso.php`: entradas de `POR_ROL`.
- `database/seeders/RoleSeeder.php`: lista de roles.
- `database/seeders/DemoUsersSeeder.php`: cuenta demo (→ Asesor + nueva cuenta Ejecutivo).
- `app/Http/Controllers/Auth/LoginController.php`: botones demo del login.
- `app/Http/Controllers/DashboardController.php`: `case Role::SERVICIOS` (líneas 62 y 129).
- `tests/Feature/RbacTest.php`: usa `Role::SERVICIOS` (línea 76).

## Auditoría

`AuditoriaService::registrar('aprobar' | 'observar' | 'reenviar', 'postulantes', $id, …)`
en `revisar` y `reenviarRevision`, reutilizando el servicio existente.

## Pruebas

Un archivo de feature test que cubra las rutas críticas (el dinero está en el gate):

1. Simulación bloqueada (403 / no aparece) cuando el postulante no está aprobado.
2. Tras aprobar, el postulante aparece en la lista del Coordinador y la simulación procede.
3. Observar exige texto; el asesor puede reenviar y vuelve a `pendiente`.
4. El Asesor solo ve sus propios postulantes; el Ejecutivo los ve todos.

## Fuera de alcance

- El permiso `solicitudes.asignar` (sin uso hoy) — se deja intacto.
- El desacople entre `postulantes.estado` y `postulante_destinos.estado_equivalencias` — no se toca.
