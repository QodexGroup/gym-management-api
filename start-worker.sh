#!/bin/sh
set -e

echo "=========================================="
echo "Starting Laravel Worker Service"
echo "Timestamp: $(date)"
echo "=========================================="

# Change to app directory
cd /app

# Function to run queue worker with auto-restart
run_queue_worker() {
    echo "[QUEUE] Starting queue worker..."
    while true; do
        php artisan queue:work \
            --sleep=3 \
            --tries=3 \
            --timeout=300 \
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

# Function to run schedule worker with auto-restart
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

# Start both processes in background
run_queue_worker &
QUEUE_PID=$!

run_schedule_worker &
SCHEDULE_PID=$!

echo "[INFO] Queue worker PID: $QUEUE_PID"
echo "[INFO] Schedule worker PID: $SCHEDULE_PID"
echo "[INFO] Both workers started successfully!"
echo "=========================================="

# Function to handle shutdown
cleanup() {
    echo "[INFO] Shutting down workers..."
    kill $QUEUE_PID 2>/dev/null || true
    kill $SCHEDULE_PID 2>/dev/null || true
    wait
    echo "[INFO] Workers stopped"
    exit 0
}

# Trap signals
trap cleanup SIGTERM SIGINT

# Wait for both processes (this keeps the container alive)
wait
