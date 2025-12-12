#!/bin/sh
set -e

echo "========================================="
echo "Starting application startup sequence..."
echo "========================================="

# Set PORT environment variable if not set (default to 8080 for Cloud Run)
export PORT=${PORT:-8080}
echo "Using PORT: $PORT"

# Substitute PORT variable in Nginx configuration
echo "Configuring Nginx to listen on port $PORT..."
sed -i "s/\${PORT:-8080}/$PORT/g" /etc/nginx/conf.d/default.conf
echo "✓ Nginx configured to listen on port $PORT"

# Quick cache clear (non-blocking)
echo "Clearing Laravel caches..."
php artisan config:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true

# Start PHP-FPM in background
echo "Starting PHP-FPM..."
php-fpm -D
sleep 1

# Test Nginx configuration
echo "Testing Nginx configuration..."
nginx -t
echo "✓ Nginx configuration valid"

# Start Nginx in foreground FIRST - this makes Cloud Run happy
# Then do database/migrations in background
echo "========================================="
echo "Starting Nginx server on port $PORT..."
echo "Application is ready to serve requests!"
echo "========================================="

# Run migrations in background after Nginx starts
(
    sleep 5
    echo "Running background tasks..."

    # Wait for database connection (reduced retries for background task)
    MAX_RETRIES=10
    RETRY_COUNT=0
    until php artisan db:show > /dev/null 2>&1; do
        RETRY_COUNT=$((RETRY_COUNT + 1))
        if [ $RETRY_COUNT -ge $MAX_RETRIES ]; then
            echo "ERROR: Database connection timeout after $MAX_RETRIES attempts"
            break
        fi
        echo "Database is unavailable - sleeping (attempt $RETRY_COUNT/$MAX_RETRIES)"
        sleep 3
    done

    if php artisan db:show > /dev/null 2>&1; then
        echo "✓ Database is ready!"
        echo "Running database migrations..."
        php artisan migrate --force && echo "✓ Migrations completed!" || echo "ERROR: Migrations failed!"
    else
        echo "WARNING: Could not verify database connection, skipping migrations"
    fi

    # Optimize application
    echo "Optimizing application..."
    php artisan route:cache 2>/dev/null || true
    php artisan view:cache 2>/dev/null || true
    echo "✓ Background tasks completed!"
) &

# Start Nginx in foreground (this keeps container alive and listening)
exec nginx -g 'daemon off;'
