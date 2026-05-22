#!/bin/sh
set -e

echo "========================================="
echo "Starting Laravel worker service..."
echo "Timestamp: $(date)"
echo "========================================="

cd /app

echo "Clearing and caching configuration..."
php artisan config:clear 2>/dev/null || true
php artisan config:cache 2>/dev/null || true

echo "Waiting for database connection..."
MAX_RETRIES=30
RETRY_COUNT=0
until php artisan db:show > /dev/null 2>&1; do
    RETRY_COUNT=$((RETRY_COUNT + 1))
    if [ $RETRY_COUNT -ge $MAX_RETRIES ]; then
        echo "ERROR: Database connection timeout after $MAX_RETRIES attempts"
        exit 1
    fi
    echo "Database is unavailable - sleeping (attempt $RETRY_COUNT/$MAX_RETRIES)"
    sleep 2
done
echo "Database is ready."

run_queue_worker() {
    echo "[QUEUE] Starting queue worker..."
    while true; do
        php artisan queue:work \
            --sleep=3 \
            --tries=3 \
            --max-time=3600 \
            --verbose \
            2>&1 | while IFS= read -r line; do
                echo "[QUEUE] $line"
            done

        EXIT_CODE=$?
        echo "[QUEUE] Queue worker exited with code $EXIT_CODE. Restarting in 5 seconds..."
        sleep 5
    done
}

run_schedule_worker() {
    echo "[SCHEDULE] Starting schedule worker..."
    while true; do
        php artisan schedule:work \
            --verbose \
            2>&1 | while IFS= read -r line; do
                echo "[SCHEDULE] $line"
            done

        EXIT_CODE=$?
        echo "[SCHEDULE] Schedule worker exited with code $EXIT_CODE. Restarting in 5 seconds..."
        sleep 5
    done
}

run_queue_worker &
QUEUE_PID=$!

run_schedule_worker &
SCHEDULE_PID=$!

echo "[INFO] Queue worker PID: $QUEUE_PID"
echo "[INFO] Schedule worker PID: $SCHEDULE_PID"

cleanup() {
    echo "[INFO] Shutting down workers..."
    kill $QUEUE_PID 2>/dev/null || true
    kill $SCHEDULE_PID 2>/dev/null || true
    wait
    echo "[INFO] Workers stopped"
    exit 0
}

trap cleanup SIGTERM SIGINT

echo "Starting health server on port 8080..."
exec php -S 0.0.0.0:8080 -t public
