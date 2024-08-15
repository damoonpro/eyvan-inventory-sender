FROM php:8.3-fpm AS php

# Set environment variables
ENV PHP_OPCACHE_ENABLE=1
ENV PHP_OPCACHE_ENABLE_CLI=0
ENV PHP_OPCACHE_VALIDATE_TIMESTAMPS=1
ENV PHP_OPCACHE_REVALIDATE_FREQ=1

# Modify www-data user
RUN usermod -u 1000 www-data

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

# Set working directory
WORKDIR /var/www

# Copy application code with appropriate ownership
COPY --chown=www-data . .

# Copy configuration files
COPY ./docker/php/php.ini /usr/local/etc/php/php.ini
COPY ./docker/php/php-fpm.conf /usr/local/etc/php-fpm.d/www.conf
COPY ./docker/nginx/nginx.conf /etc/nginx/nginx.conf

# Setup supervisor
COPY docker/supervisor/supervisord.conf /etc/supervisor/supervisord.conf

# Copy composer
COPY --from=composer:2.3.4 /usr/bin/composer /usr/bin/composer

# Set permissions
RUN chmod -R 755 /var/www/storage
RUN chmod -R 755 /var/www/bootstrap

# Define entrypoint
ENTRYPOINT [ "docker/entrypoint.sh" ]

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
