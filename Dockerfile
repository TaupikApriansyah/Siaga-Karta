# syntax=docker/dockerfile:1.7
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json ./
RUN npm install --no-audit --no-fund
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public
RUN test -f resources/js/main.jsx && test -f resources/js/SiagaKartaApp.jsx
RUN npm run build

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --no-autoloader
COPY . .
RUN composer dump-autoload --optimize --no-dev

FROM php:8.3-apache-bookworm AS runtime
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN apt-get update && apt-get install -y --no-install-recommends \
    libicu-dev libzip-dev libonig-dev libxml2-dev unzip curl \
    && docker-php-ext-install pdo_mysql mbstring intl zip opcache dom \
    && a2enmod rewrite headers expires \
    && rm -rf /var/lib/apt/lists/*
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/99-siagakarta.ini
COPY --from=vendor /app /var/www/html
COPY --from=frontend /app/public/build /var/www/html/public/build
COPY docker/entrypoint.sh /usr/local/bin/siagakarta-entrypoint
RUN chmod +x /usr/local/bin/siagakarta-entrypoint \
    && mkdir -p /var/www/html/storage/app/private /var/www/html/storage/framework/cache /var/www/html/storage/framework/sessions /var/www/html/storage/framework/views /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
WORKDIR /var/www/html
EXPOSE 80
HEALTHCHECK --interval=15s --timeout=5s --start-period=180s --retries=5 CMD curl -fsS http://127.0.0.1/up || exit 1
ENTRYPOINT ["siagakarta-entrypoint"]
CMD ["apache2-foreground"]
