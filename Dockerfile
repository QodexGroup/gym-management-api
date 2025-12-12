# Use an official PHP image with PHP-FPM (FastCGI Process Manager) for web serving
FROM php:8.2-fpm-alpine

# Update package index and install system dependencies
RUN apk update && apk add --no-cache \
    nginx \
    curl \
    git \
    mysql-client \
    zip \
    unzip \
    icu-dev \
    libxml2-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    autoconf \
    g++ \
    make \
    linux-headers \
    gettext \
    $PHPIZE_DEPS

# Configure and install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        opcache \
        bcmath \
        exif \
        pcntl \
        gd \
        intl \
        soap \
    && docker-php-ext-enable opcache

# Clean up build dependencies
RUN apk del --no-cache $PHPIZE_DEPS autoconf g++ make linux-headers \
    && rm -rf /var/cache/apk/* /tmp/*

# Set working directory inside the container
WORKDIR /app

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy composer.json and composer.lock
COPY composer.json composer.lock /app/

# Install PHP dependencies from composer.json/lock
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copy the rest of the application code into the container
COPY . /app

# Configure Nginx for Laravel:
COPY .docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY .docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Configure PHP-FPM for non-root container:
COPY .docker/php-fpm/www.conf /usr/local/etc/php-fpm.d/www.conf
COPY .docker/php-fpm/zz-docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf

# === CRITICAL FIX: Set Permissions using numeric ID (UID 82 for www-data) ===
# This ensures the command runs successfully during the build phase.
# We run `chown` as root (default build user) but target UID 82 (www-data).
# The user www-data belongs to the group www-data (GID 82)
RUN chown -R 82:82 /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache \
    && mkdir -p /var/log/nginx /var/cache/nginx \
               /tmp/client_body /tmp/proxy /tmp/fastcgi /tmp/uwsgi /tmp/scgi \
    && chown -R 82:82 /var/log/nginx /var/cache/nginx /tmp \
    && chmod -R 755 /var/log/nginx /var/cache/nginx \
    && chmod 1777 /tmp

# Copy startup script
COPY .docker/startup.sh /usr/local/bin/startup.sh
RUN chmod +x /usr/local/bin/startup.sh

# === CRITICAL FIX: Switch user before CMD ===
# This ensures the startup script and all running services run as the non-root user
# that now owns the storage directory.
USER www-data

# Expose port 8080 (Cloud Run default PORT)
EXPOSE 8080

# Keep container alive with Nginx in foreground
CMD ["/usr/local/bin/startup.sh"]
