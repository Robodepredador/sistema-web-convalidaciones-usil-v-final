# Plan de implementación para la entrega a TI — v2

**Fecha:** 10 de agosto de 2026
**Documento base:** [Auditoría de entrega](Auditoria-Entrega-TI-2026-08-10.md)
**Estado:** plan operativo con las respuestas validadas incorporadas. Nada ejecutado todavía.

Sustituye a la v1 (cuestionario). Las respuestas ya están incorporadas; lo que sigue es el trabajo que se deriva de ellas.

---

# 1. Análisis de las respuestas

## 1.1 · Lo que queda cerrado

| Tema | Decisión | Efecto en el plan |
|---|---|---|
| Servidor web | **Apache 2.4+** sobre Linux | `public/.htaccess` sirve tal cual. Se escribe `VirtualHost`, no configuración de Nginx |
| Composer en servidor | **No** | `vendor/` viaja instalado sin dependencias de desarrollo en el paquete |
| Node en servidor | **Desconocido** | `public/build` viaja compilado. Inofensivo si resulta que sí lo tienen |
| Redis | **No** | Colas y caché a MySQL. **Redis sale del proyecto entero** |
| Procesos permanentes | **Desconocido** | Worker por cron. Funciona igual si después aparece systemd |
| Entrega | Paquete comprimido **y** repositorio Git | Se producen los dos |
| MySQL | 8.0+ con permisos completos | Las 56 migraciones aplican sin obstáculo |
| Administrador inicial | Genérico, actualizable | `admin@usil.edu.pe` se conserva, pero **la contraseña deja de estar en el repositorio** |
| Respaldos | Diario, retención 30 días | Se documenta en el runbook |
| Railway | **Se descarta** | Sale `railway.json`, el `Dockerfile` raíz, `docker/railway/` y `deploy/RAILWAY.md` |
| Auditoría | Permiso retirado, pantalla a fase 2 | Sale `auditoria.ver` del catálogo |
| Catálogos día 1 | SUNEDU completo; mallas las carga el coordinador | `SuneduSeeder` entra en la instalación |
| Documentos A1/A2/A4/A6 | No entran | Se listan como pendientes con responsable |
| Campos del Documento 1 | No aplica | Sin trabajo |

**Consecuencia agregada de "sin Docker" + "sin Railway" + "sin Redis":** desaparece **toda** la infraestructura de contenedores del proyecto. Son 9 archivos menos y un runbook que por fin describe lo que va a ocurrir de verdad.

## 1.2 · Lo que cambia respecto del plan v1

### Se termina la retirada de Convalidación y Reportes

Es tu decisión y se ejecuta. Pero cambia la forma del trabajo: en la v1 la Fase 0 eran quince minutos de revertir; ahora es la fase más grande del plan.

Lo que implica, con nombre y apellido:

- **El sistema deja de emitir memorándum oficial.** Es el acto formal del proceso de convalidación. Queda gestionado fuera del sistema, según tu respuesta.
- **Hay que reescribir o retirar 21 pruebas** repartidas en cinco archivos (`AuditoriaE2ETest`, `IntegridadConvalidacionTest`, `SimulacionTest`, `DocumentosEmitidosTest`, `RbacTest`). Es el grueso del esfuerzo.
- **La tabla `convalidaciones` se conserva** con sus migraciones. El respaldo de marcha blanca tiene filas ahí y no se borra historia. Simplemente deja de escribirse.
- **Se pierde el candado de integridad.** `Simulacion::estaCerrada()` se apoya en que exista una convalidación confirmada. Sin `confirmar()`, siempre devuelve falso: **una preconvalidación ya entregada al postulante puede editarse indefinidamente y nada la congela.** Ver la pregunta abierta P-3.
- Salen del catálogo `convalidacion.confirmar`, `convalidacion.anular`, `reportes.ver` y `reportes.exportar`, más una migración que los borra de la base.
- `convalidacion.ver` **se conserva**: la pantalla Convalidaciones sigue existiendo como historial de simulaciones.

### El portal cambia de regla, y en la dirección contraria a lo que hay hoy

Tu respuesta: *el postulante ve los cursos preconvalidados en la interfaz, pero no descarga ningún documento oficial; eso se gestiona externamente.*

El trabajo sin confirmar hizo **la mitad correcta y la mitad al revés**: abrió la vista (bien) pero también abrió la descarga del PDF (mal). Lo correcto es más simple que cualquiera de los dos estados actuales:

