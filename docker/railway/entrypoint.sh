#!/bin/sh
# Arranque en Railway. Todo lo que depende de variables de entorno va aquí y no
# en el Dockerfile: durante el build, Railway todavía no las ha inyectado.
set -e

# Railway asigna el puerto en tiempo de ejecución; Apache lo trae fijo en 80.
: "${PORT:=8080}"
sed -ri "s!^Listen 80\$!Listen ${PORT}!" /etc/apache2/ports.conf
sed -ri "s!<VirtualHost \*:80>!<VirtualHost *:${PORT}>!" /etc/apache2/sites-available/000-default.conf

# El volumen se monta vacío la primera vez y tapa el esqueleto de la imagen.
mkdir -p storage/app/private storage/app/public storage/logs \
         storage/framework/cache/data storage/framework/sessions storage/framework/views
chown -R www-data:www-data storage bootstrap/cache

# Disco 'public': las mallas externas en PDF se sirven por public/storage.
# No es fatal: un symlink fallido no justifica dejar el sitio sin servidor web.
php artisan storage:link --force || echo "[entrypoint] AVISO: storage:link falló." >&2

# Cachés con las variables ya presentes. Sin esto la app arranca sin base de datos.
# Estos SÍ son fatales a propósito: son locales y rápidos, no dependen de la red,
# y si fallan es por configuración inválida — algo que ningún reintento arregla.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Las migraciones van en segundo plano y CON reintentos, nunca antes del exec.
#
# Railway arranca la app y MySQL en paralelo: en el primer despliegue la base
# suele tardar en aceptar conexiones. Con esto delante del exec y `set -e`, ese
# fallo transitorio mataba el contenedor antes de que Apache llegara a escuchar,
# así que /up no respondía y el healthcheck tumbaba el despliegue. Además, 56
# migraciones sobre la red pueden agotar por sí solas la ventana del healthcheck
# si Apache espera a que terminen.
#
# Ahora Apache abre el puerto de inmediato y las migraciones convergen aparte. Si
# agotan los reintentos, el contenedor sigue en pie: el error queda en los logs de
# Railway, que es la única forma de leerlo (no hay acceso al sistema de archivos).
migrar() {
    intento=1
    until php artisan migrate --force --no-interaction; do
        if [ "$intento" -ge 10 ]; then
            echo "[entrypoint] ERROR: migraciones fallidas tras $intento intentos." >&2
            echo "[entrypoint] La app sigue arriba; revise DB_URL y el servicio MySQL." >&2
            return 1
        fi
        echo "[entrypoint] Migración fallida (intento $intento/10); reintento en 6s..." >&2
        intento=$((intento + 1))
        sleep 6
    done
    echo "[entrypoint] Migraciones aplicadas."
}

migrar &

exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
