# Auditoría de entrega — Sistema de Convalidaciones USIL

**Fecha:** 10 de agosto de 2026
**Rama auditada:** `feat/cumplimiento-proceso-traslado-externo` (último commit `76aca1b`) **más el árbol de trabajo sin confirmar**
**Alcance:** revisión completa del repositorio — código de aplicación, base de datos, seguridad, control de acceso, dependencias, infraestructura de despliegue, pruebas y documentación.
**Objetivo:** dejar el proyecto en condiciones de ser entregado al área de TI de USIL para su pase a producción.

---

## 0. Resumen ejecutivo

El sistema tiene una base sólida: el modelo RBAC de dos ejes (permiso + alcance por carrera/facultad) está bien implementado, la trazabilidad de auditoría es completa, las contraseñas y las claves de IA se manejan correctamente, y las migraciones levantan una base de datos limpia sin errores. **Ese trabajo es real y no está en discusión.**

El problema es el estado en que se encuentra la entrega **hoy**. Hay trabajo a medio hacer sin confirmar en Git que rompe partes del sistema que antes funcionaban, y hay una serie de problemas de empaquetado y de infraestructura que impiden que TI pueda desplegarlo tal como está.

**Evidencia medida, no estimada:**

| Verificación | Último commit (`76aca1b`) | Árbol de trabajo actual |
|---|---|---|
| Suite de pruebas (`php artisan test`) | **178 pasan, 0 fallan** | **158 pasan, 21 fallan** |
| Estilo de código (`pint --test`) | pasa | **falla** (2 archivos) |
| Auditoría de dependencias (`composer audit`) | **falla — 24 advisories** | **falla — 24 advisories** |
| Compilación del frontend (`npm run build`) | pasa | pasa en local, **falla en un clon limpio** |
| `migrate` + `db:seed` en BD vacía | pasa | pasa |
| `npm audit --omit=dev` | 0 vulnerabilidades | 0 vulnerabilidades |

**Veredicto:** **no entregar todavía.** Hay 7 puntos bloqueantes. Ninguno es de arquitectura; todos son cerrables. La sección 8 propone el orden de trabajo.

---

## 1. Bloqueantes — impiden la entrega

### B-01 · Un componente del frontend no está versionado: el proyecto no compila en un clon limpio

`resources/js/Components/VolverA.vue` **existe en el disco pero no está en Git** (aparece como `??` en `git status`). Lo importan **25 páginas Vue**: todos los formularios de Estructura, Mallas, Instituciones, Postulantes, Simulaciones, Usuarios y Mapeo de Mallas.

TI hará `git clone` → `npm ci && npm run build`, y la compilación abortará en el primer archivo que lo importe. La app no arranca: `@vite` en `resources/views/app.blade.php` aborta la petición si falta `public/build`.

Aquí compila solo porque el archivo está presente en esta máquina. **Es el fallo más probable de la entrega y el más fácil de pasar por alto.**

**Corrección:** `git add resources/js/Components/VolverA.vue` y confirmar.

---

### B-02 · La pantalla de Configuración lanza un error fatal

`ConfiguracionController` invoca dos símbolos que fueron eliminados de `ConvalidacionController` en el trabajo sin confirmar:

