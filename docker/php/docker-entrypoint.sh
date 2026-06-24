#!/bin/sh
set -e

# ── Laravel Docker Entrypoint ──
# Runs at container start to ensure the app is ready to serve.

# If artisan isn't present, bail (wrong workdir).
if [ ! -f "artisan" ]; then
    echo "[ENTRYPOINT] ERROR: artisan not found in $(pwd)" >&2
    exit 1
fi

# ── Storage & cache: set permissions ──
# Ensure framework directories are writable (Cloudinary handles file storage).
chmod -R 775 storage bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# ── Cache: bootstrap Laravel once ──
if [ "${APP_ENV}" != "local" ] && [ "${APP_ENV}" != "testing" ]; then
    php artisan config:cache --no-interaction 2>/dev/null || true
    php artisan route:cache --no-interaction 2>/dev/null || true
    php artisan view:cache --no-interaction 2>/dev/null || true
    php artisan event:cache --no-interaction 2>/dev/null || true
    echo "[ENTRYPOINT] Configuration cached for ${APP_ENV}"
fi

# ── Run migrations (optional — controlled by RUN_MIGRATIONS env) ──
if [ "${RUN_MIGRATIONS}" = "true" ]; then
    php artisan migrate --force --no-interaction 2>/dev/null || true
    echo "[ENTRYPOINT] Migrations executed"
fi

# ── Execute the main command (php-fpm, queue:listen, schedule:work, etc.) ──
echo "[ENTRYPOINT] Starting: $@"
exec "$@"