- Se elimina la ruta `portal.preconvalidacion` y `Portal/PreconvalidacionController` **completo**. El postulante no descarga nada.
- El seguimiento muestra el detalle de cursos convalidados en pantalla.
- La etapa final del timeline deja de ser «Convalidación confirmada» —inalcanzable— y pasa a ser «Preconvalidación disponible», que sí se alcanza.
- El personal conserva sus descargas (`postulantes.preconvalidacion.pdf` y `.excel`). Solo el portal las pierde.

### La IA se entrega apagada

Sin aprobación legal y en calidad de piloto:

- El paquete de producción va **sin claves de IA**. `IAConvalidacionService::disponible()` devuelve falso y la interfaz recae automáticamente en el mapeo por similitud, que no envía nada fuera. Ese comportamiento ya existe y está probado.
- Se añade un aviso en la pantalla de Configuración: activar la IA exige aprobación legal previa por tratarse de transferencia internacional de datos personales (Ley 29733).
- **La corrección del IDOR (A-01) se hace igual.** Que hoy esté apagada no es motivo para dejar un fallo de control de acceso en el código que alguien encenderá mañana.
- Matiz que conviene registrar: la extracción de **mallas oficiales** (`MallaExternaController::extraerIA`) no procesa datos personales — son planes de estudio públicos. Tiene otro perfil de riesgo y podría habilitarse por separado si se quisiera.

---

# 2. Tres puntos que hay que escalar antes de entregar

## E-1 · ⚠️ El proyecto está construido sobre Laravel 11, no sobre Laravel 10

Tu respuesta a la pregunta 3 dice que **se solicitó Laravel 10.x**. Lo que hay construido es:

```
composer.json  →  "laravel/framework": "^11.31"   ·   "php": "^8.2"   ·   "inertiajs/inertia-laravel": "^2.0"
instalado      →  Laravel Framework 11.54.0
```

Y la documentación del propio proyecto ya declara Laravel 11. Es una **desviación de la especificación**, y es exactamente el tipo de cosa por la que un comité devuelve un expediente.

**No recomiendo bajar a Laravel 10, y el motivo no es el esfuerzo:**

1. **Empeoraría la seguridad.** `composer audit` ya reporta advisories de `laravel/framework` cuya corrección está en versiones **superiores**. Bajar a la 10.x aleja del parche, no acerca. Además Laravel 10 salió en febrero de 2023 y su ventana de soporte de seguridad ya cerró: sería entregar un framework que no recibe parches. Conviene que TI lo confirme con la política de soporte vigente de Laravel.
2. **No es una actualización de dependencia, es una reescritura del arranque.** Laravel 11 sustituyó `app/Http/Kernel.php`, `app/Console/Kernel.php`, `app/Exceptions/Handler.php` y `RouteServiceProvider` por el `bootstrap/app.php` que usa este proyecto. Todo el registro de middleware, el manejo de excepciones y el `health: '/up'` habría que rehacerlo. Estimo entre 15 y 25 horas, con riesgo de regresión en el RBAC — que es justo la parte que no conviene tocar.

**Lo que propongo:** solicitud formal de excepción, con la justificación técnica de arriba. Hay precedente en el propio expediente: el `INDICE.md` ya registra una excepción solicitada para el punto de API REST del §5. Puedo redactarla.

## E-2 · ⚠️ Nadie ha confirmado la versión de PHP del servidor

La respuesta 3 dijo qué se pidió, no qué hay instalado. **Es un riesgo binario:** el proyecto exige PHP 8.2 o superior, y si el servidor de TI tiene PHP 8.1 —muy común en Linux algo antiguo— `composer install` falla y la aplicación no arranca. No hay forma de programar alrededor de esto.

Es una sola pregunta a TI:

```bash
php -v && php -m
```

Debe devolver 8.2 o superior, y entre los módulos: `pdo_mysql`, `mbstring`, `gd`, `zip`, `bcmath`, `exif`, `fileinfo`, `openssl`.

## E-3 · ⚠️ Sin SMTP el sistema no puede dar de alta a nadie

Sigue sin resolverse y es la única pieza que deja el sistema inoperante aunque todo lo demás esté perfecto: **el correo es el único canal por el que se entregan las contraseñas**, tanto del personal como de los postulantes.

Y hoy falla en silencio: `UsuarioController::enviarCredenciales()` captura la excepción, escribe un aviso en el log y **la pantalla dice «Se enviaron las credenciales»** aunque no se haya enviado nada.

No puedo conseguir el SMTP, pero sí puedo hacer que la falta se note y dar una salida de emergencia. Va en la Fase 3:

- Que el fallo de envío se muestre en pantalla, no solo en el log.
- Un comando de rescate para TI: `php artisan usuario:password {email}` genera una contraseña temporal y la imprime en consola. Permite arrancar el día 1 sin SMTP.
- Un aviso visible cuando `MAIL_MAILER=log` con `APP_ENV=production`.

