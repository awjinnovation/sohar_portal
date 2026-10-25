# syntax=docker/dockerfile:1
#
# Events Platform (Mawsim) backend: production image.
#
#   Base     serversideup/php (PHP 8.3, PHP-FPM + NGINX under S6, runs as
#            www-data, listens on 8080, Laravel automations on start).
#   Targets  production (default; used by docker-compose.yml / Coolify)
#            test       (production + dev dependencies + tests, for running
#                        PHPUnit against MySQL; never deployed)
#
# Bump the base tag deliberately (security releases are announced at
# https://github.com/serversideup/docker-php/releases).
ARG PHP_IMAGE=serversideup/php:8.3-fpm-nginx-trixie-v5.0.3

############################################################################
# base: runtime + PHP extensions + web server config
############################################################################
FROM ${PHP_IMAGE} AS base

USER root

# Already in the image: pdo_mysql, redis, pcntl, zip, opcache, mbstring.
# intl: number/date formatting; bcmath: money maths; exif + gd: medialibrary
# thumbnails (webp); imagick: PNG QR codes (simple-qrcode).
RUN install-php-extensions intl bcmath exif gd imagick \
    && php -m | grep -qiE '^(intl|bcmath|exif|gd|imagick)$'

# - Logs to stderr (docker logs / Coolify "Logs").
# - OPcache on, without timestamp checks: code only changes with a new image.
# - /up (Laravel's health route) is the container health check.
# - TRUSTED_PROXY=off: NGINX keeps the proxy address in REMOTE_ADDR and
#   Laravel decides which proxies to trust (config/trustedproxy.php), so
#   X-Forwarded-For / -Proto from Coolify's Traefik / Caddy are honoured once.
# - AUTORUN_ENABLED=false: migrations / caches only run where a service turns
#   them on (the "app" service in docker-compose.yml); one-off containers
#   stay inert.
ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    LOG_LEVEL=info \
    PHP_OPCACHE_ENABLE=1 \
    HEALTHCHECK_PATH=/up \
    TRUSTED_PROXY=off \
    AUTORUN_ENABLED=false \
    SHOW_WELCOME_MESSAGE=false

# CORS on the public images the web builds load cross-origin (/storage/*,
# /tenants/*, /images/*; ./server.php does this for `php artisan serve`),
# limited to CORS_ALLOWED_ORIGINS (rendered into an NGINX map at start).
COPY --chown=www-data:www-data docker/nginx/public-assets.conf /etc/nginx/server-opts.d/public-assets.conf
COPY --chown=www-data:www-data docker/nginx/public-asset-cors.conf /etc/nginx/snippets/public-asset-cors.conf
COPY --chmod=755 docker/entrypoint.d/20-public-cors.sh /etc/entrypoint.d/20-public-cors.sh

USER www-data
WORKDIR /var/www/html

############################################################################
# vendor: production Composer dependencies (no dev packages)
############################################################################
FROM base AS vendor

COPY --chown=www-data:www-data composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist \
        --no-scripts --no-autoloader \
    && rm -rf /composer/cache

############################################################################
# production
############################################################################
FROM base AS production

COPY --chown=www-data:www-data --from=vendor /var/www/html/vendor ./vendor
COPY --chown=www-data:www-data artisan composer.json composer.lock ./
COPY --chown=www-data:www-data app ./app
COPY --chown=www-data:www-data bootstrap ./bootstrap
COPY --chown=www-data:www-data config ./config
COPY --chown=www-data:www-data database ./database
COPY --chown=www-data:www-data lang ./lang
COPY --chown=www-data:www-data public ./public
COPY --chown=www-data:www-data resources ./resources
COPY --chown=www-data:www-data routes ./routes

# Writable skeleton. storage/app is the persistent volume (uploads); the rest
# is per container (sessions / cache live in Redis, logs go to stderr).
# public/storage -> storage/app/public as a relative link, so storage:link is
# not needed at runtime.
RUN mkdir -p storage/app/public storage/app/private \
        storage/framework/cache/data storage/framework/sessions \
        storage/framework/testing storage/framework/views \
        storage/logs storage/media-library/temp bootstrap/cache \
    && ln -sfn ../storage/app/public public/storage \
    && composer dump-autoload --no-dev --optimize --no-interaction \
    && rm -rf /composer/cache

############################################################################
# test: production + dev dependencies + the PHPUnit suite (never deployed)
############################################################################
FROM production AS test

ENV APP_ENV=testing \
    PHP_OPCACHE_ENABLE=0

COPY --chown=www-data:www-data phpunit.xml server.php ./
COPY --chown=www-data:www-data tests ./tests
# Empty .env: configuration comes from the container environment (an empty
# file keeps Dotenv from reporting the missing one as a test warning).
RUN composer install --no-interaction --no-progress --prefer-dist \
    && rm -rf /composer/cache \
    && touch .env

############################################################################
# Default target
############################################################################
FROM production
