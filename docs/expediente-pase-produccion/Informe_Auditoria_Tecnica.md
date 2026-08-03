# Informe de Auditoría Técnica — Sistema de Convalidaciones USIL

**Fecha:** 2 de agosto de 2026
**Alcance:** Base de datos (énfasis), capa de aplicación, despliegue y cumplimiento de los
Lineamientos Técnicos – Desarrollo App Web (Alcance Digital), v0.1, junio 2026.
**Método:** inspección de `information_schema`, verificación de integridad referencial sobre las
51 claves foráneas, 20 pruebas de reglas de negocio ejecutadas contra datos reales, revisión de
código de los flujos que emiten documentos oficiales, y pruebas funcionales en navegador.

---

## 1. Resumen ejecutivo

La estructura de la base de datos es **sólida**. Las 32 tablas están en InnoDB con colación
uniforme `utf8mb4_unicode_ci`, las 51 claves foráneas están declaradas y **no existe un solo
registro huérfano**. La normalización en 3FN se sostiene y los catálogos maestros tienen sus
índices únicos.

Los hallazgos no están en la estructura, sino en las **reglas que la estructura no protege**.
El sistema emite memorandos de convalidación —documentos oficiales con efecto académico— y
tres defectos permiten que esos documentos se emitan o queden en estados inválidos. Se
encontraron **registros reales ya afectados en la base de datos actual**, no solo la posibilidad
teórica.

| Severidad | Cantidad | Estado |
|---|---|---|
| Crítica | 3 | Corregidas |
| Alta | 4 | Corregidas |
| Media | 3 | Corregidas |
| Verificadas sin hallazgo | 12 | — |

---

## 2. Hallazgos críticos

### BD-01 · El número de memorando oficial admite duplicados

`convalidaciones.memorandum_numero` es `varchar(50) DEFAULT NULL` **sin restricción UNIQUE**.

```sql
UNIQUE KEY `convalidaciones_simulacion_id_unique` (`simulacion_id`)   -- única restricción
```

El valor se genera en `ConvalidacionController::confirmar()` como
`'MEMO-' . año . '-' . str_pad($simulacion->id, 5)`. Hoy no hay colisiones porque el id de
simulación es único, pero la base de datos no lo garantiza: cualquier corrección manual, una
migración de datos o un cambio en la fórmula puede producir dos resoluciones oficiales con el
mismo número, sin que nada lo impida.

**Riesgo:** dos expedientes académicos distintos identificados por el mismo número de resolución.

### BD-02 · Convalidaciones confirmadas sobre simulaciones eliminadas

`SimulacionController::destroy()` aplica *soft delete* sin verificar si la simulación ya
generó una convalidación confirmada:

```php
$simulacion->update(['motivo_eliminacion' => $datos['motivo']]);
$simulacion->delete();   // no consulta convalidacion()
```

**Registros afectados en la base de datos actual:**

| Convalidación | Memorando | Estado | Simulación eliminada el |
|---|---|---|---|
| 1 | MEMO-2026-00003 | confirmada | 2026-07-14 16:45:11 |
| 4 | MEMO-2026-00008 | confirmada | 2026-07-21 11:10:58 |

Son resoluciones vigentes cuyo sustento fue borrado. En la pantalla de Convalidaciones aparecen
como confirmadas con estudiante «—» y «Sin origen».

### BD-03 · Convalidaciones confirmadas sin ningún curso convalidado

`ConvalidacionController::confirmar()` no valida que la simulación tenga al menos una fila
convalidable no excluida antes de emitir el memorando.

**Registro afectado:** convalidación 3, `MEMO-2026-0002`, estado confirmada, 0 cursos, 0 créditos.

Se emitió una resolución oficial que no reconoce ningún curso.

---

## 3. Hallazgos altos

### BD-04 · Mallas externas duplicadas sin restricción

`mallas_externas` no tiene índice único sobre `(carrera_externa_id, anio, version)`.

**Dato real:** 3 mallas idénticas de SENATI, Ingeniería de Software con IA, 2026 v1, cada una
con sus 41 cursos. El evaluador que busca la malla oficial ve tres filas indistinguibles y no
puede saber cuál es la vigente.

### BD-05 · Rol huérfano con usuario activo y permisos fuera de control

```
Servicios Académicos    usuarios=1    permisos=7
```

El rol no existe en el código: sin constante en `App\Models\Role`, ausente de `RoleSeeder` y
ausente de `Permiso::POR_ROL`. Es un remanente de la división del rol en Asesor y Ejecutivo
Comercial (commit `e81461d`), que creó los roles nuevos pero no migró a los usuarios existentes.

**Consecuencia:** `servicios.demo@usil.edu.pe` conserva 7 permisos congelados que ningún seeder
mantiene y que no aparecen en la matriz de permisos del sistema. Es un acceso que no se puede
auditar desde el código.

