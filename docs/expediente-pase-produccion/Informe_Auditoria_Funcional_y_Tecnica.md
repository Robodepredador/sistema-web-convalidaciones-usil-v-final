# Informe de Auditoría Funcional y Técnica — Sistema de Convalidaciones USIL

**Fecha:** 3 de agosto de 2026 · **Estado:** hallazgos corregidos y verificados (ver §8 y §10)
**Alcance:** (1) auditoría funcional end-to-end simulando cada uno de los 9 actores del sistema;
(2) auditoría técnica de código, autorización, integridad y despliegue.
**Método:** 19 pruebas de integración HTTP ejecutadas contra MySQL 8.0.46 real
(`tests/Feature/AuditoriaE2ETest.php`), revisión de código de los 22 controladores y 8 servicios,
inspección de rutas y middleware, y análisis de dependencias.
**Rama auditada:** `main` @ `220c58f`.

---

## 1. Veredicto

> **Actualización (3 ago 2026, tarde).** Los 17 hallazgos descritos en este informe fueron
> **corregidos y verificados**. La batería completa pasa: **81 pruebas, 275 aserciones, 0 fallos**,
> `pint --test` en verde y `npm audit` sin vulnerabilidades. El detalle de cada corrección está en
> la **§10**. Lo que sigue documenta el estado **previo** a la corrección, que es lo que da sentido
> a las pruebas de regresión.
>
> Quedan abiertos los dos puntos de la **§7**, que no se resuelven con código: la API REST del §5
> de los lineamientos y el respaldo legal del tratamiento de datos por el proveedor de IA.

**Veredicto original de la auditoría: NO APTO para el pase a producción.**

La suite existente (62 pruebas) está en verde y el flujo principal del negocio funciona de
extremo a extremo. Pero esa suite prueba el **camino feliz con el Superusuario**, y el
Superusuario es precisamente el único perfil que atraviesa sin tocar el código de permisos ni el
código de alcance. Al ejecutar los mismos flujos con los otros 7 perfiles y con sus alcances
reales, el sistema falla en dos frentes distintos:

1. **Un fallo funcional duro:** el cambio obligatorio de contraseña (RF-42) devuelve **HTTP 500**
   para todos los perfiles salvo el Superusuario. Cada usuario que el administrador dé de alta
   queda imposibilitado de cambiar su contraseña temporal.
2. **El alcance por rol (RF-40) se aplica solo al listar, nunca al abrir un registro.** Los
   listados filtran correctamente; el acceso directo por URL no valida nada. Un coordinador puede
   leer, editar y eliminar expedientes de carreras que no le corresponden, y descargar el récord
   académico de cualquier postulante de la universidad.

| Severidad | Cantidad | Bloquea el pase |
|---|---|---|
| Bloqueante (P0) | 5 | Sí |
| Alta (P1) | 4 | Sí |
| Media (P2) | 8 | No |
| Verificado sin hallazgo | 11 | — |

Estimación de corrección de P0 + P1: **3 a 4 días** de un desarrollador, más regresión.

---

## 2. Auditoría funcional — resultados por actor

Escenario de prueba: dos facultades (Ingeniería / Negocios), una carrera en cada una, dos asesores
distintos con un postulante propio, un coordinador asignado **solo** a Ingeniería y un decano
asignado **solo** a la facultad de Ingeniería.

