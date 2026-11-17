FROM php:8.2-cli-alpine

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN apk add --no-cache git unzip bash \
    && apk add --no-cache libpq \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev \
    && pecl install pcov \
    && docker-php-ext-enable pcov \
    && docker-php-ext-install pdo_mysql pdo_pgsql \
    && apk del .build-deps

WORKDIR /app
