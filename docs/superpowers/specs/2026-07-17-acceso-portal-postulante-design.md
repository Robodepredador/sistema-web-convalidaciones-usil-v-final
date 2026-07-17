# Diseño — Envío de credenciales al postulante y acceso al portal de seguimiento

**Fecha:** 2026-07-17
**Rama base:** `fix/flujos-por-rol-admision`
**Autor:** Frank Rodríguez

## Problema

Cuando el Asesor de Admisión registra a un postulante con correo, el sistema genera
una contraseña temporal y **la muestra en pantalla al asesor**, pero **no la envía al
postulante**. El postulante no recibe forma de enterarse de sus credenciales para
entrar al portal y ver el estado de su trámite de convalidación.

## Objetivo

1. Al registrar (o resetear el acceso de) un postulante con correo, enviarle por
   email su **usuario (correo) y contraseña temporal**, junto con la URL del portal.
2. Forzar el **cambio de contraseña en el primer acceso** al portal (opción híbrida).
3. Corroborar, con pruebas automatizadas, que el postulante puede acceder y ver el
   **seguimiento de su trámite en todas sus fases**.

## Contexto — lo que YA existe (no se modifica)

- **Portal del postulante** funcionando: `/portal/login` (guard `postulante`) y
  `/portal/` (`SeguimientoController@index`) con timeline, estado y observaciones.
- `PostulanteController@store` y `@resetAcceso` ya generan la contraseña temporal
  (`Str::password(10)`), la hashean en `password_hash`, activan `acceso_habilitado`
  y muestran las credenciales al asesor vía flash `status`.
- Patrón de correo existente: `App\Mail\RecuperarPasswordMail` + vista markdown
  `emails/recuperar-password.blade.php` + `Mail::to()->send()` envuelto en `try/catch`.
  Mailer por defecto = `log` (no rompe sin SMTP).
- `Postulante` implementa `Authenticatable` (`getAuthPassword()` → `password_hash`).
- Tabla `postulantes` tiene: `password_hash`, `acceso_habilitado`, `ultimo_acceso`.
  **No** tiene un flag de "primer acceso".

## Cambios

### 1. Enviar credenciales por correo

- **Nuevo Mailable** `App\Mail\AccesoPortalMail` (espejo de `RecuperarPasswordMail`),
  con `$postulante`, `$url` (portal), `$password` (temporal).
- **Nueva vista** `resources/views/emails/acceso-portal.blade.php`: saludo, URL del
  portal, usuario = correo, contraseña temporal, aviso de que deberá cambiarla al
  ingresar por primera vez.
- En `PostulanteController@store` (solo cuando hay correo) y `@resetAcceso`: tras
  generar el temporal, `Mail::to($postulante->email)->send(new AccesoPortalMail(...))`
  envuelto en `try/catch` con `Log::warning` en fallo (idéntico criterio al de
  recuperación).
- **Se mantiene** el flash con las credenciales en pantalla como respaldo (sin SMTP el
  asesor sigue teniendo acceso a las credenciales para entregarlas manualmente).

### 2. Forzar cambio de contraseña en el primer acceso (híbrido)

- **Migración**: agregar `debe_cambiar_password` (boolean, default `false`) a
  `postulantes`. Se pone en `true` al crear el acceso (`store`) y al resetearlo
  (`resetAcceso`). Añadir a `$fillable`/`$casts` del modelo.
- **Rutas** nuevas dentro del grupo `auth:postulante`:
  `GET /portal/password/cambiar` y `POST /portal/password/cambiar`.
- **Controlador** `App\Http\Controllers\Portal\PasswordController` (espejo del
  `PasswordController@actualizar` del app, pero sobre el guard `postulante`): valida la
  nueva contraseña (confirmada, mínimo razonable), la guarda en `password_hash` y pone
  `debe_cambiar_password = false`.
- **Middleware** `postulante.debe-cambiar` aplicado a la ruta de seguimiento (no a la de
  cambio, para evitar bucle): si el postulante autenticado tiene
  `debe_cambiar_password = true`, redirige a `portal.password.cambiar.form`.
- **Vista** Inertia `Portal/CambiarPassword` (espejo de `Auth/CambiarPassword`).

### 3. Corroboración por pruebas — seguimiento en TODAS sus fases

Test de feature (con `Mail::fake()`, MySQL real como el resto de la suite):

**a) Acceso end-to-end:**
- Registrar/crear un postulante con correo → assert que se envió `AccesoPortalMail`
  al correo del postulante.
- Login en `/portal/login` con la contraseña temporal → es redirigido a
  `portal.password.cambiar.form` (por `debe_cambiar_password`).
- Cambiar la contraseña → `debe_cambiar_password = false` → acceso a
  `portal.seguimiento`.

**b) Seguimiento en todas las fases del timeline** (`SeguimientoController@timeline`):
recorrer el expediente por cada etapa y assert el estado del timeline
(`completado` / `actual` / `pendiente`) en cada punto:
1. **Solicitud registrada** — siempre `completado`.
2. **Documentos recibidos** — `completado` cuando el postulante tiene ≥ 3 documentos.
3. **Revisión de equivalencias** — `completado` cuando todos los `destinos` tienen
   `estado_equivalencias = 'aprobada'` (probar también `en_revision`).
4. **Simulación de convalidación** — `completado` cuando existe al menos una simulación.
5. **Convalidación confirmada** — `completado` cuando una simulación está `confirmada`.

   Verificar además que la **primera etapa no completada** se marque como `actual` y las
   siguientes como `pendiente`.
- **Ruta de rechazo**: con `estado = 'rechazado'`, el timeline devuelve la única etapa
  "Solicitud rechazada".

### 4. Menor
- Documentar variables `MAIL_*` en `.env.example` (mailer, host, from) para habilitar
  entrega real cuando se configure SMTP.

## Fuera de alcance (YAGNI)

- No se toca el motor de seguimiento/timeline (ya refleja el estado del trámite).
- No se agregan colas/notificaciones nuevas (`log` + `sync` bastan; el `try/catch`
  evita romper el registro sin SMTP).
- No se cambia el guard ni el modelo de autenticación del postulante.
- Botón de "reenviar acceso" desde el expediente: fuera por ahora (ya existe
  `resetAcceso`, que además reenvía el correo).

## Criterios de aceptación

- Registrar un postulante con correo genera y **envía** por email usuario + contraseña
  temporal + URL del portal.
- El postulante inicia sesión, es **obligado a cambiar** la contraseña y luego ve el
  seguimiento de su trámite.
- Existe una prueba automatizada que recorre el seguimiento por **todas las fases** del
  timeline y valida sus estados.
- El registro no se rompe si no hay SMTP configurado (correo falla en silencio, flash de
  respaldo intacto).
