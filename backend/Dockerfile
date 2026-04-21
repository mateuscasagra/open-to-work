# syntax=docker/dockerfile:1
FROM php:8.4-fpm-alpine AS base

RUN apk add --no-cache \
        bash curl git icu-dev libzip-dev oniguruma-dev postgresql-dev sqlite-dev \
        linux-headers autoconf g++ make $PHPIZE_DEPS \
    && docker-php-ext-install bcmath exif intl opcache pcntl pdo pdo_pgsql pdo_sqlite zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del autoconf g++ make $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Dependencies cache layer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist

# App
COPY . .
RUN composer dump-autoload --optimize \
    && php artisan storage:link || true

RUN chown -R www-data:www-data storage bootstrap/cache

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
