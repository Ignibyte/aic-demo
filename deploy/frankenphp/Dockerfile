# Lotline on FrankenPHP: PHP 8.5 in classic mode, with the committed build/ output.
# From a clean checkout's root, with AIC checked out beside it as ../aic-nextgen-stable:
#   docker build --build-context aic=../aic-nextgen-stable -f deploy/frankenphp/Dockerfile -t lotline .
FROM dunglas/frankenphp:php8.5

RUN install-php-extensions pdo_pgsql zip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# composer.json requires aic/* by path from ../aic-nextgen-stable, which is /aic-nextgen-stable here.
COPY --from=aic framework /aic-nextgen-stable/framework
COPY --from=aic gates /aic-nextgen-stable/gates
COPY --from=aic workflow /aic-nextgen-stable/workflow

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
