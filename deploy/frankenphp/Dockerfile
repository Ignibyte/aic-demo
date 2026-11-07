# Lotline on FrankenPHP: PHP 8.5 in classic mode, with the committed build/ output.
# From a clean checkout's root:
#   docker build -f deploy/frankenphp/Dockerfile -t lotline .
FROM dunglas/frankenphp:php8.5

RUN install-php-extensions pdo_pgsql zip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# AIC comes from Packagist (ignibyte/aic-framework); the dev packages stay out of the image.
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --no-autoloader

COPY bin/aic bin/aic
COPY bootstrap bootstrap
COPY build build
COPY config config
COPY modules modules
COPY public public
COPY themes themes
RUN composer dump-autoload --no-dev --optimize --no-scripts

COPY deploy/frankenphp/Caddyfile /etc/frankenphp/Caddyfile
