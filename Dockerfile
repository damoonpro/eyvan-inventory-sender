FROM php:8.3-fpm-alpine as php

RUN apk add --no-cache unzip libpq-dev gnutls-dev autoconf build-base \
    curl-dev nginx supervisor shadow bash

# Install FreeTDS and ODBC packages for Alpine
RUN apk add --no-cache freetds freetds-dev unixodbc-dev

RUN docker-php-ext-install pdo pdo_pgsql
RUN pecl install pcov && docker-php-ext-enable pcov

# Install PDO_DBLIB for connecting to SQL Server using FreeTDS
RUN docker-php-ext-configure pdo_dblib --with-libdir=/usr/lib
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
