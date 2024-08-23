FROM php:8.3-fpm-alpine AS php

RUN apk add --no-cache unzip libpq-dev gnutls-dev autoconf build-base \
    curl-dev nginx supervisor shadow bash unixodbc-dev unixodbc gnupg

# Install Microsoft ODBC Driver for SQL Server (msodbcsql17)
RUN set -eux; \
    architecture="unsupported"; \
    case $(uname -m) in \
        x86_64) architecture="amd64" ;; \
        arm64) architecture="arm64" ;; \
        *) echo "Alpine architecture $(uname -m) is not currently supported." && exit 1 ;; \
    esac; \
    curl -O https://download.microsoft.com/download/7/6/d/76de322a-d860-4894-9945-f0cc5d6a45f8/msodbcsql18_18.4.1.1-1_${architecture}.apk; \
    curl -O https://download.microsoft.com/download/7/6/d/76de322a-d860-4894-9945-f0cc5d6a45f8/mssql-tools18_18.4.1.1-1_${architecture}.apk; \
    curl -O https://download.microsoft.com/download/7/6/d/76de322a-d860-4894-9945-f0cc5d6a45f8/msodbcsql18_18.4.1.1-1_${architecture}.sig; \
    curl -O https://download.microsoft.com/download/7/6/d/76de322a-d860-4894-9945-f0cc5d6a45f8/mssql-tools18_18.4.1.1-1_${architecture}.sig; \
    curl https://packages.microsoft.com/keys/microsoft.asc | gpg --import -; \
    gpg --verify msodbcsql18_18.4.1.1-1_${architecture}.sig msodbcsql18_18.4.1.1-1_${architecture}.apk; \
    gpg --verify mssql-tools18_18.4.1.1-1_${architecture}.sig mssql-tools18_18.4.1.1-1_${architecture}.apk; \
    apk add --allow-untrusted msodbcsql18_18.4.1.1-1_${architecture}.apk; \
    apk add --allow-untrusted mssql-tools18_18.4.1.1-1_${architecture}.apk

# Install the pdo_sqlsrv and sqlsrv PHP extensions
RUN pecl install sqlsrv pdo_sqlsrv && \
    docker-php-ext-enable sqlsrv pdo_sqlsrv

RUN docker-php-ext-install pdo pdo_pgsql
RUN pecl install pcov && docker-php-ext-enable pcov

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
