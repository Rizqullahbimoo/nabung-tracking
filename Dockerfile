# syntax=docker/dockerfile:1

# =====================================================================
# STAGE 1 - "frontend": build the CSS/JS with Vite + Tailwind.
# Node is only needed here; it never ends up in the final image.
# =====================================================================
FROM node:20-alpine AS frontend

WORKDIR /app

# Copy only the dependency manifests first. Docker caches this layer, so
# `npm ci` re-runs only when package*.json actually change.
COPY package.json package-lock.json ./
RUN npm ci

# Now the rest of the source (needed: resources/, *.config.js, blade
# files for Tailwind's class scanning). .dockerignore keeps junk out.
COPY . .

# Produces public/build/ (hashed assets + manifest.json).
RUN npm run build


# =====================================================================
# STAGE 2 - "app": the real image. PHP 8.2 + Apache + our code.
# =====================================================================
FROM php:8.2-apache AS app

# --- System libraries + PHP extensions Laravel/Neon need --------------
# libpq-dev  -> headers for pdo_pgsql/pgsql (PostgreSQL / Neon)
# libzip/oniguruma/icu -> zip, mbstring, intl
RUN apt-get update && apt-get install -y --no-install-recommends \
        libpq-dev \
        libzip-dev \
        libicu-dev \
        libonig-dev \
        unzip \
        git \
    && docker-php-ext-configure intl \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_pgsql \
        pgsql \
        mbstring \
        bcmath \
        intl \
        zip \
        opcache \
    && a2enmod rewrite headers \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# --- Composer: copy the binary from the official Composer image ------
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# --- Custom PHP + Apache config -------------------------------------
COPY docker/php.ini      /usr/local/etc/php/conf.d/zz-app.ini
COPY docker/opcache.ini  /usr/local/etc/php/conf.d/zz-opcache.ini
COPY docker/000-default.conf /etc/apache2/sites-available/000-default.conf

WORKDIR /var/www/html

# --- Install PHP dependencies (production only) ---------------------
# Copy just the composer files first for layer caching. --no-scripts /
# --no-autoloader because the app code isn't here yet.
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-interaction \
        --prefer-dist \
        --no-progress \
        --no-scripts \
        --no-autoloader

# --- Bring in the application code + the built assets from stage 1 --
COPY . .
COPY --from=frontend /app/public/build ./public/build

# Now that the code is present, build the optimised autoloader and let
# Laravel discover its packages.
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && php artisan package:discover --ansi

# --- Permissions: Apache runs as www-data --------------------------
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

# --- Entrypoint ---------------------------------------------------
COPY docker/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# Documentation only; Render maps its own $PORT via the entrypoint.
EXPOSE 8080

# entrypoint runs migrations + cache warmup, then execs this CMD.
ENTRYPOINT ["entrypoint"]
CMD ["apache2-foreground"]