| # | Actor | Flujo probado | Resultado |
|---|---|---|---|
| 1 | Asesor de Admisión | Registrar postulante → recibe código, queda `pendiente` | ✅ Correcto |
| 2 | Ejecutivo Comercial | Observar expediente → exige texto de observación | ✅ Correcto |
| 3 | Asesor de Admisión | Corregir y reenviar a revisión | ✅ Correcto |
| 4 | Ejecutivo Comercial | Aprobar → avanza a `en_evaluacion` | ✅ Correcto |
| 5 | Coordinador | Simular antes de la aprobación → 403 | ✅ Correcto |
| 6 | Coordinador | Generar preconvalidación (4 créditos reconocidos) | ✅ Correcto |
| 7 | Decano | Confirmar convalidación → memorándum descargable | ✅ Correcto |
| 8 | Postulante | Login al portal → ver seguimiento | ✅ Correcto |
| 9 | Auditor / Consulta | Login rechazado pese a figurar «Activo» | ⚠️ Ver P2-04 |
| 10 | **Cualquier perfil salvo Superusuario** | **Cambiar contraseña en primer acceso** | ❌ **HTTP 500** |
| 11 | Coordinador (Ing.) | Leer / editar / eliminar simulación de Negocios | ❌ Permitido |
| 12 | Coordinador (Ing.) | Descargar récord académico de postulante ajeno | ❌ Permitido |
| 13 | Coordinador (Ing.) | Ver datos personales de toda la universidad en Reportes | ❌ Permitido |
| 14 | Decano (Ing.) | Confirmar convalidación de Negocios (memorándum oficial) | ❌ Permitido |
| 15 | Decano (Ing.) | Ver convalidaciones de Negocios buscando por memorándum | ❌ Permitido |
| 16 | Asesor A | Leer / descargar / alterar expedientes del Asesor B | ❌ Permitido |
| 17 | Anónimo | 30 intentos de contraseña contra el portal | ❌ Sin bloqueo |

**Conclusión funcional:** el proceso de negocio (registro → revisión → evaluación → convalidación
→ seguimiento) está correctamente implementado y encadenado. Lo que falla es la **segmentación
entre actores**: cada rol hace bien su trabajo, y además puede hacer el de los demás.

---

## 3. Hallazgos bloqueantes (P0)

### P0-01 · El cambio de contraseña obligatorio devuelve HTTP 500 para 7 de los 8 perfiles

`User::permisosClaves()` guarda su caché **dentro del arreglo de atributos del modelo**:

```php
// app/Models/User.php:63
if (! isset($this->attributes['_permisos_cache'])) {
    $this->attributes['_permisos_cache'] = $this->rol->permisos()->pluck('clave')->all();
}
```

`_permisos_cache` no es una columna. Eloquent lo ve como atributo sucio y lo incluye en el
siguiente `UPDATE`. Y el caché se llena en **todas** las peticiones, porque Inertia comparte los
permisos con el frontend antes de que corra el controlador:

```php
// app/Http/Middleware/HandleInertiaRequests.php:29
'permisos' => $user->esAdministrador() ? ['*'] : $user->permisosClaves(),
```

Cuando `PasswordController::actualizar()` guarda la contraseña nueva (línea 38), la consulta que
sale es:

```sql
update `usuarios` set `_permisos_cache` = ["dashboard.ver","evaluacion.ver", ...],
       `password_hash` = ?, `primer_acceso` = 0 where `id` = 106
-- SQLSTATE[42S22]: Unknown column '_permisos_cache' in 'field list'
```

**Por qué nadie lo detectó:** el Superusuario corta en `['*']` y nunca llama a `permisosClaves()`.
Todas las pruebas manuales se hicieron con `admin.demo@usil.edu.pe`. Las dos pruebas de este
informe lo demuestran lado a lado: el mismo POST pasa con Superusuario y revienta con Coordinador.

**Impacto operativo:** todo usuario dado de alta por el administrador recibe una contraseña
temporal (que se le muestra al administrador en pantalla) y **no puede cambiarla nunca**. La
contraseña inicial de cada cuenta del sistema queda permanentemente en manos de quien la creó.
RF-42 no se cumple.

**Corrección:** mover el caché a una propiedad del objeto.

```php
private ?array $permisosCache = null;

public function permisosClaves(): array
{
    return $this->permisosCache ??= $this->rol ? $this->rol->permisos()->pluck('clave')->all() : [];
}
```

**Prueba:** `test_cambio_de_password_en_primer_acceso_por_http`,
`test_cambio_de_password_del_superusuario_si_funciona`.

---

### P0-02 · El alcance por rol (RF-40) no se aplica al abrir un registro

`AlcanceService::alcanzaCarrera()` está escrito para esto exactamente:

```php
public static function alcanzaCarrera(User $user, ?int $carreraId): bool
```

y tiene **cero invocaciones** en todo el repositorio (`grep -rn "alcanzaCarrera" app/` → solo su
propia definición). El trait `FiltraPorCarrera` tampoco lo usa ningún modelo.

Los listados sí filtran (`SimulacionController::index` línea 53, `ConvalidacionController::index`
línea 51, `PostulanteController::index` línea 35). Ninguno de los métodos que operan sobre un
registro concreto lo hace:

