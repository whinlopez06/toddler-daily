FROM php:8.5-cli-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends git unzip libpq-dev libzip-dev libicu-dev \
    && docker-php-ext-install pdo_pgsql bcmath pcntl zip intl \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
EXPOSE 8000
CMD ["sh", "-c", "composer install --no-interaction --prefer-dist && php artisan serve --host=0.0.0.0 --port=8000"]
