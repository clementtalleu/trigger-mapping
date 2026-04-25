FROM php:8.3-cli

# System dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        zlib1g-dev \
        libxml2-dev \
        netcat-openbsd \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions via mlocati's helper (handles ODBC for pdo_sqlsrv automatically)
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions \
        pdo_mysql \
        pdo_pgsql \
        pdo_sqlsrv \
        sqlsrv \
        xdebug \
        zip \
        intl

# Disable xdebug by default (set XDEBUG_MODE=coverage to enable)
RUN echo "xdebug.mode=off" > /usr/local/etc/php/conf.d/99-xdebug.ini

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
