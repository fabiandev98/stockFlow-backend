# This Dockerfile is meant for prod environments
FROM serversideup/php:8.3-fpm-nginx

WORKDIR /var/www/html
USER root

# 1) Only copy dependencies files
COPY composer.json composer.lock ./

# 2) Install dependencies using cache if available
# Install required extensions
# RUN install-php-extensions #ext1 #ext2
#    sharing=locked to prevent race conditions if there are multiple builds
RUN --mount=type=cache,target=/tmp/composer-cache,sharing=locked \
    COMPOSER_CACHE_DIR=/tmp/composer-cache \
    composer install --optimize-autoloader --no-dev --no-interaction --no-progress --prefer-dist

# 3) Copy the code (excluding files in .dockerignore)
COPY --chown=www-data:www-data . .

USER www-data