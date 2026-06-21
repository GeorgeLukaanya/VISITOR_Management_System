# PHP-FPM image for the Visitor Management app.
# The same image is reused for the php-fpm web worker, the queue worker, and Reverb
# (only the container command differs in docker-compose.yml).
FROM php:8.4-fpm

# System libraries needed to build the PHP extensions we use.
#  - libpq-dev      -> pdo_pgsql / pgsql (PostgreSQL, our primary datastore)
#  - libsqlite3-dev -> pdo_sqlite (fast in-memory test database)
#  - libzip/zip     -> composer package handling
RUN apt-get update && apt-get install -y --no-install-recommends \
        git unzip libpq-dev libzip-dev libsqlite3-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql pdo_sqlite zip bcmath pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer (copied from the official composer image).
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Run as a non-root user that matches the host UID so bind-mounted files stay writable.
ARG UID=1000
ARG GID=1000
RUN groupmod -g "${GID}" www-data 2>/dev/null || true \
    && usermod -u "${UID}" -g "${GID}" www-data 2>/dev/null || true

EXPOSE 9000
CMD ["php-fpm"]
