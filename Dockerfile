FROM php:8.2-fpm

ENV PHALCON_VERSION="5.9.3" \
    REDIS_VERSION="6.2.0"

RUN apt update -y && \
    apt install -y \
        apt-utils \
        gettext \
        libicu-dev \
        libzip-dev \
        libgmp-dev \
        wget \
        zip

# PECL Packages
RUN pecl install -o -s apcu
RUN pecl install -o -s igbinary
RUN pecl install -o -s xdebug

# Redis
RUN pecl install -o -s redis-${REDIS_VERSION}

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . /app

CMD ["php", "--version"]
