FROM php:8.3-fpm AS php

# Update package list with bypass for expired release files
RUN apt-get update -o Acquire::Check-Valid-Until=false -o Acquire::Check-Date=false -y

# Install necessary packages
RUN apt-get install -y libpq-dev libzip-dev libicu-dev libpng-dev libjpeg-dev libwebp-dev libgif-dev libfreetype6-dev libcurl4-gnutls-dev libtiff5-dev ffmpeg nginx curl zip unzip gnupg supervisor

# Install FreeTDS and ODBC packages
RUN apt-get install -y freetds-common freetds-bin unixodbc freetds-dev

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp
RUN docker-php-ext-install -j$(nproc) intl zip pdo pdo_pgsql curl gd opcache exif

# Install PDO_DBLIB for connecting to SQL Server using FreeTDS
RUN apt-get install -y libsybdb5
RUN docker-php-ext-install pdo_dblib

WORKDIR /app

# Setup PHP-FPM.
COPY docker/php/php.ini $PHP_INI_DIR/
COPY docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/php/conf.d/opcache.ini $PHP_INI_DIR/conf.d/opcache.ini

RUN addgroup --system --gid 1000 eyvangroup
RUN adduser --system --ingroup eyvangroup --uid 1000 eyvanuser

# Setup nginx.
COPY docker/nginx/nginx.conf docker/nginx/fastcgi_params docker/nginx/fastcgi_fpm docker/nginx/gzip_params /etc/nginx/
RUN mkdir -p /var/lib/nginx/tmp /var/log/nginx
RUN /usr/sbin/nginx -t -c /etc/nginx/nginx.conf

# setup nginx user permissions
RUN chown -R eyvanuser:eyvangroup /var/lib/nginx /var/log/nginx
RUN chown -R eyvanuser:eyvangroup /usr/local/etc/php-fpm.d

# Setup supervisor.
COPY docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf

# Copy application sources into the container.
COPY --chown=eyvanuser:eyvangroup . .
RUN chown -R eyvanuser:eyvangroup /app
RUN chmod -R 755 /app
RUN chmod +w /app/public
RUN chown -R eyvanuser:eyvangroup /var /run

# disable root user
RUN passwd -l root
RUN usermod -s /usr/sbin/nologin root

USER eyvanuser
COPY --from=composer:2.7.6 /usr/bin/composer /usr/bin/composer

ENTRYPOINT ["docker/entrypoint.sh"]

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
