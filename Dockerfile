FROM php:8.3-cli

# Install required extensions and tools
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git unzip libicu-dev libzip-dev libgmp-dev libpq-dev \
        libssl-dev libcurl4-openssl-dev \
    && docker-php-ext-install intl mbstring pdo bcmath gmp zip \
    && pecl channel-update pecl.php.net \
    && pecl install phalcon-5.9.3 redis \
    && docker-php-ext-enable phalcon redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . /app

CMD ["php", "--version"]
