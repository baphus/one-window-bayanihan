FROM php:8.2-fpm

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libzip-dev \
    zip \
    unzip \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql sockets

# Raise upload limits so referral document uploads (validated up to 10MB each)
# are not rejected by PHP's small defaults (post_max_size=8M, upload_max_filesize=2M)
# inside the ValidatePostSize middleware before Laravel can run validation.
RUN { \
        echo 'post_max_size = 64M'; \
        echo 'upload_max_filesize = 12M'; \
        echo 'max_file_uploads = 30'; \
        echo 'memory_limit = 256M'; \
        echo 'max_execution_time = 120'; \
        echo 'max_input_time = 120'; \
    } > /usr/local/etc/php/conf.d/zz-uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

CMD ["php-fpm"]
