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
mkdir -p storage/logs storage/framework/cache/data storage/framework/sessions storage/framework/views
touch storage/logs/laravel.log
chmod -R 775 storage bootstrap/cache 2>/dev/null || true
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# ── Cache: bootstrap Laravel once ──
# These used to end in `|| true`. That masked a real fault: GET and POST /login
# were both named `login`, so `route:cache` aborted with "Unable to prepare route
# [login] for serialization" on every boot and the app silently ran without
# cached routes. A container that cannot cache its own config or routes is
# misconfigured and must not go on to serve traffic.
if [ "${APP_ENV}" != "local" ] && [ "${APP_ENV}" != "testing" ]; then
    for step in config route view event; do
        if ! php artisan "${step}:cache" --no-interaction; then
            echo "[ENTRYPOINT] FATAL: ${step}:cache failed — refusing to start" >&2
            exit 1
        fi
    done
    echo "[ENTRYPOINT] Configuration cached for production"
fi

# ── Run migrations if enabled ──
# Default is RUN_MIGRATIONS=false: migrations are a deliberate release step run
# before the new image is deployed, because a schema change must land before the
# code that depends on it. When this path IS used, a failure must stop the boot —
# it previously ended in `|| true`, which served traffic on a broken schema.
if [ "${RUN_MIGRATIONS}" = "true" ]; then
    echo "[ENTRYPOINT] Running migrations..."
    if ! php artisan migrate --force --no-interaction; then
        echo "[ENTRYPOINT] FATAL: migrations failed — refusing to start" >&2
        exit 1
    fi
fi

# ── Chatbot: build the FTS5 retrieval index (fails loudly if FTS5 missing) ──
if [ "${AI_CHATBOT_ENABLED}" = "true" ]; then
    echo "[ENTRYPOINT] Rebuilding chatbot retrieval index..."
    php artisan chatbot:index --no-interaction || echo "[ENTRYPOINT] WARNING: chatbot:index failed — the bot will rebuild lazily on first query" >&2
fi

# ── Execute the main command (supervisord, queue:listen, schedule:work, etc.) ──
echo "[ENTRYPOINT] Starting: $@"
exec "$@"
