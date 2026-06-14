#!/bin/sh
set -e

# Cache framework config/routes/views for performance (safe: bracket-free path in container).
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true

# Run migrations on boot (idempotent). Disable with RUN_MIGRATIONS=false.
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force || true
fi

exec "$@"
