FROM composer:latest as composer
FROM php:8.2-cli

ENV PHALCON_VERSION="5.9.3" \
    PHP_VERSION="8.2"

# Update
RUN apt-get update -y && \
    apt-get install -y \
        apt-utils \
        gettext \
        git \
        libicu-dev \
        libzip-dev \
        libgmp-dev \
        vim \
        sudo \
        wget \
        zip

# PECL Packages
RUN pecl install -o -s redis msgpack && \
    pecl install phalcon-${PHALCON_VERSION}

# Install PHP extensions
RUN docker-php-ext-install \
      intl \
      gettext \
      gmp \
      bcmath \
      pdo_mysql \
      zip

# Install PHP extensions
RUN docker-php-ext-enable \
      intl \
      gmp \
      bcmath \
      opcache \
      msgpack \
      phalcon \
      redis

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . /app

CMD ["php", "--version"]
