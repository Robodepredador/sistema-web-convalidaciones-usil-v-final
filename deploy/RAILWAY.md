# Despliegue en Railway

Alternativa gestionada al despliegue en servidor propio de `RUNBOOK.md`. Railway
construye el `Dockerfile` de la raíz y administra MySQL y Redis; el resto del
procedimiento (respaldo, rollback, checklist de seguridad) sigue valiendo igual.

## Arquitectura

Tres servicios en un mismo proyecto de Railway:

| Servicio | Origen | Qué corre |
|----------|--------|-----------|
| `app` | este repositorio (`Dockerfile`) | Apache + PHP 8.2 + worker de colas, bajo supervisor |
| `MySQL` | plantilla de Railway | Base de datos 8.x |
| `Redis` | plantilla de Railway | Colas y caché |

**Web y worker van juntos en `app`, a propósito.** El worker tiene que leer el
Excel que subió la web, y `ImportarMallaExcel` lo abre con `Storage::path()`,
que solo funciona sobre disco local. Como un volumen de Railway se monta en un
único servicio, separarlos dejaría al worker sin el archivo y rompería la carga
masiva (RF-11).

## 1. Crear el proyecto

En Railway: **New Project → Deploy from GitHub repo →** `Robodepredador/usil_convalidaciones`.
Detecta el `Dockerfile` de la raíz y `railway.json` (healthcheck en `/up`).

Después, **+ New → Database → Add MySQL**, y otra vez **+ New → Database → Add Redis**.

## 2. Volumen para los archivos subidos

En el servicio `app`: **Settings → Volumes → Add Volume**, punto de montaje:

```
/var/www/html/storage/app
```

Sin esto, los Excel de carga masiva, los PDF de mallas externas y los documentos
de postulantes se borran en cada redespliegue. El montaje es sobre `storage/app`
y no sobre `storage/` entero, para no tapar `storage/framework` ni las fuentes de
DomPDF que vienen en la imagen.

## 3. Variables de entorno

En `app` → **Variables**. Las referencias `${{...}}` las resuelve Railway.

```bash
APP_NAME="Convalidaciones USIL"
APP_ENV=production
APP_DEBUG=false
APP_KEY=            # ver abajo — NO dejarlo vacío
APP_URL=https://${{RAILWAY_PUBLIC_DOMAIN}}   # RAILWAY_PUBLIC_DOMAIN no trae el esquema

DB_URL=${{MySQL.MYSQL_URL}}
REDIS_URL=${{Redis.REDIS_URL}}

QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=database
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax

MAIL_MAILER=smtp
MAIL_HOST=__definir__
MAIL_PORT=587
MAIL_USERNAME=__definir__
MAIL_PASSWORD=__definir__
MAIL_SCHEME=tls
MAIL_FROM_ADDRESS="no-responder@usil.edu.pe"
MAIL_FROM_NAME="${APP_NAME}"

IA_PROVEEDOR=gemini
GEMINI_API_KEY=__definir__
GEMINI_MODEL=gemini-2.5-flash

BCRYPT_ROUNDS=12
LOG_CHANNEL=stderr
APP_LOCALE=es
APP_FALLBACK_LOCALE=es
```

**`APP_KEY`** se genera UNA vez y se respalda fuera de Railway:

```bash
php artisan key:generate --show
```

Cifra las API keys guardadas en la tabla `configuraciones`. Cambiarla las vuelve
indescifrables y cierra todas las sesiones activas.

**`MAIL_*` es obligatorio.** Sin SMTP, `MAIL_MAILER` cae a `log` y las
contraseñas temporales del personal y de los postulantes nunca se envían: el
alta parece funcionar y falla en silencio. Es el único canal de entrega.

`LOG_CHANNEL=stderr` en vez de `daily`: Railway no da acceso al sistema de
archivos, y su consola solo muestra la salida estándar.

## 4. Primer despliegue

El arranque (`docker/railway/entrypoint.sh`) hace solo, en cada despliegue:
enlaza `public/storage`, genera los cachés de configuración/rutas/vistas con las
variables ya inyectadas y lanza `php artisan migrate --force` (idempotente).

**Las migraciones corren en segundo plano, no antes de Apache.** Railway levanta
la app y MySQL en paralelo, así que en el primer despliegue la base suele tardar
en aceptar conexiones; el arranque reintenta hasta 10 veces cada 6s. Si aun así
fallan, el contenedor **sigue en pie** y el error queda en los logs de Railway —
si en cambio tumbara el proceso, `/up` no respondería, el healthcheck fallaría y
no habría forma de leer la causa.

Si tras un despliegue la app responde pero da errores de tabla inexistente, es
esto: busque `[entrypoint]` en los logs.

Falta sembrar los roles y el administrador inicial, una única vez:

```bash
railway run --service app php artisan db:seed
```

Crea `admin@usil.edu.pe` / `Admin#2026`. **Cambie esa contraseña en el primer
acceso.**

## 5. Verificación post-despliegue

- [ ] `/up` responde 200 (Railway lo marca verde solo).
- [ ] Entrar con el administrador y completar el cambio de contraseña forzado (RF-42).
- [ ] Importar un Excel de malla y ver que la carga **cambia de estado**: eso prueba
      que el worker vive y que Redis conecta.
- [ ] Volver a desplegar y confirmar que el Excel importado sigue ahí (volumen bien montado).
- [ ] Generar una simulación y descargar su PDF.
- [ ] Crear un usuario de prueba y confirmar que **recibe el correo** con sus credenciales.

## Límites conocidos

- **Web y worker escalan juntos.** Es deliberado (ver Arquitectura). Si algún día
  hiciera falta separarlos, primero hay que mover las subidas al disco `s3` y
  reemplazar el `Storage::path()` de `ImportarMallaExcel` por una lectura por
  stream; hasta entonces, no se pueden separar.
- **Sin `schedule:run`.** El proyecto hoy no define ninguna tarea programada, así
  que el contenedor no arranca un scheduler. Si se agrega una, hay que añadir su
  `[program:scheduler]` a `docker/railway/supervisord.conf`.
- **Las migraciones corren en cada despliegue.** Una migración destructiva se
  aplica sin confirmación previa. Respalde antes de desplegar cambios de esquema:
  `railway run --service MySQL mysqldump ... > respaldo.sql`.
- **El healthcheck no verifica la base de datos.** `/up` responde 200 en cuanto
  Apache escucha, aunque las migraciones sigan corriendo o hayan fallado. Es
  deliberado (ver arriba), pero significa que un despliegue verde no garantiza
  por sí solo que el esquema esté al día: confírmelo con la lista de §5.