| Ruta | Controlador | Alcance verificado |
|---|---|---|
| `GET /simulaciones/{id}` | `SimulacionController::show` | ❌ |
| `GET /simulaciones/{id}/editar` | `SimulacionController::editar` | ❌ |
| `PUT /simulaciones/{id}` | `SimulacionController::update` | ❌ |
| `DELETE /simulaciones/{id}` | `SimulacionController::destroy` | ❌ |
| `PATCH /simulaciones/{id}/detalle/{d}` | `SimulacionController::toggleDetalle` | ❌ |
| `GET /simulaciones/{id}/pdf` | `SimulacionController::generarPdf` | ❌ |
| `GET /simulaciones/{id}/excel` | `SimulacionController::exportarExcel` | ❌ |
| `GET /simulaciones/simular/{postulante}` | `SimulacionController::crear` | ❌ |

**No es una decisión de diseño: es una omisión.** `MallaController` implementa el mismo control
correctamente, con `autorizarCarrera()` invocado en sus 12 métodos de lectura y escritura. El
patrón existe en el código; a Simulaciones no se le aplicó.

**Evidencia:** un coordinador asignado exclusivamente a Ing. de Software obtuvo `200 OK` al leer,
editar y eliminar una simulación de Administración.

**Prueba:** `test_coordinador_lee_simulacion_fuera_de_su_alcance`,
`test_coordinador_modifica_simulacion_fuera_de_su_alcance`,
`test_coordinador_descarga_preconvalidacion_ajena`.

---

### P0-03 · El récord académico de cualquier postulante es descargable por cualquier evaluador

```php
// app/Http/Controllers/SimulacionController.php:190
public function verDocumento(PostulanteDocumento $documento)
{
    abort_unless(Storage::exists($documento->ruta), 404, '...');
    return Storage::response($documento->ruta, $documento->nombre_original);
}
```

La ruta `GET /documentos/{documento}/ver` está protegida únicamente por `permission:evaluacion.ver`.
No comprueba a qué postulante pertenece el documento, ni si ese postulante está en el alcance del
usuario. Recorriendo ids de 1 a N se descarga el expediente documental completo de la universidad:
certificados de estudios, constancias y sílabos, que contienen nombre completo, documento de
identidad y notas.

Es tratamiento de datos personales sensibles fuera de la finalidad autorizada (Ley N.º 29733).

**Prueba:** `test_documento_personal_accesible_por_cualquier_evaluador`.

---

### P0-04 · El módulo de Reportes no aplica ningún filtro de alcance

`ReporteController` no tiene una sola referencia a `AlcanceService`. `index()`, `consultarCursos()`,
`consultarResumen()` y `exportar()` consultan `SimulacionDetalle` y `Convalidacion` sin restricción
por carrera ni por facultad.

Cualquier usuario con `reportes.ver` —Coordinador, Director, Decano, Ejecutivo, Auditor, Consulta—
obtiene en pantalla el nombre, el documento de identidad, la carrera, los cursos y las notas de
**todos** los postulantes de la universidad. Con `reportes.exportar` (Decano, Auditor) todo eso se
descarga en un Excel.

**Prueba:** `test_reportes_ignoran_el_alcance_por_rol`.

---

### P0-05 · El buscador evade el filtro de alcance (precedencia AND/OR en SQL)

`ConvalidacionController::index` construye el filtro de búsqueda sin agrupar el `OR`:

```php
// línea 76 — convalidaciones
->when($q, function ($query) use ($q) {
    $query->whereHas('simulacion', fn ($sq) => $sq->where('nombres','like',"%{$q}%")->...)
          ->orWhere('memorandum_numero', 'like', "%{$q}%");     // <-- fuera del grupo
});

// línea 122 — preconvalidaciones
->when($q, function ($query) use ($q) {
    $query->where('nombres', 'like', "%{$q}%")
          ->orWhere('apellidos', 'like', "%{$q}%")              // <-- fuera del grupo
          ->orWhere('numero_documento', 'like', "%{$q}%");
});
```

Como `AND` tiene mayor precedencia que `OR`, la condición efectiva es:

```sql
(alcance AND estado AND nombres LIKE x) OR (apellidos LIKE x) OR (numero_documento LIKE x)
```

