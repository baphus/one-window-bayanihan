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
# Ensure framework directories are writable (Supabase Storage handles file storage).
chmod -R 755 storage bootstrap/cache 2>/dev/null || true
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# ── Cache: bootstrap Laravel once ──
if [ "${APP_ENV}" != "local" ] && [ "${APP_ENV}" != "testing" ]; then
    php artisan config:cache --no-interaction || true
    php artisan route:cache --no-interaction || true
    php artisan view:cache --no-interaction || true
    php artisan event:cache --no-interaction || true
    echo "[ENTRYPOINT] Configuration cached for production"
fi

# ── Run migrations if enabled ──
if [ "${RUN_MIGRATIONS}" = "true" ]; then
    echo "[ENTRYPOINT] Running migrations..."
    php artisan migrate --force --no-interaction || true
fi

# ── Chatbot: build the FTS5 retrieval index (fails loudly if FTS5 missing) ──
if [ "${AI_CHATBOT_ENABLED}" = "true" ]; then
    echo "[ENTRYPOINT] Rebuilding chatbot retrieval index..."
    php artisan chatbot:index --no-interaction || echo "[ENTRYPOINT] WARNING: chatbot:index failed — the bot will rebuild lazily on first query" >&2
fi

# ── Execute the main command (supervisord, queue:listen, schedule:work, etc.) ──
echo "[ENTRYPOINT] Starting: $@"
exec "$@"
