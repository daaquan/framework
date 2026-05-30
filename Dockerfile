FROM php:8.4-cli

# Suppress incompatible-pointer-types errors during Phalcon compilation for PHP 8.3+
ENV CFLAGS="-Wno-incompatible-pointer-types"

RUN apt-get update -y && \
    apt-get install -y --no-install-recommends \
        git unzip libicu-dev libzip-dev libgmp-dev zlib1g-dev && \
    docker-php-ext-install intl zip gmp bcmath pdo pdo_mysql && \
    pecl install apcu igbinary phalcon-5.10.0 && \
    docker-php-ext-enable apcu igbinary phalcon && \
    apt-get clean && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . /app
RUN composer install --no-interaction --prefer-dist --ignore-platform-req=php

CMD ["php", "bin/pest"]