El filtro de alcance queda anulado en cuanto la coincidencia cae en cualquier rama que no sea la
primera. Un decano de Ingeniería que busque por apellido o por número de memorándum ve las
convalidaciones y preconvalidaciones de las demás facultades.

`PostulanteController::index` (línea 47) **sí** agrupa correctamente el mismo filtro con
`->where(fn ($w) => ...)`. Es una inconsistencia entre dos módulos, no un criterio deliberado.

**Prueba:** `test_buscador_de_convalidaciones_evade_el_alcance`,
`test_buscador_de_preconvalidaciones_evade_el_alcance`.

---

## 4. Hallazgos altos (P1)

### P1-01 · Un asesor accede a los expedientes de los demás asesores

`PostulanteController::autorizarPropiedad()` implementa la regla correcta y se invoca en `edit`,
`update`, `destroy`, `resetAcceso` y `reenviarRevision`. Falta en tres puntos:

| Ruta | Método | Consecuencia |
|---|---|---|
| `GET /postulantes/{id}/preconvalidacion` | `preconvalidacion()` | Lee el expediente completo en JSON |
| `GET /postulantes/{id}/preconvalidacion/{sim}/pdf` | `preconvalidacionPdf()` | Descarga el PDF |
| `PATCH /postulantes/{id}/estado` | `estado()` | Cambia el estado a `rechazado`, `admitido`, etc. |

El tercero es el más grave: permite alterar el estado de un expediente ajeno sin dejar rastro de
que quien lo hizo no era su responsable.

**Prueba:** `test_asesor_lee_expediente_de_otro_asesor`,
`test_asesor_descarga_preconvalidacion_ajena`, `test_asesor_cambia_estado_de_postulante_ajeno`.

### P1-02 · El decano emite y anula memorandos oficiales de otras facultades

`ConvalidacionController::confirmar()`, `anular()` y `memorandumPdf()` no verifican el alcance por
facultad. El decano de Ingeniería confirmó una convalidación de Administración y generó su
memorándum: un documento oficial con efecto académico firmado por una autoridad sin competencia
sobre esa carrera.

**Prueba:** `test_decano_confirma_convalidacion_de_otra_facultad`.

### P1-03 · El portal del postulante admite fuerza bruta sin límite

- `POST /portal/login` no tiene `throttle`, ni contador de intentos, ni bloqueo temporal.
  30 intentos consecutivos con contraseña errónea: ninguno rechazado por límite de tasa.
- `POST /login` (personal) tiene bloqueo por cuenta (RF-41, 5 intentos / 15 min) pero **tampoco
  tiene `throttle` por IP**: no hay defensa contra el barrido de muchas cuentas desde un mismo
  origen, y el bloqueo por cuenta permite además dejar fuera a un usuario legítimo a voluntad.

Corrección: `->middleware('throttle:5,1')` en ambas rutas de login, y contador de intentos en el
portal equivalente al de RF-41.

**Prueba:** `test_login_del_portal_sin_limite_de_intentos`.

### P1-04 · Descargar el PDF (petición GET) modifica el expediente

```php
// SimulacionController::generarPdf
$simulacion->update(['pdf_path' => $ruta, 'estado' => 'enviada']);
AuditoriaService::registrar('crear', 'simulaciones', $simulacion->id, ...);
```

Un `GET` cambia el estado de la simulación de `generada` a `enviada`, reescribe el archivo en
almacenamiento y añade una entrada de auditoría de tipo `crear` en cada descarga. Consecuencias:

- El **Auditor**, perfil declarado de solo lectura, altera el expediente al descargar su PDF.
- El estado `enviada` deja de significar «enviada al postulante» y pasa a significar «alguien
  abrió el PDF alguna vez».
- La traza de auditoría se llena de eventos `crear` que son en realidad lecturas.

**Prueba:** `test_descargar_pdf_muta_el_estado_de_la_simulacion`.

---

## 5. Hallazgos medios (P2)