---

# 3. Preguntas abiertas (con valor por defecto, no bloquean)

| # | Pregunta | Por defecto |
|---|---|---|
| **P-1** | Sin TLS todavía: si se despliega primero en HTTP, `SESSION_SECURE_COOKIE=true` impide iniciar sesión — la cookie no viaja | Se entrega en `false` con un aviso destacado en el runbook para cambiarlo al activar HTTPS |
| **P-2** | ¿El postulante ve la simulación en cuanto se guarda, o hace falta un acto explícito de «publicar»? | Visible al guardarse. **Riesgo:** si el coordinador la edita después, lo que ve el postulante cambia sin avisar |
| **P-3** | Perdido el candado de integridad, ¿se acepta que una preconvalidación entregada siga siendo editable? | Se acepta y se documenta. La alternativa —una acción explícita de «cerrar expediente»— es alcance nuevo: 2 a 3 horas |
| **P-4** | Con la IA apagada, ¿se retira también la extracción de mallas oficiales, que no procesa datos personales? | Se conserva, apagada por falta de clave, y se documenta que tiene otro perfil de riesgo |

---

# 4. Plan de implementación

## Fase 0 · Punto de retorno

| | Acción |
|---|---|
| 0.1 | Confirmar el estado actual en la rama `wip/retirada-convalidacion-checkpoint` como red de seguridad |
| 0.2 | Volver a la rama de entrega y continuar desde donde está — el trabajo sin confirmar es el **comienzo** de la dirección elegida, no algo que revertir |

**Prueba:** `git log` muestra el checkpoint recuperable.
**Duración:** 15 minutos.

---

## Fase 1 · Terminar la retirada de Convalidación y Reportes

*La fase más grande. Deja la suite verde otra vez, que es la condición para todo lo demás.*

| | Acción | Hallazgo |
|---|---|---|
| 1.1 | `ConfiguracionController`: eliminar la sección de memorándum completa (`responsablesMemo`, `MEMO_DEFAULTS`, `updateMemorandum`) y su ruta | **B-02** |
| 1.2 | `Configuracion/Index.vue`: quitar el formulario de responsables del memorándum | B-02 |
| 1.3 | Borrar `ReporteController`, `ConvalidacionesExport`, `Pages/Reportes/Index.vue` y `views/pdf/memorandum.blade.php` | B-03 |
| 1.4 | Retirar del catálogo `convalidacion.confirmar`, `convalidacion.anular`, `reportes.ver`, `reportes.exportar` y `auditoria.ver`, más migración que los borra de `permisos` y `rol_permiso` | B-03 · M-05 |
| 1.5 | Reescribir o retirar las 21 pruebas de los cinco archivos afectados. Lo que valida reglas vigentes se conserva; lo que valida confirmar/anular/memorándum se retira | B-03 |
| 1.6 | `SeguimientoTimeline`: cuarta etapa → «Preconvalidación disponible», alcanzable | B-03 |
| 1.7 | Conservar tabla y modelo `Convalidacion` en solo lectura; documentar que deja de escribirse | — |
| 1.8 | Documentar la pérdida del candado de integridad, o implementar P-3 si se decide | P-3 |

**Prueba:** `php artisan test` verde. `grep` sin referencias colgantes a los símbolos retirados.
**Duración:** 6 a 8 horas.

---

## Fase 2 · Portal del postulante según la regla nueva

| | Acción |
|---|---|
| 2.1 | Eliminar la ruta `portal.preconvalidacion` y `Portal/PreconvalidacionController` completo |
| 2.2 | `Portal/SeguimientoController`: quitar `pdf_url`, exponer el detalle de cursos convalidados |
| 2.3 | `Portal/Seguimiento.vue`: mostrar los cursos en pantalla, sin botón de descarga |
| 2.4 | Prueba: el postulante ve sus cursos y **no** existe ninguna ruta de descarga en el portal |
| 2.5 | Verificar que el personal conserva `postulantes.preconvalidacion.pdf` y `.excel` |

**Prueba:** suite verde con la prueba nueva del portal.
**Duración:** 1 a 2 horas.

---

## Fase 3 · Seguridad y correcciones funcionales

