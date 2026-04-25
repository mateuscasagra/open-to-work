# syntax=docker/dockerfile:1
FROM dunglas/frankenphp:1-php8.4-alpine AS base

RUN install-php-extensions \
        bcmath \
        exif \
        intl \
        opcache \
        pcntl \
        pdo_pgsql \
        pdo_sqlite \
        redis \
        zip \
        gd

RUN { \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.interned_strings_buffer=16'; \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.revalidate_freq=0'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.enable_cli=1'; \
        echo 'realpath_cache_size=4096K'; \
        echo 'realpath_cache_ttl=600'; \
    } > /usr/local/etc/php/conf.d/opcache-prod.ini

RUN { \
        echo 'upload_max_filesize=20M'; \
        echo 'post_max_size=20M'; \
        echo 'memory_limit=256M'; \
    } > /usr/local/etc/php/conf.d/uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative \
    && php artisan storage:link || true \
    && chown -R www-data:www-data storage bootstrap/cache

ENV SERVER_NAME=:8000
EXPOSE 8000
