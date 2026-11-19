FROM composer:2.10.3@sha256:da624c2c705d54e2f5a9bb77a5dc6c7d091711433ebefd7035672e84c2010fee AS composer

FROM php:8.3.37-fpm-bookworm@sha256:5b9440ebac23314bb42d33338918c1c2c852469e5b0a7f0a9493448d827bab35

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libc-client-dev libkrb5-dev libonig-dev libsqlite3-dev libzip-dev \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install -j2 imap pdo_mysql pdo_sqlite mbstring zip pcntl bcmath \
    && rm -rf /var/lib/apt/lists/*

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY --from=composer /usr/bin/composer /usr/local/bin/composer

WORKDIR /backend
