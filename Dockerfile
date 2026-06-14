FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
        $PHPIZE_DEPS \
        curl-dev \
        icu-dev \
        libxml2-dev \
        libpq-dev \
        libzip-dev \
        linux-headers \
        oniguruma-dev \
        unzip \
    && docker-php-ext-install \
        bcmath \
        curl \
        dom \
        intl \
        mbstring \
        opcache \
        pcntl \
        pdo_pgsql \
        zip

COPY --from=composer:2.8 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/php/entrypoint.sh /usr/local/bin/app-entrypoint

ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]
