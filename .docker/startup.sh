#!/bin/sh
set -e

echo "========================================="
echo "Starting application startup sequence..."
echo "========================================="

# Quick cache clear (non-blocking)
echo "Clearing Laravel caches..."
php artisan config:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true
php artisan view:clear 2>/dev/null || true

# Start PHP-FPM first so Cloud Run knows the container is alive
echo "Starting PHP-FPM..."
php-fpm -D
sleep 2

# Test Nginx configuration
echo "Testing Nginx configuration..."
nginx -t
echo "✓ Nginx configuration valid"

# Start Nginx in background temporarily
echo "Starting Nginx (temporary)..."
nginx

# Wait for database connection
echo "Waiting for database connection..."
MAX_RETRIES=20
RETRY_COUNT=0
until php artisan db:show > /dev/null 2>&1; do
    RETRY_COUNT=$((RETRY_COUNT + 1))
    if [ $RETRY_COUNT -ge $MAX_RETRIES ]; then
        echo "ERROR: Database connection timeout after $MAX_RETRIES attempts"
        echo "Continuing anyway - migrations may fail..."
        break
    fi
    echo "Database is unavailable - sleeping for 2 seconds (attempt $RETRY_COUNT/$MAX_RETRIES)"
    sleep 2
done

if php artisan db:show > /dev/null 2>&1; then
    echo "✓ Database is ready!"

    # Run migrations (synchronously so we can see errors)
    echo "Running database migrations..."
    php artisan migrate --force
    if [ $? -eq 0 ]; then
        echo "✓ Migrations completed successfully!"
    else
        echo "ERROR: Migrations failed!"
    fi
else
    echo "WARNING: Could not verify database connection, skipping migrations"
fi

# Optimize application (but don't cache config to ensure CORS updates work)
echo "Optimizing application..."
# Don't cache config - let it read from file to ensure CORS config is fresh
php artisan route:cache 2>/dev/null || true
php artisan view:cache 2>/dev/null || true
echo "✓ Application optimized!"

# Stop background nginx and start in foreground
echo "Stopping temporary Nginx..."
nginx -s stop 2>/dev/null || true
sleep 1

# Keep container alive with Nginx in foreground
echo "========================================="
echo "Starting Nginx server..."
echo "Application is ready to serve requests!"
echo "========================================="
exec nginx -g 'daemon off;'