| # | Hallazgo | Detalle |
|---|---|---|
| P2-01 | **N+1 en el listado de simulaciones** | `SimulacionController::index` ejecuta un `COUNT` sobre `simulaciones` por cada fila. Medido: 6 consultas para 6 filas; con la paginación de 12, 12 consultas por carga. Se resuelve con `withCount` sobre la relación. Prueba: `test_listado_de_simulaciones_sin_n_mas_1` |
| P2-02 | **El grupo de rutas de administración usa semántica OR** | `permission:usuarios.gestionar,configuracion.gestionar,estructura.gestionar` cubre a la vez Usuarios, Configuración y Estructura, y `EnsurePermission` pasa con **uno solo** de los tres. Hoy no hay fuga porque solo el Superusuario los tiene, pero conceder `estructura.gestionar` a un Director le entregaría también la gestión de usuarios. Deben ser tres grupos separados |
| P2-03 | **Contraseñas temporales expuestas en pantalla y en sesión** | `UsuarioController::store` y `PostulanteController::store`/`resetAcceso` devuelven la contraseña en claro dentro del mensaje flash. Queda en la sesión, en el historial del navegador del operador y —si se activa— en el log de Inertia. Debe entregarse solo por el canal de correo |
| P2-04 | **Perfiles «Activos» que no pueden iniciar sesión** | Auditor y Consulta figuran como cuentas activas y el login los rechaza (`Role::SIN_ACCESO`). La pantalla de Usuarios lo marca con `sin_acceso`, pero el modelo de datos sigue diciendo `activo = 1`. Confirmado en prueba: ambos perfiles reciben error de credenciales |
| P2-05 | **La auditoría del postulante no tiene autor** | `AuditoriaService::registrar()` guarda `usuario_id = null` para el guard `postulante`. El informe del 2 de agosto lo reportó como BD-09 y declaró «los diez hallazgos corregidos»; la corrección no se aplicó — sigue en el código con un comentario que la justifica. Falta una columna `actor_tipo`/`actor_id` para cerrarlo de verdad |
| P2-06 | **Dependencia con vulnerabilidad alta** | `postcss < 8.5.22` — GHSA-r28c-9q8g-f849 y GHSA-fxqj-rqcc-2cmp (path traversal en el auto-cargado de source maps). `npm audit fix` lo resuelve. El pipeline de CI no ejecuta `npm audit` ni `composer audit` |
| P2-07 | **El CI/CD no despliega** | Los jobs `deploy-qa` y `deploy-prod` de `.github/workflows/ci.yml` solo ejecutan `echo "Desplegando..."`. La documentación presenta el pipeline como operativo. O se implementa, o se documenta como despliegue manual con el RUNBOOK |
| P2-08 | **Restos del módulo de equivalencias** | `GET /equivalencias` y `GET /equivalencias/crear` siguen enrutadas y con pantalla, y el Dashboard sigue ofreciendo el acceso rápido «Mallas Externas» apuntando ahí, sobre un módulo cuya escritura se retiró. Además queda `->where('id', '<', 0)` como «Hack» y comentarios en inglés dentro de `ConvalidacionController::index` |

---

## 6. Verificado sin hallazgo

| # | Verificación | Resultado |
|---|---|---|
| 1 | Flujo completo Asesor → Ejecutivo → Coordinador → Decano → Portal | Correcto de extremo a extremo |
| 2 | Suite existente (62 pruebas, 208 aserciones) | Verde |
| 3 | Estilo de código (`pint --test`) | Pasa |
| 4 | Regla 1 a 1: un curso USIL no se repite en una simulación | Validado en `persistirSimulacion` |
| 5 | Un curso destino debe pertenecer al plan de estudios de la carrera | Validado |
| 6 | No se emite memorándum sin cursos convalidados | Guarda activa (corrección previa) |
| 7 | No se elimina una simulación con convalidación vigente | Guarda activa (corrección previa) |
| 8 | Número de memorándum único a nivel de base de datos | Índice `uq_convalidaciones_memorandum` |
| 9 | API keys cifradas en reposo con `APP_KEY` | `Configuracion::SECRETOS` + `Crypt` |
| 10 | Cuentas demo bloqueadas en producción | Guarda en `DemoUsersSeeder` + migración `..._000002` |
| 11 | Alcance por carrera en el módulo de Mallas | `MallaController::autorizarCarrera` en los 12 métodos |
| 12 | Token de recuperación: hash SHA-256, expiración 60 min, `hash_equals` | Correcto |
| 13 | Secretos fuera del repositorio (`.env` en `.gitignore`) | Correcto |