### BD-06 · La tabla de auditoría no tiene índices de consulta

`auditoria_log` solo indexa `usuario_id` (por la FK). No hay índice sobre `registro_id` ni sobre
`tabla_afectada`, que son exactamente los campos por los que se consulta una traza:
«¿qué pasó con la convalidación 47?».

Es una tabla que solo crece —170 registros hoy, sin política de purga— y toda consulta de
auditoría hace recorrido completo. Contradice el §4 de los lineamientos («uso de índices en
campos clave», «consultas optimizadas»).

### BD-07 · Restos del catálogo de equivalencias desactivado

Confirmada con TI la eliminación del módulo, quedan en el esquema:

- Tabla `equivalencias` (0 filas) con sus 5 claves foráneas.
- Columna `simulacion_detalle.equivalencia_id` con FK a esa tabla (0 referencias).
- Valor `'catalogo'` en el enum `simulacion_detalle.origen` (0 usos).
- `EquivalenciaController::store/destroy`, el modelo `Equivalencia` y `SugerenciaController`
  sin rutas que los alcancen.

---

## 4. Hallazgos medios

### BD-08 · Usuarios activos con perfiles que no pueden iniciar sesión

`consulta.demo@usil.edu.pe` (Consulta / Alta Dirección) y `auditor.demo@usil.edu.pe` (Auditor)
figuran como **Activo** en Gestión de Usuarios, pero `LoginController::ROLES_SIN_ACCESO` les
niega el acceso. Un administrador que cree una cuenta con esos perfiles la verá activa y no
entenderá por qué el usuario no puede entrar.

### BD-09 · La auditoría no atribuye las acciones del postulante

`AuditoriaService::registrar()` guarda `usuario_id = null` cuando el actor no es del guard `web`.
Las acciones del portal del postulante quedan registradas sin autor, indistinguibles de una
acción del sistema. Para una traza de auditoría es una pérdida de trazabilidad.

### BD-10 · Cursos externos sin restricción de unicidad por malla

`cursos_externos` no restringe `(malla_externa_id, nombre)`. Hoy no hay duplicados, pero la
carga por IA puede reprocesar una malla y duplicar sus 41 cursos sin que nada lo impida.

---

## 4bis. Observación: datos personales enviados al proveedor de IA

No es un defecto de la base de datos, pero se detectó durante la revisión del código y debe
constar para la decisión del comité.

`IAConvalidacionService::extraerCursos()` envía el **documento del postulante tal cual** (PDF o
imagen del récord académico) al proveedor externo —Google Gemini por defecto— y el prompt pide
explícitamente extraer nombre, código y carrera del estudiante. Es inherente a la función: el
sistema necesita leer esos datos del certificado.

La clase `Seudonimizador` (que retira correos, documentos y teléfonos, invocando la Ley N.º
29733) existía en el proyecto pero **solo la usaba el asistente de sugerencias, que estaba
desconectado**. La ruta viva de extracción no la aplicaba.

Se corrigió lo que se podía corregir sin romper la función: la seudonimización se aplica ahora a
la rama de **texto plano/CSV** de `bloqueArchivo()`. No se aplica a PDF ni imagen porque ahí el
contenido es binario y una expresión regular lo corrompería.

**Queda abierto para decisión institucional:** el envío del récord académico completo a un
tercero requiere respaldo formal —acuerdo de tratamiento de datos con el proveedor y aviso de
privacidad al postulante—. No es algo que se resuelva en el código.

---

## 5. Verificaciones sin hallazgo

Se comprobaron y resultaron correctas:

| # | Verificación | Resultado |
|---|---|---|
| 1 | Integridad referencial de las 51 FK | 0 huérfanos |
| 2 | Motor de almacenamiento | 32/32 InnoDB |
| 3 | Colación | 32/32 `utf8mb4_unicode_ci` |
| 4 | Regla 1 a 1: curso USIL no repetido en una simulación | Sin violaciones |
| 5 | Curso USIL convalidado en dos resoluciones del mismo postulante | Sin violaciones |
| 6 | Filas no convalidables con destino asignado | Sin contradicciones |
| 7 | Créditos reconocidos mayores al curso destino | Sin violaciones |
| 8 | Créditos negativos o confianza fuera de 0–100 | Sin violaciones |
| 9 | Postulantes con correo duplicado | Sin duplicados |
| 10 | Mallas curriculares sin ciclos | Ninguna |
| 11 | Prerrequisitos cíclicos directos | Ninguno |
| 12 | Roles sin permisos asignados | Ninguno |

Nota metodológica: la prueba «fila convalidable sin curso destino» arrojó 90 coincidencias que
**no son defecto**. `SimulacionController::persistirSimulacion()` admite deliberadamente ese
estado: un curso aprobado en origen, convalidable en principio, para el que el evaluador no
encontró equivalente en la malla destino. Se descartó tras revisar el código.

