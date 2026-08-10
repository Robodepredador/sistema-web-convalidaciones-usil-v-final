# Runbook de despliegue — Sistema de Convalidaciones USIL

Procedimiento de instalación, actualización y reversión sobre **Apache 2.4 + PHP 8.2 + MySQL 8**, sin contenedores.

> Este runbook describía un despliegue con Docker Compose. Se reescribió el 10/08/2026 al confirmarse que TI no dispone de Docker: un procedimiento que nadie puede ejecutar es peor que ninguno. Con él salieron del proyecto `docker/`, ambos `docker-compose*.yml` y la configuración de Railway.

---

## 1. Requisitos — verificar ANTES de nada

Ejecutar en el servidor y comprobar cada salida. **Si PHP es 8.1 o anterior, la instalación no puede continuar**: `composer install` aborta y la aplicación no arranca.

```bash
php -v && php -m && mysql --version && apache2 -v
```

| Requisito | Mínimo | Cómo se comprueba |
|---|---|---|
| PHP | **8.2** | `php -v` |
| Extensiones PHP | `pdo_mysql` `mbstring` `gd` `zip` `bcmath` `exif` `fileinfo` `openssl` `ctype` `json` `tokenizer` `xml` | `php -m` |
| MySQL | 8.0, motor InnoDB | `mysql --version` |
| Apache | 2.4 con `mod_rewrite` | `apache2ctl -M \| grep rewrite` |
| Certificado TLS | válido para el dominio | — |
| Cuenta MySQL | permisos `CREATE`, `ALTER`, `INDEX`, `DROP` sobre su base | las 71 migraciones los necesitan |

`php.ini` — la carga masiva acepta Excel de hasta 10 MB:

```ini
upload_max_filesize = 12M
post_max_size = 12M
memory_limit = 512M
max_execution_time = 120
```

**No hacen falta** Docker, Redis, Node ni Composer en el servidor: el paquete de entrega ya lleva `vendor/` instalado y `public/build` compilado.

---

## 2. Estructura y permisos

Desplegar el paquete en, por ejemplo, `/var/www/convalidaciones`.

> El paquete se construye con `bash deploy/empaquetar.sh`, que parte de un clon
> limpio del repositorio, instala las dependencias sin las de desarrollo, compila
> el frontend y **verifica que no viaje `.env`, `backups/` ni ningún caché de
> configuración con credenciales dentro**. Si algo falta o algo sobra, aborta.

```bash
cd /var/www/convalidaciones
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

> `storage/` guarda los documentos de los postulantes (récords, DNI, sílabos) y los PDF de mallas externas. **Debe quedar fuera del alcance del navegador**: solo `public/` se publica. El `DocumentRoot` del paso 4 se encarga de eso.

---

## 3. Configuración

```bash
cp deploy/.env.production.example .env
```

Completar **todos** los `__definir__`. Tres avisos que ahorran horas de diagnóstico:

- **`MAIL_*` es obligatorio.** El correo es el único canal por el que se entregan las contraseñas, del personal y de los postulantes. Sin SMTP el sistema avisa en pantalla de que no se envió, pero nadie podrá iniciar sesión. Salida de emergencia: §8.
- **`SESSION_SECURE_COOKIE`** se entrega en `false`. Con HTTPS activo hay que ponerlo en `true`. Al revés —`true` sirviendo por HTTP— la cookie no viaja y **nadie puede iniciar sesión**, sin ningún mensaje que lo explique.
- **`APP_KEY` se genera UNA sola vez** y se respalda. Cifra las API keys guardadas en `configuraciones`; regenerarla las vuelve indescifrables y cierra todas las sesiones.

```bash
php artisan key:generate      # SOLO en la instalación inicial
```

---

## 4. Apache

```apache
<VirtualHost *:443>
    ServerName convalidaciones.usil.edu.pe
    DocumentRoot /var/www/convalidaciones/public

    SSLEngine on
    SSLCertificateFile      /ruta/al/certificado.crt
    SSLCertificateKeyFile   /ruta/a/la/clave.key

    <Directory /var/www/convalidaciones/public>
        AllowOverride All          # imprescindible: public/.htaccess trae el reescrito
        Require all granted
        Options -Indexes
    </Directory>

    # Cabeceras de seguridad (requiere mod_headers).
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set Referrer-Policy "same-origin"
    Header always set Strict-Transport-Security "max-age=31536000"

    ErrorLog  ${APACHE_LOG_DIR}/convalidaciones-error.log
    CustomLog ${APACHE_LOG_DIR}/convalidaciones-access.log combined
</VirtualHost>

# Todo el tráfico por HTTPS.
<VirtualHost *:80>
    ServerName convalidaciones.usil.edu.pe
    Redirect permanent / https://convalidaciones.usil.edu.pe/
