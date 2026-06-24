#!/bin/sh
set -e

# ── Nginx Docker Entrypoint ──
# Substitutes environment variables in nginx config at container start

# Replace ${APP_DOMAIN} placeholder in nginx config if the env var is set
if [ -n "${APP_DOMAIN}" ]; then
  sed -i "s/\${APP_DOMAIN}/${APP_DOMAIN}/g" /etc/nginx/conf.d/default.conf
  echo "[ENTRYPOINT] APP_DOMAIN set to ${APP_DOMAIN}"
fi

echo "[ENTRYPOINT] Starting: $@"
exec "$@"
