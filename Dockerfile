FROM php:8.4-cli AS build

RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libzip-dev \
    libcurl4-openssl-dev \
    libonig-dev \
    ca-certificates \
    && docker-php-ext-install zip mbstring curl \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --classmap-authoritative

COPY . .
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

RUN useradd -u 1000 -m appuser && chown -R appuser:appuser /app

FROM php:8.4-cli AS runtime
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libcurl4-openssl-dev \
    libonig-dev \
    ca-certificates \
    && docker-php-ext-install zip mbstring curl \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY --from=build /app /app
USER appuser
EXPOSE 10000
HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 CMD php -l test_server.php || exit 1
CMD ["php", "-S", "0.0.0.0:10000", "test_server.php"]