| | Acción | Hallazgo |
|---|---|---|
| 3.1 | `AlcanceService::autorizarPostulante()` en `SimulacionController::extraerIA()` + prueba que falla sin ella | **A-01** |
| 3.2 | Control de alcance en `sugerirSimilitud()` y `sugerirIA()` | A-01 menor |
| 3.3 | `AdminUserSeeder`: `firstOrCreate` y contraseña desde variable de entorno + prueba | **A-02** |
| 3.4 | `MallaExternaController`: excepción al log, mensaje genérico al cliente | **A-03** |
| 3.5 | Control de alcance por carrera en los cinco métodos de `MallaImportController` + prueba | **A-05** |
| 3.6 | Documentos por tipo (`updateOrCreate` + borrar el anterior) y contar tipos distintos en el portal | **M-02** |
| 3.7 | Código de postulante derivado del `id` dentro de la transacción | **M-06** |
| 3.8 | `mb_substr()` en `MallaExternaController::store()` | M-07 |
| 3.9 | Decidir el disco de los PDF de mallas externas | **A-04** |
| 3.10 | Fallo de correo visible en pantalla + comando `usuario:password` + aviso de `MAIL_MAILER=log` en producción | **E-3** |
| 3.11 | Aviso de aprobación legal en la pantalla de Configuración de IA | — |
| 3.12 | `./vendor/bin/pint` | **B-07** |

**Prueba:** suite verde con seis pruebas nuevas; `pint --test` limpio.
**Duración:** 3 a 4 horas.

---

## Fase 4 · Dependencias

*Aislada: es la única fase cuyo resultado no puedo predecir.*

| | Acción |
|---|---|
| 4.1 | `composer update` sobre `laravel/framework`, `guzzlehttp/guzzle`, `phpoffice/phpspreadsheet`, `dompdf/dompdf`, `league/commonmark` |
| 4.2 | Suite completa. **Riesgo:** PHPSpreadsheet corrige sus tres altas muy por encima de la versión instalada y `maatwebsite/excel` fija su propia restricción. Puede no converger, o converger con cambios de API en el lector de Excel |
| 4.3 | Si no converge: documentar cada advisory vivo con su impacto real y su mitigación, para que TI decida con información |
| 4.4 | Retirar `"advisories": {"block": false}` de `composer.json` |

**Prueba:** `composer audit` limpio, o la lista justificada de 4.3. Suite verde.
**Duración:** 2 a 3 horas, con incertidumbre real.

---

## Fase 5 · Simplificar la infraestructura

*Aquí se borra mucho más de lo que se escribe.*

| | Acción | Hallazgo |
|---|---|---|
| 5.1 | Eliminar `docker-compose.prod.yml`, `docker-compose.yml`, `docker/` completo y `.dockerignore` | **B-05** |
| 5.2 | Eliminar `railway.json`, el `Dockerfile` raíz y `deploy/RAILWAY.md` | — |
| 5.3 | Redis fuera: `.env.example`, plantilla de producción y comandos del worker. Colas y caché a MySQL | — |
| 5.4 | Migraciones de `jobs`, `failed_jobs`, `job_batches`, `cache` y `cache_locks` | **M-03** |
| 5.5 | Quitar de `config/auth.php` la referencia a `password_reset_tokens`, tabla inexistente y sin uso | M-03 |
| 5.6 | Borrar los tres `config/*_snippet.php` — Laravel los carga como configuración real | Sección 4 |
| 5.7 | Borrar `public/hot` y añadirlo a la exclusión del empaquetado | **A-07** |
| 5.8 | Eliminar `CarreraPolicy` y su registro: declarada y nunca invocada | Sección 4 |
| 5.9 | Vaciar `routes/console.php` del comando `inspire` de ejemplo | Sección 4 |
| 5.10 | **Alojar las tipografías localmente.** Con la IA apagada, Google Fonts pasa a ser la única dependencia de salida a internet. Quitarla elimina la pregunta 12 del riesgo | Sección 6 |

**Prueba:** `migrate:fresh` + `db:seed` sobre base vacía y suite verde con `QUEUE_CONNECTION=database`.
**Efecto colateral:** al desaparecer Redis desaparece **M-01**, la condición de carrera de la carga masiva. Ya no tiene dónde ocurrir.
**Duración:** 2 a 3 horas.

---

## Fase 6 · Runbook Apache y paquete de entrega

*Sustituye al bloqueante B-05. Es lo que TI va a tener en la mano.*

