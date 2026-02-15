#!/bin/sh
set -e

echo "========================================="
echo "Starting application startup sequence..."
echo "========================================="

# Clear caches first
echo "Clearing application caches..."
php artisan config:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true

# Optimize application (quick operations)
echo "Optimizing application..."
php artisan route:cache 2>/dev/null || true
php artisan view:cache 2>/dev/null || true
echo "✓ Application optimization completed!"

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

# Run migrations in background after server starts (non-blocking)
(
    echo "Waiting for database connection before running migrations..."
    MAX_RETRIES=30
    RETRY_COUNT=0
    until php artisan db:show > /dev/null 2>&1; do
        RETRY_COUNT=$((RETRY_COUNT + 1))
        if [ $RETRY_COUNT -ge $MAX_RETRIES ]; then
            echo "ERROR: Database connection timeout after $MAX_RETRIES attempts"
            echo "WARNING: Skipping migrations - they will need to be run manually"
            exit 0
        fi
        echo "Database is unavailable - sleeping (attempt $RETRY_COUNT/$MAX_RETRIES)"
        sleep 2
    done

    if php artisan db:show > /dev/null 2>&1; then
        echo "✓ Database is ready!"
        echo "Running database migrations in background..."
        if php artisan migrate --force; then
            echo "✓ Migrations completed successfully!"
        else
            echo "ERROR: Migrations failed! Check logs for details."
            echo "WARNING: Application will continue running, but migrations need attention"
        fi
    else
        echo "WARNING: Could not verify database connection, skipping migrations"
    fi
) &

# Start Queue Worker in background
(
    echo "Starting queue worker..."
    php artisan queue:listen --sleep=3 --tries=3
) &

# Start Scheduler in background
(
    echo "Starting scheduler..."
    php artisan schedule:work
) &

# Start Nginx in foreground (this keeps container alive and listening)
# This must be the last command and must not exit
exec nginx -g 'daemon off;'
