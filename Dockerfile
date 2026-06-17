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
    libzip-dev \
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
    && docker-php-ext-configure zip \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        opcache \
        bcmath \
        exif \
        pcntl \
        gd \
        intl \
        soap \
        zip \
    && docker-php-ext-enable opcache

# Install New Relic PHP agent (APM + Errors Inbox). Best-effort: failures never break the build.
# Version is auto-detected; curl uses --http1.1 + --retry to survive HTTP/2 resets (curl error 92).
# mkdir /etc/init.d avoids the Alpine 3.20+ install abort; agent log forwarding is off because
# App\Logging\NewRelicLogHandler already ships logs over HTTP.
RUN ( \
        NRFILE=$(curl -fsSL --http1.1 --retry 5 --retry-delay 2 --retry-all-errors "https://download.newrelic.com/php_agent/release/" | grep -oE 'newrelic-php5-[0-9.]+-linux-musl\.tar\.gz' | sort -V | tail -1) \
        && echo "Latest New Relic musl agent: ${NRFILE}" \
        && curl -fsSL --http1.1 --retry 5 --retry-delay 2 --retry-all-errors "https://download.newrelic.com/php_agent/release/${NRFILE}" -o /tmp/newrelic.tar.gz \
        && mkdir -p /tmp/newrelic && tar -C /tmp/newrelic -zxf /tmp/newrelic.tar.gz --strip-components=1 \
        && cd /tmp/newrelic \
        && mkdir -p /etc/init.d \
        && NR_INSTALL_USE_CP_NOT_LN=1 NR_INSTALL_SILENT=1 ./newrelic-install install \
        && rm -f /usr/local/etc/php/conf.d/*newrelic*.ini \
        && printf '%s\n' \
        'extension=newrelic.so' \
        'newrelic.license="${NEW_RELIC_LICENSE_KEY}"' \
        'newrelic.appname="${NEW_RELIC_APP_NAME}"' \
        'newrelic.enabled=${NEW_RELIC_ENABLED}' \
        'newrelic.logfile="/dev/stderr"' \
        'newrelic.daemon.logfile="/dev/stderr"' \
        'newrelic.daemon.address="/tmp/.newrelic.sock"' \
        'newrelic.distributed_tracing_enabled=true' \
        'newrelic.error_collector.enabled=true' \
        'newrelic.error_collector.record_database_errors=true' \
        'newrelic.application_logging.forwarding.enabled=false' \
        > /usr/local/etc/php/conf.d/zz-newrelic-custom.ini \
        && php -m | grep -qi newrelic \
        && echo "New Relic PHP agent installed and enabled" \
    ) || echo "WARNING: New Relic agent install failed - continuing build without APM agent"; \
    rm -rf /tmp/newrelic /tmp/newrelic.tar.gz

# Clean up build dependencies
RUN apk del --no-cache $PHPIZE_DEPS autoconf g++ make linux-headers \
    && rm -rf /var/cache/apk/* /tmp/*

# Set working directory inside the container
WORKDIR /app

# Install Composer by copying the binary from the official image.
# Avoids the getcomposer.org installer, whose PHP stream download intermittently
# fails with "Network unreachable" (IPv6 ENETUNREACH) on the Railway build host.
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Copy composer.json and composer.lock
COPY composer.json composer.lock /app/

# Install PHP dependencies from composer.json/lock.
# Composer uses libcurl (happy-eyeballs IPv4/IPv6 fallback), so it won't get stuck
# on an unroutable IPv6 address the way the old PHP-stream installer did. The retry
# loop rides out transient build-network blips; the build fails only if all 5 fail.
RUN for i in 1 2 3 4 5; do \
        composer install --no-dev --optimize-autoloader --no-scripts && exit 0; \
        echo "composer install failed (attempt $i/5), retrying in 5s..." >&2; \
        sleep 5; \
    done; \
    echo "composer install failed after 5 attempts" >&2; exit 1

# Copy the rest of the application code into the container
COPY . /app

# Composer install ran when only composer.json/lock existed; regenerate autoload now that app/ exists.
RUN composer dump-autoload --optimize --no-dev --no-scripts

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
    && mkdir -p /var/log/nginx /var/cache/nginx /var/lib/nginx/logs \
               /tmp/client_body /tmp/proxy /tmp/fastcgi /tmp/uwsgi /tmp/scgi \
    && chown -R 82:82 /var/log/nginx /var/cache/nginx /var/lib/nginx /tmp \
    && chmod -R 755 /var/log/nginx /var/cache/nginx /var/lib/nginx \
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
