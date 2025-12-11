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

# Copy composer.json and composer.lock to the working directory.
# Doing this before copying the rest of the app allows Docker to cache this layer
# and speed up builds if only app code changes.
COPY composer.json composer.lock /app/

# Set working directory inside the container
WORKDIR /app

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install PHP dependencies from composer.json/lock
# Skip scripts during install since artisan file doesn't exist yet
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copy the rest of the application code into the container
COPY . /app

# Note: Autoloader is already optimized from composer install above
# Package discovery will happen at runtime when the container starts

# Configure Nginx for Laravel:
# You'll create these files in a moment.
COPY .docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY .docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Set proper permissions for Laravel storage and bootstrap cache directories.
# This is crucial for Laravel to function correctly.
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache \
    && chmod -R 775 /app/storage /app/bootstrap/cache

# Copy startup script
COPY .docker/startup.sh /usr/local/bin/startup.sh
RUN chmod +x /usr/local/bin/startup.sh

# Expose port 8080 (Cloud Run default PORT)
# Cloud Run will set the PORT environment variable, and nginx will use it
EXPOSE 8080

# Use startup script that runs migrations before starting services
CMD ["/usr/local/bin/startup.sh"]