</VirtualHost>
```

```bash
a2enmod rewrite ssl headers && systemctl reload apache2
```

---

## 5. Instalación inicial

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

`db:seed` deja el sistema operable: 8 perfiles con su matriz de permisos, 206 instituciones de origen licenciadas por SUNEDU, 9 facultades y 40 programas de USIL, sedes y modalidades. **No crea cuentas demo con `APP_ENV=production`.**

> **El administrador inicial se crea con una contraseña aleatoria que se imprime UNA vez, en esa salida.** Anótela antes de cerrar la terminal. Si se pierde, se regenera con `php artisan usuario:password admin@usil.edu.pe`.

**Tarea del día 1, en la aplicación:** el coordinador carga la malla curricular de cada carrera destino. Sin plan de estudios cargado no se puede evaluar ninguna convalidación — el sistema lo dice, pero conviene no descubrirlo con el primer postulante delante.

---

## 6. Worker de la carga masiva — imprescindible

La importación de mallas por Excel (RF-11) corre en segundo plano. **Sin worker, las importaciones quedan encoladas para siempre y la pantalla se queda en «procesando».**

Las colas van sobre MySQL: no hace falta Redis.

### Opción A — cron (por defecto)

```cron
* * * * * cd /var/www/convalidaciones && php artisan queue:work --stop-when-empty --max-time=55 >> storage/logs/worker.log 2>&1
```

Procesa lo pendiente y termina; el siguiente minuto vuelve a arrancar. Una importación puede tardar hasta 60 s en empezar: para este uso es irrelevante.

### Opción B — systemd (si se pueden ejecutar servicios)

```ini
# /etc/systemd/system/convalidaciones-worker.service
[Unit]
Description=Worker de colas — Convalidaciones USIL
After=mysql.service

[Service]
User=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/convalidaciones
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --timeout=600

[Install]
WantedBy=multi-user.target
```

```bash
systemctl enable --now convalidaciones-worker
```

---

## 7. Verificación posterior (smoke test)

- [ ] La página de login responde **por HTTPS** y el HTTP redirige.
- [ ] Los estilos y el JavaScript cargan (si la página se ve sin formato, falta `public/build`).
- [ ] Inicio de sesión con `admin@usil.edu.pe` y **cambio de contraseña forzado**.
- [ ] `SELECT email FROM usuarios WHERE email LIKE '%.demo@%';` devuelve **0 filas**.
- [ ] Crear un usuario de prueba con perfil distinto de Superusuario: **debe llegar el correo** con sus credenciales. Si la pantalla avisa de que no se envió, revisar `MAIL_*` antes de seguir.
- [ ] Registrar una institución y su malla externa; el PDF adjunto se descarga desde la aplicación (no por URL directa: está fuera de `public/`).
- [ ] Importar una malla por Excel y comprobar que **el progreso avanza** (valida el worker del §6).
- [ ] Generar una preconvalidación y descargar su PDF y su Excel.
- [ ] Entrar al portal con un postulante y ver sus cursos en pantalla.

## 8. Si el correo aún no está disponible

El sistema no oculta el problema: al dar de alta a alguien, la pantalla dice si el envío no salió. Para entregar una contraseña a mano:

```bash
php artisan usuario:password correo@usil.edu.pe
```

Genera una temporal, la imprime y obliga a cambiarla en el primer acceso. Sirve para personal y para postulantes. **Es un apaño para arrancar, no un sustituto del SMTP**: sin correo no hay recuperación de contraseña ni alta masiva.

---

## 9. Nota sobre proxies

La aplicación **no confía en ninguna cabecera de proxy**, porque Apache sirve y termina TLS directamente. Si se coloca un balanceador o un proxy inverso delante, hay que declararlo en `bootstrap/app.php` con sus IP reales:

```php
$middleware->trustProxies(at: ['10.0.0.1', '10.0.0.2']);
```

Sin eso, `url()` generaría `http://` bajo un dominio HTTPS y `auditoria_log` registraría la IP del proxy para todos, perdiendo la trazabilidad de RNF-08. **Nunca usar `'*'`**: permite falsear la IP de origen.

---

## 10. Actualizaciones

```bash
cd /var/www/convalidaciones
php artisan down                       # modo mantenimiento
git pull                               # o desplegar el paquete nuevo
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart              # el worker recarga el código nuevo
php artisan up
```

> **`db:seed` NO se ejecuta en las actualizaciones**, solo en la instalación inicial. Los catálogos ya están y volver a sembrarlos no aporta nada.

---

## 11. Reversión

```bash
php artisan down
git checkout <etiqueta-anterior>       # p. ej. v1.0.0
php artisan migrate:rollback --step=1  # SOLO si la actualización trajo migraciones
php artisan config:cache && php artisan route:cache && php artisan view:cache
php artisan queue:restart
php artisan up
```

- **No ejecutar `key:generate` durante una reversión**: invalidaría las API keys cifradas y las sesiones.
- Si una migración destruyó datos, restaurar el respaldo del §12 antes de volver a levantar.

---

## 12. Operación continua

- **Respaldo diario** de la base, retención 30 días. Probar la **restauración**, no solo el volcado.
- **Respaldar `storage/app`**: ahí viven los documentos de los postulantes y los PDF de mallas. No están en la base.
- Rotación de logs (`LOG_CHANNEL=daily`).
- `auditoria_log` crece sin purga automática: revisar su tamaño cada trimestre.
- Revisar `failed_jobs` periódicamente: una importación fallida deja rastro ahí.

---

## 13. Estado de seguridad conocido

Documentado en `docs/Auditoria-Entrega-TI-2026-08-10.md`:

- `composer audit` reporta **3 advisories de `laravel/framework`**, sin parche en la rama 11.x. Uno no tiene exposición (la aplicación no usa URLs firmadas); el otro —inyección CRLF en la regla `email`— **está mitigado en código** (`App\Rules\Correo`).
- Por eso `composer.json` conserva `"advisories": {"block": false}`: activarlo haría fallar `composer install`. Retirarlo cuando se resuelva la versión del framework.
- El motor de IA se entrega **apagado**. Encenderlo transfiere datos personales a un proveedor externo fuera del país (Ley N.° 29733) y **exige autorización escrita del área legal**.
