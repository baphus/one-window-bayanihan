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
# Once the database is on a private endpoint, an external CI runner cannot reach
# it, so migrations have to run from inside the platform. This is that path.
#
# It is safe here for two reasons that were NOT true of the original version:
#
#  1. Failure stops the boot. The old code ended in `|| true`, so a failed
#     migration served traffic on a broken schema. Now the container refuses to
#     start, the platform keeps the previous deployment active, and the release
#     fails visibly instead of half-applying.
#
#  2. --isolated takes an atomic cache lock, so only one container runs the
#     migrations even when several start at once. Without it, scaling past a
#     single node means concurrent `migrate` runs against one database. The lock
#     needs a cache store that implements LockProvider — the `database` store
#     does, which is what this deployment uses.
#
# Migrations must still be backward compatible with the version being replaced:
# during a rolling deployment the previous container keeps serving while the new
# one migrates. Expand first, contract in a later release — never both at once.
if [ "${RUN_MIGRATIONS}" = "true" ]; then
    echo "[ENTRYPOINT] Running migrations..."
    if ! php artisan migrate --force --isolated --no-interaction; then
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
