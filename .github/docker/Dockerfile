FROM composer:2.10.3@sha256:da624c2c705d54e2f5a9bb77a5dc6c7d091711433ebefd7035672e84c2010fee AS composer

FROM php:8.3.36-fpm-bookworm@sha256:6b4641a875dd3e0f7551b2751e1f5909084856d18166af7fbb522ac230feb702

RUN apt-get update && apt-get install -y --no-install-recommends \
    git unzip libc-client-dev libkrb5-dev libonig-dev libsqlite3-dev libzip-dev \
    && docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install -j2 imap pdo_mysql pdo_sqlite mbstring zip pcntl bcmath \
    && rm -rf /var/lib/apt/lists/*

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
COPY --from=composer /usr/bin/composer /usr/local/bin/composer

WORKDIR /backend
