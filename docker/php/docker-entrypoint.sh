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

# ── Required configuration ──
# None of the cache commands below need APP_KEY or database credentials, so a
# container missing them starts cleanly and then fails on the first request that
# touches an encrypted column, a session, or the database. Refusing to start is
# the better failure: it surfaces at deploy time, on the deployment that caused
# it, instead of as intermittent 500s later.
#
# APP_KEY specifically must be the SAME value the data was encrypted with —
# EncryptedString and EncryptedDate make a wrong key indistinguishable from
# corruption, so an absent key must never be silently replaced by a generated one.
if [ "${APP_ENV}" != "local" ] && [ "${APP_ENV}" != "testing" ]; then
    missing=""
    for required in APP_KEY DB_HOST DB_DATABASE DB_USERNAME DB_PASSWORD; do
        eval "value=\${${required}:-}"
        if [ -z "${value}" ]; then
            missing="${missing} ${required}"
        fi
    done

    if [ -n "${missing}" ]; then
        echo "[ENTRYPOINT] FATAL: missing required environment:${missing}" >&2
        echo "[ENTRYPOINT] refusing to start — see deploy/lightsail/README.md" >&2
        exit 1
    fi
fi

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
