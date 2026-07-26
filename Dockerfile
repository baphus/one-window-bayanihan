# ============================================================================
# Stage 1: Frontend build — Node.js
# Builds Inertia SPA assets via Vite
# ============================================================================
FROM node:22-bookworm AS node-build

WORKDIR /build

# Copy dependency manifests for layer caching
COPY package.json package-lock.json ./
RUN npm ci --ignore-scripts --no-audit --no-fund

# Copy application source and build
COPY . .
ENV VITE_APP_NAME="One Window Bayanihan"
RUN npm run build

# ============================================================================
# Stage 2: PHP runtime — Nginx + PHP-FPM (single container for PaaS)
# Runs Laravel application behind Nginx reverse proxy
# ============================================================================
FROM php:8.4-fpm AS app

LABEL org.opencontainers.image.source="https://github.com/dmw-region7/one-window-bayanihan"
LABEL org.opencontainers.image.description="One Window Bayanihan - DMW Region VII Case Management"
LABEL org.opencontainers.image.licenses="MIT"

# ── System dependencies (includes Nginx + Supervisor) ──
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libzip-dev \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    libicu-dev \
    nginx \
    supervisor \
    && docker-php-ext-install -j$(nproc) \
    pdo \
    pdo_pgsql \
    sockets \
    bcmath \
    gd \
    intl \
    opcache \
    pcntl \
    exif \
    mbstring \
    zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# ── Composer ──
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── Working directory ──
WORKDIR /var/www/html

# ── Copy application files ──
# .dockerignore controls what gets excluded
COPY . .

# ── Copy built frontend assets ──
COPY --from=node-build /build/public/build public/build

# ── Install PHP dependencies (production only) ──
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-source \
    && rm -rf /root/.composer/cache

RUN composer audit --no-interaction 2>&1 | tee /tmp/composer-audit.log

RUN rm -rf /root/.composer

# ── PHP configuration ──
COPY docker/php/php.ini $PHP_INI_DIR/conf.d/custom.ini

# ── Nginx configuration ──
COPY docker/nginx/conf.d/default.conf /etc/nginx/sites-available/default
RUN rm -f /etc/nginx/sites-enabled/default \
    && ln -s /etc/nginx/sites-available/default /etc/nginx/sites-enabled/default

# ── Supervisor configuration ──
COPY docker/supervisord.conf /etc/supervisor/conf.d/app.conf

# ── Entrypoint ──
COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# ── Cleanup build-only files ──
# resources/js/data must survive: AddressNameResolver reads the PSGC lookup
# table from resource_path('js/data/philippine-addresses.ts') at runtime to turn
# stored codes into place names. Deleting all of resources/js made every
# resolve() fall through to returning the raw code, so case managers saw
# "0730600041, 0730600000, ..." instead of "Lahug, City of Cebu, Cebu, Region VII".
# The failure was silent because resolve() falls back to its input.
RUN find resources/js -mindepth 1 -maxdepth 1 ! -name data -exec rm -rf {} + \
    && rm -rf node_modules resources/css tailwind.config.js vite.config.js postcss.config.ts tsconfig.json vitest.config.ts playwright.config.ts

# ── HOME for non-root processes ──
# libpq probes for an OPTIONAL client certificate at $HOME/.postgresql/postgresql.crt
# on every connection. supervisord runs as root, so its `user=www-data` children
# (queue-worker, scheduler) inherit HOME=/root, which is mode 700 — the traversal
# returns EACCES and libpq treats that as a fatal connection error:
#   SQLSTATE[08006] ... could not open certificate file ".../postgresql.crt": Permission denied
# php-fpm is unaffected because it sets HOME from the pool user's passwd entry.
# Pointing HOME at a world-readable directory makes the optional lookup miss cleanly.
ENV HOME=/tmp

# ── Storage directories with proper permissions ──
RUN mkdir -p /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    /var/log/supervisor \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# ── Nginx needs write access to temp dirs and log ──
RUN chown -R www-data:www-data /var/log/nginx /var/lib/nginx /run

HEALTHCHECK --interval=30s --timeout=3s --start-period=10s --retries=3 \
    CMD curl -f http://127.0.0.1:8080/up || exit 1

EXPOSE 8080

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/app.conf"]
