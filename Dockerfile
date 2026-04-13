FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
        git \
        unzip \
        libzip-dev \
        libonig-dev \
    && docker-php-ext-install pdo pdo_mysql zip mbstring \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock* ./
RUN composer install --no-scripts --no-autoloader --no-dev --prefer-dist

COPY . .

RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p temp log \
    && chown -R www-data:www-data temp log \
    && chmod -R 775 temp log

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
