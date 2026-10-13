# Production image for the NEXORA QR Laravel + Inertia/React application.
# Multi-architecture base images allow deployment to amd64 or ARM64 VMs.

FROM php:8.3-apache-bookworm AS php-base

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libonig-dev \
        libpng-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath exif gd intl mbstring opcache pcntl pdo_mysql zip \
    && a2enmod headers rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

FROM php-base AS php-dependencies
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --no-scripts \
    --optimize-autoloader

FROM node:22-bookworm-slim AS frontend-build
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY . .
RUN npm run build

FROM php-base AS runtime
WORKDIR /var/www/html

COPY . .
COPY --from=php-dependencies /app/vendor ./vendor
COPY --from=frontend-build /app/public/build ./public/build

RUN mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache \
    && rm -f bootstrap/cache/*.php \
    && php artisan package:discover --ansi \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf '%s\n' \
        '<Directory /var/www/html/public>' \
        '    Options FollowSymLinks' \
        '    AllowOverride All' \
        '    Require all granted' \
        '</Directory>' \
        > /etc/apache2/conf-available/laravel-public.conf \
    && a2enconf laravel-public \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

COPY deploy/entrypoint.sh /usr/local/bin/nexora-entrypoint
RUN chmod 0755 /usr/local/bin/nexora-entrypoint

EXPOSE 80
VOLUME ["/var/www/html/storage/app/public"]
ENTRYPOINT ["nexora-entrypoint"]
CMD ["apache2-foreground"]
