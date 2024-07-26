FROM php:8.2-fpm

COPY --from=composer:latest /usr/bin/composer /usr/local/bin/composer

RUN apt-get update && apt-get install -y \
    zip \
    unzip \
    && docker-php-ext-install pdo pdo_mysql

WORKDIR /app
COPY . .
RUN composer install