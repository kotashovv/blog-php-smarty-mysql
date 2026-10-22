FROM node:22-alpine AS css
WORKDIR /build
COPY package.json package-lock.json ./
RUN npm ci
COPY assets/scss ./assets/scss
RUN npm run build:css

FROM composer:2 AS dependencies
WORKDIR /app
COPY composer.json composer.lock ./
COPY src ./src
RUN composer install --no-dev --prefer-dist --no-interaction --no-scripts --classmap-authoritative

FROM php:8.2-fpm-alpine AS app
RUN docker-php-ext-install pdo_mysql \
    && sed -i '/^user =/d; /^group =/d' /usr/local/etc/php-fpm.d/www.conf
WORKDIR /var/www/html
COPY --from=dependencies /app/vendor ./vendor
COPY --from=dependencies /app/src ./src
COPY composer.json composer.lock ./
COPY config ./config
COPY bin ./bin
COPY database ./database
COPY design/tokens.json ./design/tokens.json
COPY templates ./templates
COPY public ./public
COPY docker/php/app.ini /usr/local/etc/php/conf.d/app.ini
COPY --from=css /build/public/assets/css/main.css ./public/assets/css/main.css
RUN mkdir -p var/templates_c var/cache public/uploads \
    && chown -R www-data:www-data var public/uploads
USER www-data

FROM nginx:1.27-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public
COPY --from=css /build/public/assets/css/main.css /var/www/html/public/assets/css/main.css
