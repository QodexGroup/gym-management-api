#!/bin/sh
set -e

echo "========================================="
echo "Starting application startup sequence..."
echo "========================================="

# Start PHP-FPM in background
echo "Starting PHP-FPM..."
php-fpm
sleep 2

# Verify PHP-FPM is running
if [ -f /tmp/php-fpm.pid ]; then
    echo "✓ PHP-FPM started (PID: $(cat /tmp/php-fpm.pid))"
else
    echo "WARNING: PHP-FPM PID file not found, but continuing..."
fi

# Test Nginx configuration
echo "Testing Nginx configuration..."
nginx -t
echo "✓ Nginx configuration valid"

echo "========================================="
echo "Starting Nginx server on port 8080..."
echo "Application is ready to serve requests!"
echo "========================================="

# Run migrations in background after Nginx starts
(
    sleep 5
    echo "Running background tasks..."

    # Clear caches first
    php artisan config:clear 2>/dev/null || true
    php artisan route:clear 2>/dev/null || true
    php artisan view:clear 2>/dev/null || true

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
