# BAFO API image for hosted demo environments (Railway). One image, three roles:
# ROLE=api (default) | reverb | worker  — see docker/start.sh.
FROM php:8.4-cli-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libicu-dev libzip-dev libpng-dev libjpeg-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql intl zip gd bcmath pcntl opcache \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY . .
RUN composer dump-autoload --optimize \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/framework/testing storage/logs storage/app/private storage/app/public bootstrap/cache \
    && chmod +x docker/start.sh

ENV PHP_CLI_SERVER_WORKERS=4
CMD ["docker/start.sh"]
