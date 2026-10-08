# syntax=docker/dockerfile:1.7

FROM node:22-alpine AS node-deps
WORKDIR /var/www/html
COPY package.json package-lock.json ./
RUN npm ci
COPY public ./public
COPY resources ./resources
COPY tests/JavaScript ./tests/JavaScript
COPY vite.config.js ./

FROM node-deps AS js-test
CMD ["sh", "-lc", "node --test tests/JavaScript/*.test.js"]

FROM node-deps AS frontend
RUN npm run build

FROM php:8.5-fpm-alpine AS php-base
WORKDIR /var/www/html

RUN set -eux; \
    apk add --no-cache freetype icu-libs libjpeg-turbo libpng libpq libwebp libzip; \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        freetype-dev \
        icu-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        postgresql-dev; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" \
        bcmath \
        gd \
        intl \
        pcntl \
        pdo_pgsql \
        zip; \
    apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

FROM php-base AS php-test
COPY . .
RUN cp .env.example .env; \
    composer install --no-interaction --prefer-dist --no-progress
CMD ["php", "artisan", "test", "--compact"]

FROM php-base AS vendor
COPY . .
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-progress

FROM php-base AS runtime
COPY . .
COPY --from=vendor /var/www/html/vendor ./vendor
COPY --from=vendor /var/www/html/bootstrap/cache ./bootstrap/cache
COPY --from=vendor /var/www/html/public ./public
COPY --from=frontend /var/www/html/public/build ./public/build

RUN rm -f public/hot; \
    mkdir -p \
        storage/app/public \
        storage/framework/cache/data \
        storage/framework/sessions \
        storage/framework/views \
        storage/logs \
        bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache

USER www-data
EXPOSE 9000
CMD ["php-fpm", "-F"]

FROM nginxinc/nginx-unprivileged:1.29-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=runtime --chown=nginx:nginx /var/www/html/public /var/www/html/public
EXPOSE 8080