- [`ConfiguracionController.php:45`](../app/Http/Controllers/ConfiguracionController.php#L45) → `ConvalidacionController::responsablesMemo()`
- [`ConfiguracionController.php:53`](../app/Http/Controllers/ConfiguracionController.php#L53) y `:58` → `ConvalidacionController::MEMO_DEFAULTS`

Comprobado en ejecución:

```
FATAL /configuracion         -> Error: Undefined constant App\Http\Controllers\ConvalidacionController::MEMO_DEFAULTS
FATAL responsablesMemo       -> Error: Call to undefined method App\Http\Controllers\ConvalidacionController::responsablesMemo()
```

Consecuencia directa: `GET /configuracion` y `PUT /configuracion/memorandum` devuelven **HTTP 500**. Es la única pantalla donde se cargan las API keys de IA y los datos del memorándum — el Superusuario queda sin acceso a la configuración del sistema.

`tests/Feature/DocumentosEmitidosTest.php:85` referencia la misma constante y también falla.

---

### B-03 · Trabajo a medio hacer: se retiraron dos módulos completos sin cerrar el ciclo

El árbol de trabajo elimina, sin confirmar en Git:

- **Módulo Convalidación** (CU-06 / RF-30, RF-31, RF-33, RF-46, RF-47): `confirmar()`, `anular()`, `memorandumPdf()` y sus rutas.
- **Módulo Reportes** (CU-08 / RF-36, RF-37): las rutas `reportes.index` y `reportes.exportar`, y los permisos `reportes.ver` / `reportes.exportar`.

Efectos comprobados:

1. **21 pruebas fallan** (`AuditoriaE2ETest`, `IntegridadConvalidacionTest`, `SimulacionTest`, `RbacTest`, `DocumentosEmitidosTest`). En `76aca1b` las 178 pasaban.
2. **Ya no se puede confirmar ninguna convalidación.** No hay ruta que cree una fila en `convalidaciones`. El memorándum oficial —el entregable final del proceso— no puede emitirse.
3. **El seguimiento del postulante tiene una etapa inalcanzable.** [`SeguimientoTimeline.php:50`](../app/Support/SeguimientoTimeline.php#L50) mantiene la fase «Convalidación confirmada», que quedará en «Pendiente de confirmación» para siempre.
4. **Permisos huérfanos:** `convalidacion.confirmar` y `convalidacion.anular` siguen en el catálogo y asignados al Decano, pero ninguna ruta los usa.
5. **Código muerto:** `ReporteController`, `ConvalidacionesExport`, `resources/js/Pages/Reportes/Index.vue`, `resources/views/pdf/memorandum.blade.php` y las migraciones `2026_08_05_000001` / `2026_08_05_000002` quedan sin consumidores.

**Decisión requerida antes de entregar.** Solo hay dos salidas válidas y hay que elegir una explícitamente:

- **(a) Revertir** el trabajo sin confirmar y entregar `76aca1b`, que está verde. Es lo más rápido y seguro.
- **(b) Terminar la retirada**: si Convalidación y Reportes salen del alcance, hay que borrar también el código muerto, los permisos, las pruebas y las referencias, actualizar `SeguimientoTimeline`, y documentar ante TI que el proceso ya no emite memorándum.

Lo que **no** es una opción es entregar el estado intermedio actual.

---

### B-04 · La exportación a Excel depende de un archivo que no viaja en la entrega

[`SimulacionController::exportarExcel()`](../app/Http/Controllers/SimulacionController.php#L817) fue reescrito para cargar una plantilla:

```php
$templatePath = storage_path('app/plantillas/formato_simulacion.xltx');
if (!file_exists($templatePath)) {
    abort(500, 'La plantilla de Excel no se encuentra en el servidor.');
}
```

El archivo existe en esta máquina pero **está excluido por `storage/app/.gitignore` (`*`)** y no está en Git. En cualquier despliegue nuevo, **toda descarga de preconvalidación en Excel devuelve 500**. Afecta a tres rutas: `simulaciones.excel`, `convalidaciones.preconvalidacion.excel` y `postulantes.preconvalidacion.excel`.

Problemas adicionales del mismo método:

- **Pérdida funcional.** Solo escribe nombre USIL y nombre de origen en las columnas A y B. La implementación anterior (`PreconvalidacionExport` con `PreconvalidacionSheet`, `NoConvalidadosSheet` y `FormatoErpSheet`) producía créditos, notas, los cursos no convalidados con su motivo y la hoja de formato ERP. Todo eso desaparece del archivo que recibe el usuario.
- `PreconvalidacionExport` queda importado pero sin uso → es uno de los dos archivos que hacen fallar Pint.
- Escribe en `storage/app/temp/` con el nombre del postulante. Dos descargas simultáneas del mismo expediente pisan el mismo archivo. `deleteFileAfterSend(true)` no limpia si la escritura falla antes.

**Corrección:** versionar la plantilla (excepción en `.gitignore` o moverla a `resources/plantillas/`), o volver a `PreconvalidacionExport`. Y decidir conscientemente si se acepta perder el contenido del Excel.

---

### B-05 · La ruta de despliegue documentada en el RUNBOOK no funciona

`deploy/RUNBOOK.md` §3 y §4 indican desplegar con `docker-compose.prod.yml`. Ese stack tiene cuatro defectos que lo impiden:

**1. Los cachés se generan en build, sin variables de entorno.**
[`docker/php/Dockerfile`](../docker/php/Dockerfile) ejecuta `config:cache`, `route:cache` y `view:cache` durante `docker build`, cuando `.env` todavía no existe (llega en runtime vía `env_file`). El contenedor arranca con una configuración congelada sin `APP_KEY` ni credenciales de base de datos.

> Este error ya se identificó y se resolvió en el `Dockerfile` de Railway —sus comentarios lo explican en detalle— pero la corrección nunca se trasladó al stack de producción propio.

**2. No se compila el frontend.** `docker/php/Dockerfile` no instala Node ni ejecuta `npm run build`. `public/build` está en `.gitignore`, así que la imagen no lo tiene. `@vite` aborta cada petición.

**3. Nginx no tiene acceso a los archivos estáticos.** El servicio `nginx` apunta a `root /var/www/html/public` pero **no monta ningún volumen con el código** — solo el archivo de configuración. El código vive dentro de la imagen del servicio `app`. Resultado: el PHP se resuelve por FastCGI, pero **todo activo estático (JS, CSS, favicon) devuelve 404**.

**4. El servicio `scheduler` no tiene `build:`**, solo `image:`. Depende de que el tag exista previamente y no hay nada que agendar (`routes/console.php` está vacío salvo el comando `inspire` de ejemplo).

**Corrección:** trasladar el patrón del `Dockerfile` de Railway (mover los cachés a un entrypoint, añadir Node y `npm run build`), montar un volumen compartido de código para Nginx, y eliminar el servicio `scheduler` o darle contenido.

---

### B-06 · 24 advisories de seguridad en dependencias PHP

`composer audit` reporta **24 advisories que afectan a 5 paquetes**. `composer.lock` está congelado desde el 9 de julio de 2026.

| Paquete | Severidad | Relevancia para este sistema |
|---|---|---|
| `phpoffice/phpspreadsheet` | **alta ×3** (CVE-2026-59931/59932/59933) | **Directamente alcanzable.** El sistema acepta `.xlsx`/`.xls` de usuarios en carga masiva de mallas y en mallas externas. Agotamiento de memoria y SSRF. |
| `laravel/framework` v11.54.0 | **alta** (CVE-2026-48019) | Inyección CRLF en la regla `email` por defecto. El sistema valida correos en login, recuperación y alta de postulantes. |
| `guzzlehttp/guzzle` | **alta** + 4 medias | Bypass de verificación de host; se usa en todas las llamadas a Gemini/OpenAI. |
| `dompdf/dompdf` | 4 medias + 2 bajas | Lectura de archivos locales vía SVG. Se usa para generar los PDF de preconvalidación. |
| `league/commonmark` | 4 altas + 2 medias | DoS por parsing. Dependencia indirecta. |

Agravante: [`composer.json`](../composer.json) contiene

```json
"policy": { "advisories": { "block": false } }
```

Esto **desactiva a propósito el bloqueo por advisories** de Composer. TI debería saber que existe y por qué.

**Corrección:** `composer update` sobre las cinco dependencias, volver a correr la suite, y retirar la política `block: false` (o documentar formalmente por qué se mantiene).

---

### B-07 · El pipeline de CI está en rojo

`.github/workflows/ci.yml` falla en dos pasos con el código actual:

- **`./vendor/bin/pint --test`** → falla en `app/Http/Controllers/SimulacionController.php` (8 reglas: `no_unused_imports`, `not_operator_with_successor_space`, `no_whitespace_in_blank_line`, `concat_space`, `braces_position`, entre otras) y en `app/Imports/MallaCursosImport.php` (`phpdoc_single_line_var_spacing`).
- **`composer audit`** → falla por B-06.

Entregar a TI un repositorio cuyo propio CI no pasa es un problema de confianza además de técnico. `./vendor/bin/pint` sin `--test` corrige lo primero automáticamente.

---

## 2. Hallazgos de seguridad (alta prioridad)

### A-01 · IDOR: cualquier evaluador puede leer el récord académico de cualquier postulante

`POST /simulaciones/extraer-ia` → [`SimulacionController::extraerIA()`](../app/Http/Controllers/SimulacionController.php#L303).

El método recibe `documento_id`, hace `PostulanteDocumento::with('postulante')->findOrFail(...)`, **comprueba el consentimiento de datos pero nunca comprueba el alcance del usuario.** Un Coordinador cuyo alcance está restringido a una sola carrera puede iterar identificadores y extraer el contenido íntegro del récord académico de cualquier postulante del sistema: nombre, documento de identidad y notas. Además, ese contenido **se envía al proveedor externo de IA**.

El contraste está en el mismo archivo: [`verDocumento()`](../app/Http/Controllers/SimulacionController.php#L253) sí llama a `AlcanceService::autorizarPostulante()` antes de servir el archivo. La comprobación simplemente falta en la rama de IA.

**Corrección (una línea, en el punto donde ya se resolvió el dueño):**

```php
AlcanceService::autorizarPostulante($request->user(), $dueno);
```

Menor, del mismo tipo: `sugerirSimilitud()` y `sugerirIA()` validan que `carrera_usil_id` exista pero no que esté dentro del alcance; filtran el pool de cursos de cualquier carrera.

---

### A-02 · Cada re-despliegue reimpone la contraseña de administrador publicada en el repositorio

[`AdminUserSeeder`](../database/seeders/AdminUserSeeder.php) hace `updateOrCreate` sobre `admin@usil.edu.pe` con la contraseña literal `Admin#2026`.

`deploy/RUNBOOK.md` §4 indica ejecutar `php artisan db:seed --force` **en cada actualización**. Como es `updateOrCreate` y no `firstOrCreate`, cada despliegue **restablece la contraseña del Superusuario a un valor que está escrito en el repositorio** y vuelve a marcar `primer_acceso = true`.

**Corrección:** cambiar a `firstOrCreate`, o leer la contraseña inicial de una variable de entorno, o retirar `db:seed` del procedimiento de actualización dejándolo solo en la instalación inicial. Cualquiera de las tres sirve; hay que hacer una.

> El control equivalente para las cuentas demo **sí** está bien resuelto: `DemoUsersSeeder` se omite cuando `APP_ENV=production`. Verificado en ejecución.

---

### A-03 · Fuga de detalles internos en errores

[`MallaExternaController::store()`](../app/Http/Controllers/MallaExternaController.php) devuelve al cliente el mensaje crudo de la excepción:

```php
return response()->json(['message' => 'Error al guardar la malla: '.$e->getMessage()], 500);
```

Esto expone errores SQL, rutas del sistema de archivos y nombres de columna **incluso con `APP_DEBUG=false`**, que es precisamente lo que esa bandera debería impedir. Igual en `extraerIA()` del mismo controlador.

**Corrección:** `Log::error($e)` y devolver un mensaje genérico.

---

### A-04 · Los PDF de mallas externas se guardan en un disco público sin autenticación

`MallaExternaController::store()` usa `->store('mallas_externas', 'public')`, lo que los deja bajo `public/storage/` y **accesibles por URL sin sesión**.

Es incoherente con el resto del sistema: los documentos de postulantes usan el disco privado y se sirven por `documentos.ver` con control de alcance. Las mallas oficiales tienen menos sensibilidad que un récord académico, pero la decisión debe ser explícita, no un efecto colateral de haber pasado `'public'`.

---

### A-05 · La carga masiva de mallas no aplica el control de alcance por carrera

[`MallaImportController`](../app/Http/Controllers/MallaImportController.php) — ninguno de sus métodos llama a `AlcanceService`. `MallaController`, en cambio, invoca `autorizarCarrera()` en sus 15 métodos.

Consecuencias para un Coordinador o Director (alcance = carrera):

- `previsualizar()`, `guardarRevisada()` y `store()` aceptan `carrera_id` del cuerpo de la petición: puede **crear e importar la malla de cualquier carrera de la universidad**.
- `guardarRevisada()` con `activa = true` ejecuta `MallaCurricular::where('carrera_id', ...)->update(['activa' => false])`: puede **desactivar el plan de estudios vigente de una carrera ajena**.
- `estado()` y `progreso()` no comprueban propiedad de la `CargaMasiva`: cualquiera consulta el progreso y los errores de las importaciones de otros.

---

### A-06 · Secretos y datos personales dentro de la carpeta que se va a entregar

Están excluidos de Git, pero **si la entrega se hace comprimiendo la carpeta viajan con ella**:

| Archivo | Contenido |
|---|---|
| `.env` | `APP_KEY`, contraseña de MySQL, **`GEMINI_API_KEY` y `OPENAI_API_KEY` reales** |
| `backups/respaldo_pre_marcha_blanca_20260804_123545.sql` | Volcado con datos reales: `postulantes`, `postulante_documentos`, `usuarios` (hashes), `auditoria_log`, `convalidaciones` |
| `.phpunit.result.cache`, `.claude/`, `.cursor/` | Ruido de herramientas de desarrollo |
| `docs/cursos_malla_externa_SENATI.xlsx` | Archivo de prueba sin versionar |

**Corrección:** entregar mediante `git archive` o un clon limpio, nunca comprimiendo el directorio de trabajo. Si ya se compartió una copia, **rotar las dos API keys**.

---

### A-07 · `public/hot` presente: rompe la aplicación si se entrega la carpeta

Existe `public/hot`, el marcador que deja el servidor de desarrollo de Vite. Mientras está presente, `@vite` **ignora `public/build` y apunta al servidor de desarrollo local**. En producción eso deja la aplicación sin JavaScript ni CSS.

Peor: `.dockerignore` no lo excluye, así que un `docker build` desde este directorio lo copia dentro de la imagen y `npm run build` no lo borra. **El fallo sobrevive a la construcción de la imagen.**

**Corrección:** borrar `public/hot` y añadirlo a `.dockerignore` junto con `public/build`.

---

### A-08 · El portal publica preconvalidaciones sin confirmar (regresión de regla de negocio)

En el árbol de trabajo, `Portal/PreconvalidacionController::ver()` pasó de exigir convalidación confirmada a solo rechazar las anuladas, y `SeguimientoController` publica ahora `pdf_url` para **todas** las simulaciones.

El comentario que se eliminó explicaba el motivo original: *«mientras Admisión no confirme la convalidación el resultado aún puede cambiar y el postulante no debe verlo»*.

El cambio es coherente con B-03 (sin `confirmar()` el estado «confirmada» es inalcanzable, así que el portal no mostraría nunca nada), pero el efecto es que **el postulante ve resultados preliminares que todavía pueden cambiar**. Es una decisión de negocio, no técnica: debe ser aprobada por el área usuaria y quedar por escrito, o revertirse.

---

## 3. Hallazgos funcionales y de robustez (prioridad media)

### M-01 · Condición de carrera en la carga masiva con Redis

`MallaImportController::store()` despacha `ImportarMallaExcel::dispatch()` **dentro de** `DB::transaction()`, y `config/queue.php` tiene `'after_commit' => false` en las cuatro conexiones.

Con `QUEUE_CONNECTION=redis` (el valor de producción) el worker puede tomar el trabajo **antes de que la transacción confirme** → `CargaMasiva::findOrFail()` lanza excepción y la carga queda en «fallido» sin causa aparente. No se reproduce en desarrollo porque el `.env` local usa `QUEUE_CONNECTION=sync`.

**Corrección:** `ImportarMallaExcel::dispatch(...)->afterCommit()`, o `'after_commit' => true` en la conexión Redis.

---

### M-02 · Documentos duplicados: el contador «N de 5» miente

[`PostulanteController::guardarDocumentos()`](../app/Http/Controllers/PostulanteController.php#L590) siempre hace `create()`. Subir dos veces el mismo tipo genera dos filas y **el archivo anterior queda huérfano en disco para siempre**.

`Portal/SeguimientoController` cuenta `$p->documentos()->count()` — filas, no tipos distintos. Subiendo cinco veces el DNI, el postulante ve **«5 de 5 documentos entregados»** con un solo tipo real presentado.

**Corrección:** `updateOrCreate` por `['postulante_id', 'tipo']` borrando el archivo anterior, y contar `distinct('tipo')` en el portal.

---

### M-03 · Faltan las tablas de infraestructura del framework

No existen migraciones para `jobs`, `failed_jobs`, `job_batches`, `cache`, `cache_locks` ni `password_reset_tokens`.

Los valores por defecto de `config/queue.php` y `config/cache.php` son `database`. Si TI despliega sin Redis —escenario probable en un servidor propio— la carga masiva y el caché fallan con «table not found». El `.env.example` fija `redis`, pero un despliegue que no lo copie íntegro cae en el camino roto.

Además, `config/auth.php` declara `password_reset_tokens`, tabla que no existe. Hoy no molesta porque la recuperación de contraseña usa columnas propias en `usuarios`, pero es una inconsistencia que confundirá a quien la lea.

**Corrección:** `php artisan make:queue-table`, `make:queue-failed-table`, `make:cache-table` y confirmarlas.

---

### M-04 · Una instalación de producción arranca sin catálogos maestros

Ejecutado `migrate` + `db:seed --force` con `APP_ENV=production` sobre una base vacía, el resultado es:

```
usuarios: 1     roles: 8       permisos: 21    rol_permiso: 62
facultades: 9   carreras: 40   modalidades: 3  unidades_negocio: 2
cursos_no_convalidables: 43

instituciones_externas: 0   ← el proceso no puede empezar
carreras_externas: 0
mallas_curriculares: 0      ← sin plan de estudios no hay simulación posible
cursos_usil: 0
planes_estudio: 0
```

Existen seeders para esos catálogos (`SuneduSeeder` con las universidades licenciadas, `CargarPlanEstudiosJsonSeeder` con `database/data/plan_estudios.json`, `CarrerasExternasSeeder`, `CursosNoConvalidablesSeeder`) pero **no están en `DatabaseSeeder` ni mencionados en el RUNBOOK**. TI desplegará y nada funcionará, sin ningún mensaje que lo explique.

**Corrección:** cablearlos en `DatabaseSeeder` o documentar en el RUNBOOK los `db:seed --class=...` obligatorios de la instalación inicial.

---

### M-05 · Permisos declarados que ninguna ruta aplica

Ocho de los 21 permisos del catálogo no se comprueban en ningún sitio:

`dashboard.ver`, `solicitudes.asignar`, `evaluacion.aprobar`, `evaluacion.observar`, `evaluacion.reasignar`, `auditoria.ver`, `convalidacion.confirmar`, `convalidacion.anular`.

`auditoria.ver` es el más llamativo: **existe el permiso y la tabla `auditoria_log` se llena correctamente, pero no hay pantalla ni ruta para consultarla.** El Auditor tiene el permiso y no puede auditar nada.

Esto importa para la entrega: la matriz de permisos que TI reciba dirá cosas que el sistema no hace cumplir.

**Corrección:** retirar del catálogo lo que no se usa, o implementar la pantalla que falta (como mínimo, la de auditoría).

---

### M-06 · Colisión en el código de postulante bajo concurrencia

```php
$siguiente = (Postulante::withTrashed()->max('id') ?? 0) + 1;
$datos['codigo'] = 'POST-'.now()->year.'-'.str_pad((string) $siguiente, 5, '0', STR_PAD_LEFT);
```

Dos asesores registrando a la vez obtienen el mismo `max(id)` y por tanto el mismo código. Si `codigo` tiene índice único uno de los dos recibe un error inexplicable; si no lo tiene, quedan dos postulantes con el mismo identificador. Lo mismo aplica al documento temporal `TMP-AAAA-NNNNN`.

**Corrección:** derivar el código del `id` ya asignado, dentro de una transacción, después del `INSERT`.

---

### M-07 · Otros puntos de robustez

- **`ImportarMallaExcel`** ejecuta un `UPDATE` sobre `cargas_masivas` **por cada fila** procesada. Una malla de 2.000 cursos genera 2.000 escrituras innecesarias. Actualizar cada 50 filas basta.
- **El Excel subido nunca se borra** tras la importación (`storage/app/cargas/`). Crece sin límite.
- **Reintentar una importación duplica los cursos**: cada fila es su propia transacción y no hay clave única `(malla, código)`.
- **`MallaExternaController::store()` usa `substr()`** donde `previsualizarExcel()` usa `mb_substr()`. Un nombre de curso con tildes cortado en el byte 200 se guarda con UTF-8 corrupto.
- **El worker está fijado a Redis por comando** (`queue:work redis` en `docker/railway/supervisord.conf` y en `docker-compose.prod.yml`), ignorando `QUEUE_CONNECTION`. Si TI decide no usar Redis, el worker no arranca y la carga masiva muere en silencio.
- **`persistirSimulacion()` no comprueba** que el postulante haya solicitado efectivamente la carrera destino. Valida el alcance de la carrera, pero un evaluador puede generar una simulación de cualquier postulante hacia su propia carrera aunque no la haya pedido.
- **El procedimiento de actualización no ejecuta `queue:restart`**: los workers conservan el código anterior en memoria.

---

## 4. Código muerto y arrastre

| Elemento | Situación |
|---|---|
| `config/auth_provider_snippet.php` | Fragmento de documentación dentro de `config/`. **Laravel lo carga como configuración real.** |
| `config/middleware_snippet.php` | Ídem. Solo contiene un comentario; el archivo no devuelve nada. |
| `config/services_openai_snippet.php` | Ídem. Su contenido ya está en `config/services.php`. |
| `app/Policies/CarreraPolicy.php` | Registrada en `AppServiceProvider` pero **nunca invocada**. El control real lo hace `AlcanceService`. |
| `app/Http/Controllers/ReporteController.php` | Sin rutas tras B-03. |
| `app/Exports/ConvalidacionesExport.php` | Solo lo usa `ReporteController`. |
| `app/Exports/PreconvalidacionExport.php` + `Exports/Sheets/*` | Sin uso tras la reescritura de B-04. |
| `resources/js/Pages/Reportes/Index.vue` | Sin ruta. |
| `resources/views/pdf/memorandum.blade.php` | Sin emisor. |
| `docker/Dockerfile` | Imagen base sin `CMD` ni build; solo la usa el compose de desarrollo. |
| `routes/console.php` | Únicamente el comando `inspire` de ejemplo de Laravel. |

---

## 5. Documentación

- **`docs/expediente-pase-produccion/INDICE.md` declara pendientes** los entregables A1 (documento funcional), A2 (documento técnico), A4 (manual de usuario por rol), A6 (RUP consolidado), el Documento 2 (Expediente Técnico de Pase a Producción) y cuatro diagramas (D-03 AS-IS, D-04 TO-BE, D-06 casos de uso, D-08 secuencia). Son exactamente los que TI va a pedir en el comité.
- **`DESPLIEGUE.md` describe una estructura que ya no coincide** con el repositorio y no menciona el despliegue en Railway, que sí existe y está documentado en `deploy/RAILWAY.md`.
- **El RUNBOOK no advierte** de que hay que sembrar los catálogos maestros (M-04) ni del riesgo de `db:seed` en actualizaciones (A-02).
- Los cuatro `[campos entre corchetes]` del Documento 1 (comité receptor, fecha de sesión, fecha objetivo del pase, responsable de indicadores) siguen sin completar.

---

## 6. Observaciones menores

- **`resources/views/app.blade.php` carga tipografías desde Google Fonts.** El servidor de USIL necesitará salida a internet; si no la tiene, la aplicación funciona pero se ve con tipografías de sistema. Conviene alojarlas localmente.
- **Sin cabeceras de seguridad en la vía Apache/Railway.** `X-Frame-Options` y `X-Content-Type-Options` solo están en `docker/nginx/app.conf`. No hay CSP en ninguna vía.
- **`storage/certs/cacert.pem` versionado.** Es un bundle de CA que caduca; hay que planificar su actualización o preferir el del sistema.
- **CI usa Node 18 y el `Dockerfile` Node 20.** Conviene alinearlos.
- **Bundle de 755 kB sin fragmentar.** Vite lo advierte en cada build. No es urgente, pero afecta al primer arranque.
- **`auditoria_log` crece sin purga.** El RUNBOOK ya lo menciona; conviene una política concreta de retención.

---

## 7. Lo que se verificó y está correcto

Para que TI tenga el balance completo, esto se auditó y **no** requiere corrección:

- **Migraciones.** Las 56 migraciones aplican limpias sobre MySQL 8 vacío, en orden y sin errores. Verificado en ejecución.
- **RBAC de dos ejes.** Permiso + alcance (global / carrera / facultad) correctamente aplicado en `SimulacionController`, `MapeoMallasController`, `PostulanteController` y `MallaController`. El filtrado del listado y la comprobación por registro están ambos presentes: la URL directa no evade el filtro. Las excepciones son A-01 y A-05.
- **Rutas.** Cada submódulo exige su propio permiso; el comentario en `routes/web.php` documenta correctamente por qué no comparten grupo. Las rutas literales van antes que las comodín.
- **Autenticación.** bcrypt con 12 rondas, bloqueo tras 5 intentos, `session()->regenerate()` tras el login, token de recuperación almacenado como SHA-256 y comparado con `hash_equals`, respuesta neutra ante correo inexistente, y el rol se valida **después** de la contraseña para no revelar perfiles.
- **Cuentas demo.** `DemoUsersSeeder` se omite en producción y los accesos rápidos del login solo aparecen en `local`. Verificado en ejecución.
- **Claves de IA cifradas en reposo** con `APP_KEY` (`Configuracion::SECRETOS`) y nunca enviadas al frontend.
- **Capa de IA bien construida:** verificación TLS activa, timeouts de 120 s, reintentos solo ante errores transitorios (429/500/502/503/529) y nunca ante 400/401/403, seudonimización del contenido de texto antes de enviarlo, y **puerta de consentimiento de datos personales** (Ley 29733) antes de cualquier extracción.
- **Subidas de archivos** validadas por tipo MIME y tamaño, almacenadas en el disco privado con nombre aleatorio y servidas con control de alcance (`documentos.ver`).
- **Sin inyección SQL** — todo pasa por Eloquent/Query Builder con enlaces de parámetros.
- **Sin XSS** — los 26 usos de `v-html` son exclusivamente las etiquetas de paginación generadas por Laravel.
- **Auditoría completa:** actor (usuario / postulante / sistema), IP real detrás del proxy (`trustProxies`), valores anteriores y nuevos.
- **`npm audit --omit=dev`:** 0 vulnerabilidades.
- **Reglas de negocio bien defendidas en el servidor:** nota mínima vigesimal de 11 no rebajable (Reglamento Art. 15), convalidación 1 a 1, solo cursos del plan destino, motivo obligatorio en los descartes, expediente cerrado tras confirmación, y exigencia documental del Art. 24 con la vía de aprobación provisional explícita.

> **Nota sobre privacidad:** cuando el récord académico es un PDF o una imagen —el caso habitual— se envía **íntegro** al proveedor de IA (Google o OpenAI), sin seudonimizar, porque una expresión regular corrompería el binario. La seudonimización solo se aplica a la rama de texto plano. Está correctamente gobernado por el consentimiento del postulante, pero **es una transferencia internacional de datos personales que el área legal de USIL debe conocer y aprobar por escrito** antes del pase a producción. No es un defecto del código; es una decisión que necesita constancia.

---

## 8. Plan de corrección propuesto

### Antes de entregar — obligatorio

| # | Acción | Esfuerzo |
|---|---|---|
| 1 | Decidir B-03: revertir el trabajo sin confirmar, o terminarlo. **Todo lo demás depende de esta decisión.** | — |
| 2 | `git add resources/js/Components/VolverA.vue` (B-01) | minutos |
| 3 | Reparar `ConfiguracionController` (B-02) — se resuelve solo si se elige revertir | minutos |
| 4 | Versionar `formato_simulacion.xltx` o volver a `PreconvalidacionExport` (B-04) | 1 h |
| 5 | Añadir `AlcanceService::autorizarPostulante()` en `extraerIA` (A-01) | minutos |
| 6 | `AdminUserSeeder` → `firstOrCreate` o contraseña por entorno (A-02) | minutos |
| 7 | `composer update` de los 5 paquetes con advisories + volver a correr la suite (B-06) | 2 h |
| 8 | `./vendor/bin/pint` y dejar el CI en verde (B-07) | minutos |
| 9 | Borrar `public/hot`; añadirlo a `.dockerignore` (A-07) | minutos |
| 10 | Reparar `docker-compose.prod.yml` + `docker/php/Dockerfile` (B-05) y **probar el despliegue de principio a fin** | 4-6 h |
| 11 | Entregar por `git archive`, no comprimiendo la carpeta. Rotar las API keys si ya se compartió (A-06) | minutos |

### Antes del pase a producción — recomendado

12. Alcance por carrera en `MallaImportController` (A-05).
13. Sanear el error de `MallaExternaController::store()` (A-03) y decidir el disco de los PDF (A-04).
14. Migraciones de `jobs` / `failed_jobs` / `cache` (M-03).
15. Cablear los seeders de catálogos y documentarlo en el RUNBOOK (M-04).
16. `->afterCommit()` en el despacho de la carga masiva (M-01).
17. Documentos por tipo y contador correcto (M-02).
18. Limpiar código muerto y los tres `config/*_snippet.php` (sección 4).
19. Resolver los permisos huérfanos: implementar la pantalla de auditoría o retirar `auditoria.ver` (M-05).
20. Cerrar los entregables documentales pendientes del INDICE (sección 5).
21. Obtener la aprobación escrita del área legal sobre la transferencia de datos al proveedor de IA (sección 7).

---

## 9. Cómo se verificó

Todo lo afirmado en este informe se comprobó ejecutando, no leyendo:

```bash
php artisan test                    # 158 pasan / 21 fallan (árbol de trabajo)
                                    # 178 pasan / 0 fallan (76aca1b, en worktree aislado)
./vendor/bin/pint --test            # falla: 2 archivos
composer audit                      # falla: 24 advisories, 5 paquetes
npm audit --omit=dev                # 0 vulnerabilidades
npm run build                       # compila (con VolverA.vue presente en disco)
php artisan migrate --force         # 56 migraciones limpias sobre BD vacía
php artisan db:seed --force         # con APP_ENV=production
```

Los dos errores fatales de `ConfiguracionController` se reprodujeron cargando el contenedor de la aplicación e invocando los símbolos eliminados directamente. La comparación con `76aca1b` se hizo en un `git worktree` aislado con su propio `vendor` y su propio `public/build`, para que el autoloader no apuntara al árbol de trabajo.
