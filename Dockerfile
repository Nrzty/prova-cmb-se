# syntax=docker/dockerfile:1

FROM php:8.2-fpm-alpine

# System deps
RUN apk add --no-cache \
    bash \
    git \
    icu-dev \
    libzip-dev \
    oniguruma-dev \
    postgresql-dev \
    zip \
    unzip \
    nodejs \
    npm

# PHP extensions
RUN docker-php-ext-configure intl \
    && docker-php-ext-install -j$(nproc) \
        intl \
        pdo \
        pdo_pgsql \
        zip \
        bcmath \
        opcache

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# Copy app
COPY . .

# Install PHP deps
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Build frontend assets
RUN npm ci && npm run build

# Ensure storage permissions
RUN mkdir -p storage bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]