---

## 7. Riesgos que permanecen abiertos del informe anterior

Estos dos puntos del informe del 2 de agosto siguen sin resolverse y no son corregibles solo con
código:

1. **§5 de los Lineamientos Técnicos — API REST.** Sigue sin existir `routes/api.php` ni
   `laravel/sanctum`. Requiere construcción o excepción formal documentada.
2. **Envío del récord académico al proveedor de IA.** `IAConvalidacionService::extraerCursos()`
   envía el PDF o la imagen del postulante a Google Gemini, y el prompt pide extraer su nombre,
   código y carrera. La seudonimización solo se aplica a la rama de texto plano/CSV, no a PDF ni a
   imagen —que son el caso real de uso—. Requiere acuerdo de tratamiento de datos con el proveedor
   y aviso de privacidad al postulante. **P0-03 agrava este punto:** hoy ese mismo documento es
   además descargable por cualquier evaluador del sistema.

---

## 8. Plan de corrección propuesto

**Antes del pase (bloqueantes):**

1. P0-01 — mover el caché de permisos fuera de `$attributes`. *(30 minutos)*
2. P0-02 — invocar `AlcanceService::alcanzaCarrera()` en los 8 métodos de `SimulacionController`,
   siguiendo el patrón que ya usa `MallaController::autorizarCarrera()`. *(medio día)*
3. P0-03 — validar en `verDocumento()` que el postulante del documento está en el alcance del
   usuario. *(1 hora)*
4. P0-04 — aplicar `AlcanceService::carrerasVisibles()` en las cuatro consultas de
   `ReporteController`. *(medio día)*
5. P0-05 — agrupar los `orWhere` de `ConvalidacionController::index` dentro de un
   `->where(fn ($w) => ...)`. *(30 minutos)*
6. P1-01 — invocar `autorizarPropiedad()` en `preconvalidacion()`, `preconvalidacionPdf()`,
   `preconvalidacionExcel()` y `estado()`. *(1 hora)*
7. P1-02 — verificar el alcance por facultad en `confirmar()`, `anular()` y `memorandumPdf()`.
   *(2 horas)*
8. P1-03 — `throttle:5,1` en ambas rutas de login; contador de intentos en el portal. *(2 horas)*
9. P1-04 — separar la generación del PDF (POST, cambia estado) de su descarga (GET, no cambia
   nada). *(2 horas)*

**Recomendación transversal:** el patrón repetido en P0-02, P0-04, P1-01 y P1-02 es siempre el
mismo —el alcance se aplica al listar y no al abrir—. Conviene resolverlo con un
`Gate`/`Policy` sobre `Simulacion`, `Convalidacion` y `Postulante` en lugar de con verificaciones
sueltas método a método, para que un método nuevo quede protegido por omisión y no por disciplina.

**Antes del pase (higiene):** P2-01, P2-02, P2-06.
**Puede ir después:** P2-03, P2-04, P2-05, P2-07, P2-08.

---

## 9. Cómo reproducir esta auditoría

```bash
php artisan test --filter=AuditoriaE2ETest
```

Las 19 pruebas de `tests/Feature/AuditoriaE2ETest.php` afirman el **comportamiento correcto**.
Antes de corregir: 16 fallaban, 3 pasaban. Después: **19 en verde**. Quedan como suite de
regresión permanente, de modo que reintroducir cualquiera de estos defectos vuelva a poner el
pipeline en rojo.

Cada sonda de alcance lleva además un **control positivo** (el usuario sí ve lo que le
corresponde), para que la prueba no pueda pasar por haber roto la función en lugar de filtrarla.

---

## 10. Correcciones aplicadas

Todas verificadas con la batería completa (**81 pruebas, 275 aserciones, 0 fallos**), `pint --test`
en verde, `npm audit` sin vulnerabilidades, y prueba manual en navegador de los flujos afectados.

### Bloqueantes

