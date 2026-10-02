# Entorno para probar la tienda en tu computador (no se usa en el servidor).
FROM php:8.3-cli
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libpq-dev libzip-dev libicu-dev libpng-dev libjpeg62-turbo-dev libwebp-dev libfreetype6-dev postgresql-client \
 && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
 && docker-php-ext-install -j$(nproc) pdo_pgsql pgsql zip intl gd bcmath \
 && docker-php-ext-enable opcache \
 && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY php.ini /usr/local/etc/php/conf.d/app.ini
WORKDIR /app
