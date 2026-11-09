FROM php:8.5-apache-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libicu-dev libpq-dev \
    && docker-php-ext-install -j"$(nproc)" bcmath intl pdo_pgsql \
    && rm -rf /var/lib/apt/lists/* \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && a2enmod rewrite

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

ENV PORT=10000
RUN printf '%s\n' 'Listen ${PORT}' > /etc/apache2/ports.conf \
    && printf '%s\n' \
        '<VirtualHost *:${PORT}>' \
        '    DocumentRoot /var/www/html/public' \
        '    <Directory /var/www/html/public>' \
        '        DirectoryIndex index.php' \
        '        AllowOverride All' \
        '        Require all granted' \
        '    </Directory>' \
        '</VirtualHost>' > /etc/apache2/sites-available/000-default.conf \
    && apache2ctl configtest

WORKDIR /var/www/html
COPY composer.json composer.lock artisan ./
COPY app/ ./app/
COPY bootstrap/ ./bootstrap/
COPY config/ ./config/
COPY database/ ./database/
COPY public/ ./public/
COPY resources/ ./resources/
COPY routes/ ./routes/

RUN mkdir -p bootstrap/cache storage/app/private storage/app/public \
        storage/framework/cache/data storage/framework/cache/locks \
        storage/framework/sessions storage/framework/views storage/logs \
    && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader \
    && composer check-platform-reqs --no-dev \
    && chown -R www-data:www-data bootstrap/cache storage

EXPOSE 10000
CMD ["apache2-foreground"]
