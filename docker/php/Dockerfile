FROM node:22-alpine AS css
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY assets/scss ./assets/scss
RUN npm run build:css

FROM composer:2 AS dependencies
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts

FROM php:8.3-fpm-alpine AS runtime
RUN docker-php-ext-install pdo_mysql
WORKDIR /var/www/html
COPY --from=dependencies /app/vendor ./vendor
COPY config ./config
COPY templates ./templates
COPY public ./public
COPY --from=css /build/public/assets/css/main.css ./public/assets/css/main.css
RUN mkdir -p var/smarty/compile && chown -R www-data:www-data var

# Isolated preview image; replace the front controller with the existing app in production.
FROM runtime AS preview
COPY tools/preview.php tools/preview.php
COPY tools/fixtures tools/fixtures
COPY docker/php/preview-index.php public/index.php

FROM nginx:1.27-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public
COPY --from=css /build/public/assets/css/main.css /var/www/html/public/assets/css/main.css