| # | Corrección | Archivos |
|---|---|---|
| P0-01 | El caché de permisos pasa de `$attributes` a una propiedad del objeto (`$permisosCache`). El `UPDATE` ya no arrastra una columna inexistente | `app/Models/User.php` |
| P0-02 | Alcance verificado en los 8 puntos de entrada de Simulaciones (`show`, `crear`, `editar`, `update`, `destroy`, `toggleDetalle`, `generarPdf`, `exportarExcel`) y también sobre `carrera_usil_id` recibido en el cuerpo de `store`/`update` | `SimulacionController.php`, `AlcanceService.php` |
| P0-03 | `verDocumento()` comprueba que el postulante dueño del récord esté en el alcance de quien lo pide | `SimulacionController.php` |
| P0-04 | Alcance aplicado a las cuatro consultas de Reportes y a los desplegables de filtro | `ReporteController.php` |
| P0-05 | Los `orWhere` de los dos buscadores quedan agrupados en un `where(fn …)`; se elimina el `where('id','<',0)` marcado como «Hack» | `ConvalidacionController.php` |

### Altas

| # | Corrección | Archivos |
|---|---|---|
| P1-01 | `autorizarPropiedad()` se invoca en `preconvalidacion()`, `preconvalidacionPdf()`, `preconvalidacionExcel()` y `estado()`; además ahora comprueba propiedad **y** alcance | `PostulanteController.php` |
| P1-02 | `confirmar()`, `anular()` y `memorandumPdf()` verifican el alcance sobre la carrera de la simulación | `ConvalidacionController.php` |
| P1-03 | `throttle:10,1` en `POST /login` y `POST /portal/login` | `routes/web.php` |
| P1-04 | La descarga del PDF es una lectura pura: no cambia `estado`, no escribe en almacenamiento y no registra un evento `crear` por cada consulta | `SimulacionController.php` |

### Medias

| # | Corrección | Archivos |
|---|---|---|
| P2-01 | Un único `COUNT` agrupado para toda la página en lugar de uno por fila | `SimulacionController.php` |
| P2-02 | Los tres submódulos de administración pasan a tener cada uno su propio grupo de permiso | `routes/web.php` |
| P2-03 | Las contraseñas temporales se entregan por correo (`AccesoSistemaMail` para el personal, `AccesoPortalMail` ya existente para el postulante). En pantalla solo aparecen en entorno `local` | `UsuarioController.php`, `PostulanteController.php`, `app/Mail/AccesoSistemaMail.php` |
| P2-04 | Ya cubierto: la pantalla de Usuarios marca los perfiles `sin_acceso`, y `LoginTest` fija la regla en el modelo | — |
| P2-05 | Nuevo par `(actor_tipo, actor_id)` en `auditoria_log`, poblado según el guard activo; la migración reconstruye la traza histórica | `2026_08_03_000001_add_actor_to_auditoria_log.php`, `AuditoriaService.php` |
| P2-06 | `postcss` actualizado; CI incorpora `composer audit` y `npm audit` | `package-lock.json`, `.github/workflows/ci.yml` |
| P2-07 | Se retiran los jobs de despliegue simulados y se documenta que el despliegue es manual según el runbook | `.github/workflows/ci.yml` |
| P2-08 | Comentario de rutas corregido: el prefijo `equivalencias` es hoy el registro de mallas externas, no un módulo muerto | `routes/web.php` |

### Cambios en pruebas existentes

`GateSimulacionTest` empezó a fallar al aplicar P0-02. No era una regresión de la corrección sino
un **fixture incompleto**: el postulante no tenía carrera destino y el coordinador no tenía
carreras asignadas, así que el 403 de alcance pasaba a tapar el gate de revisión que la prueba
mide. Se completó el fixture con una carrera real dentro del alcance del coordinador, en lugar de
relajar la regla de seguridad. La prueba ahora verifica exactamente lo que declara.

### Verificación manual en navegador

Login como Coordinador, Decano y Superusuario; los 9 módulos responden 200; el listado de
Simulaciones muestra los conteos correctos; Reportes y Convalidaciones cargan con datos reales; la
búsqueda por número de memorándum devuelve la resolución correcta; el PDF y el memorándum se
descargan (200) y **el estado de la simulación permanece `generada`** tras la descarga; el cambio
de contraseña en primer acceso de un Coordinador completa correctamente.

> Un defecto se detectó **solo** en esta verificación manual: el `pluck()` del conteo agrupado
> usaba una expresión cruda como clave y reventaba en producción, pero no en las pruebas porque el
> escenario no tenía simulaciones que contar. Se corrigió y se reforzó la prueba para que el
> listado lleve simulaciones reales.
