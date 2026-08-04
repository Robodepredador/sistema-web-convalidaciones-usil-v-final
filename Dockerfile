# Imagen para Railway: Apache + PHP 8.2 + worker de colas en UN solo contenedor.
#
# Convive con docker/php/Dockerfile (Nginx + PHP-FPM + worker en contenedores
# separados, para un servidor propio con docker-compose.prod.yml). Railway
# obliga a esta forma por tres razones:
#
#   1. Un solo contenedor. Los volúmenes de Railway se montan en UN servicio, y
#      el worker necesita leer el Excel que subió la web: ImportarMallaExcel usa
#      Storage::path(), que solo funciona sobre disco local. Separar web y
#      worker en dos servicios rompería la carga masiva (RF-11).
#   2. Compila el frontend. `@vite` en resources/views/app.blade.php aborta la
#      petición si falta public/build, y ese directorio no se versiona.
#   3. Los cachés se generan al ARRANCAR, no aquí. En build las variables de
#      Railway todavía no existen: `config:cache` congelaría una configuración
#      vacía y la app arrancaría sin base de datos.

FROM php:8.2-apache

# Extensiones PHP. Mismo juego que docker/php/Dockerfile más `redis`: la
# configuración usa REDIS_CLIENT=phpredis por defecto y ni la extensión ni
# predis estaban instaladas, así que QUEUE_CONNECTION=redis fallaba al arrancar.
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip supervisor \
        libpng-dev libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip \
    && pecl install redis && docker-php-ext-enable redis \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get purge -y --auto-remove && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache sirve public/ y respeta el .htaccess que trae Laravel.
RUN a2enmod rewrite \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\n    AllowOverride All\n    Require all granted\n</Directory>\n' \
        > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel

# El límite por defecto de PHP (2M) rechazaba los Excel de hasta 10MB que
# aceptan ImportarMallaRequest y MallaImportController (max:10240).
RUN printf 'upload_max_filesize=12M\npost_max_size=12M\nmemory_limit=512M\n' \
        > /usr/local/etc/php/conf.d/convalidaciones.ini

WORKDIR /var/www/html
COPY . .

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && npm ci && npm run build && rm -rf node_modules

COPY docker/railway/supervisord.conf /etc/supervisor/conf.d/convalidaciones.conf
COPY docker/railway/entrypoint.sh /usr/local/bin/entrypoint

RUN chmod +x /usr/local/bin/entrypoint \
    && chown -R www-data:www-data storage bootstrap/cache

CMD ["/usr/local/bin/entrypoint"]