---

## 6. Cumplimiento de los Lineamientos Técnicos

| § | Lineamiento | Estado |
|---|---|---|
| 1 | Stack tecnológico | Cumple. PHP 8.2.32, MySQL 8.0.46, Nginx 1.27, Vue 3. Laravel 11.54 (el lineamiento sugiere 10.x como opcional) |
| 2 | Arquitectura MVC y modular | Cumple. Capa `Services/` desacoplada de HTTP |
| 3 | Seguridad de la aplicación | Cumple tras corregir las cuentas demo |
| 4 | Base de datos | Cumple tras añadir los índices de auditoría |
| 5 | Integraciones API REST | **No cumple.** Ver §7 |
| 6 | Experiencia UX/UI | Cumple. Diseño responsive con breakpoints |
| 7 | Performance | Cumple. Redis, eager loading, consultas indexadas |
| 8 | Gestión de errores y monitoreo | Cumple. `LOG_CHANNEL=daily`, `auditoria_log` |
| 9 | Documentación | Parcial. 2 de 5 documentos completos |
| — | Accesibilidad | Cumple tras asociar etiquetas a campos |

---

## 7. Pendiente fuera del alcance de esta corrección

**§5 — API REST.** El sistema no expone servicios REST: no existe `routes/api.php` ni está
habilitado el enrutamiento `api` en `bootstrap/app.php`. Los endpoints JSON actuales son
llamadas internas del frontend, autenticadas por sesión.

El lineamiento exige dos endpoints mínimos: simulación de convalidaciones y consulta de
equivalencias. Ambos son construibles sobre la capa de servicios existente —`ConvalidacionEngine`
es lógica pura, sin acoplamiento a HTTP— y la consulta de equivalencias puede servirse desde
`simulacion_detalle` de las convalidaciones confirmadas, que conserva el mapeo curso externo ↔
curso USIL aunque el catálogo se haya retirado.

Esfuerzo estimado: 1,5 a 2 días. Requiere `laravel/sanctum`, que no está instalado.

---

## 8. Correcciones aplicadas y verificación

Todas las correcciones se implementaron y se verificaron ejecutando de nuevo la batería de
auditoría contra la base de datos.

### Migraciones

| Migración | Contenido |
|---|---|
| `2026_08_02_000001` | Índice único en `memorandum_numero`, `mallas_externas` y `cursos_externos`; índices de consulta en `auditoria_log` |
| `2026_08_02_000002` | Reasignación del rol huérfano, anulación de resoluciones inválidas, neutralización de cuentas demo en producción |
| `2026_08_02_000003` | Eliminación del esquema del catálogo de equivalencias |

### Guardas de negocio

- `ConvalidacionController::confirmar()` rechaza simulaciones sin cursos convalidados.
- `SimulacionController::destroy()` rechaza eliminar una simulación con convalidación vigente;
  exige anular primero.

### Resultado de la verificación

```
Convalidaciones confirmadas con problemas ....... 0   (antes 3)
Rol 'Servicios Académicos' ...................... eliminado
Tabla `equivalencias` / columna `equivalencia_id`  eliminadas
Mallas externas duplicadas ...................... 0   (3 → 1, cursos 123 → 41)
Cuentas demo creadas por un seed de producción .. 0   (antes 8)
```

Estado de las resoluciones tras el saneamiento: `MEMO-2026-00004` permanece **confirmada**
(20 cursos, 72 créditos); `MEMO-2026-0002` y `MEMO-2026-00008` quedaron **anuladas** con su
motivo registrado. Ninguna se eliminó: son documentos oficiales y su traza se conserva.

### Suite de pruebas

62 pruebas, 208 aserciones, todas en verde. Se añadieron 9 pruebas nuevas que cubren cada
guarda: confirmación sin cursos, confirmación con todas las filas excluidas, eliminación con
convalidación vigente, eliminación tras anular, unicidad del memorándum a nivel de base de
datos, y ausencia de cuentas demo en un despliegue de producción.

`./vendor/bin/pint --test` pasa (94 archivos reformateados), con lo que el pipeline de CI deja
de estar bloqueado en su primera etapa.

## 9. Conclusión

La base de datos está bien construida: la estructura, las relaciones y la normalización resisten
la revisión. Lo que faltaba eran las restricciones que impiden que un dato válido en forma sea
inválido en significado, y las guardas de negocio en los dos puntos donde el sistema emite
documentos con efecto oficial.

Corregidos los diez hallazgos y verificados contra la base de datos, el sistema queda apto para
el pase a producción con dos salvedades que exceden el alcance técnico de esta corrección: el
§5 (API REST), que debe resolverse por construcción o por excepción formal documentada, y el
respaldo legal del tratamiento de datos personales por el proveedor de IA (§4bis).
