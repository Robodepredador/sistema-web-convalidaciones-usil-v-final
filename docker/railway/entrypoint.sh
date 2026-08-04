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
php artisan storage:link --force

# Cachés con las variables ya presentes. Sin esto la app arranca sin base de datos.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Idempotente: en un redespliegue sin migraciones nuevas no hace nada.
php artisan migrate --force

exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