| | Acción |
|---|---|
| 6.1 | Reescribir `deploy/RUNBOOK.md` completo para **Apache 2.4 + PHP 8.2 + MySQL 8, sin Docker**: requisitos verificables, permisos de `storage/` y `bootstrap/cache`, `VirtualHost`, `storage:link`, cachés, migraciones y verificación |
| 6.2 | Documentar el worker por cron con la línea de `crontab` lista para copiar, y la variante systemd por si aparece |
| 6.3 | Procedimiento de **actualización** (incluido `queue:restart`, que hoy falta) y de **rollback** sin Docker |
| 6.4 | Cablear `SuneduSeeder` en la instalación inicial y documentar la carga de mallas por el coordinador como tarea día 1 | **M-04** |
| 6.5 | Reescribir `DESPLIEGUE.md`, que describe una estructura que ya no existe |
| 6.6 | Construir el paquete: `vendor/` sin dependencias de desarrollo, `public/build` compilado, **sin** `.env`, `backups/`, `.claude/`, `.cursor/`, `.phpunit.result.cache` ni `public/hot` | **A-06** |
| 6.7 | **Ensayo de instalación limpia.** Clonar en un directorio vacío, instalar siguiendo el runbook al pie de la letra y arrancar. Es el paso que atrapa la clase de fallo de `VolverA.vue`: nada de lo que hay en esta máquina cuenta como prueba |

**Prueba:** el 6.7 **es** la prueba. Si arranca desde un clon limpio y se puede iniciar sesión, el runbook sirve.
**Duración:** 4 a 5 horas.

---

## Fase 7 · Cierre documental

| | Acción |
|---|---|
| 7.1 | Actualizar la auditoría con el estado final de cada hallazgo: corregido, aceptado o diferido con responsable |
| 7.2 | Redactar la **solicitud de excepción por Laravel 11** (E-1) con su justificación técnica |
| 7.3 | Dejar por escrito que la IA se entrega apagada y qué se exige para encenderla |
| 7.4 | Registrar la retirada de Convalidación y Reportes, y que el memorándum oficial pasa a gestionarse fuera del sistema |
| 7.5 | Actualizar el `INDICE.md` con lo que entra y lo que queda pendiente, con responsable |
| 7.6 | Etiquetar `v1.0.0` para que el rollback del runbook tenga a dónde volver |

**Duración:** 2 horas.

---

# 5. Capacidad y forma de ejecución

**Total estimado: 20 a 27 horas.** Son entre 9 y 11 horas más que el camino de revertir, y la diferencia está casi toda en la Fase 1 — reescribir 21 pruebas y desmontar un módulo con cuidado no se acelera.

**No cabe en una ejecución.** Propongo cinco, cada una cerrada por una prueba distinta:

| Ejecución | Fases | Qué la cierra | Duración |
|---|---|---|---|
| **1ª** | 0 + 1 | La suite vuelve a verde tras la retirada | 6–8 h |
| **2ª** | 2 + 3 | Suite verde con las seis pruebas nuevas de portal, alcance y seeder | 4–6 h |
| **3ª** | 4 | `composer audit` limpio, o la lista justificada | 2–3 h |
| **4ª** | 5 + 6 | **Instalación desde un clon vacío** | 6–8 h |
| **5ª** | 7 | Tu revisión | 2 h |

Los cortes no son arbitrarios:

- **La Fase 4 puede salir mal y no sé de antemano cómo.** Quiero que un `composer update` fallido se vea contra un árbol verde y no enterrado bajo otros veinte cambios.
- **La Fase 6 termina en un ensayo de instalación**, que no vale nada si el código sigue moviéndose mientras corre.
- **Las fases 1, 3 y 5 tocan control de acceso, alcance por carrera y permisos.** Es donde menos conviene que yo llegue con seis horas de contexto acumulado encima.

**Ruta más corta si hay que entregar algo hoy:** ejecuciones 1 y 2 dejan el sistema **funcionalmente correcto y seguro** (suite verde, IDOR cerrado, seeder arreglado, portal según la regla nueva). Lo que faltaría es el runbook y el paquete — es decir, TI recibiría un sistema sano sin instrucciones de instalación. No lo recomiendo, pero es una entrega parcial defendible si la fecha aprieta.

---

# 6. Lo que este plan no resuelve

- **Que funcione en el servidor de USIL.** Puedo demostrar que instala desde un clon limpio aquí. La validación en su infraestructura es de TI.
- **El SMTP (E-3).** Se mitiga para que no falle en silencio y se añade una salida de emergencia, pero sin servidor de correo el sistema no entrega credenciales.
- **La versión de PHP del servidor (E-2).** Riesgo binario pendiente de una sola respuesta.
- **La excepción por Laravel 11 (E-1).** Redacto la solicitud; la aprobación es del comité.
- **La aprobación legal de la IA.** Se entrega apagada; encenderla requiere el respaldo que hoy no existe.
- **Los documentos A1, A2, A4, A6 y los diagramas D-03/D-04/D-06**, fuera de esta entrega por decisión tomada.
- **La pantalla de consulta de auditoría**, diferida a fase 2 por decisión tomada.
