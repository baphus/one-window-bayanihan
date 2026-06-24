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
RUN npm run build

# ============================================================================
# Stage 2: PHP runtime — PHP-FPM
# Runs Laravel application
# ============================================================================
FROM php:8.3-fpm AS app

# ── System dependencies ──
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
RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && rm -rf /root/.composer/cache

# ── PHP configuration ──
COPY docker/php/php.ini $PHP_INI_DIR/conf.d/custom.ini

# ── Entrypoint ──
COPY docker/php/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# ── Cleanup build-only files ──
RUN rm -rf node_modules resources/js resources/css tailwind.config.js vite.config.js postcss.config.js tsconfig.json vitest.config.ts playwright.config.ts

EXPOSE 9000

ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["php-fpm"]
