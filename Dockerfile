FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
        unzip \
        libzip-dev \
        libonig-dev \
    && docker-php-ext-install pdo pdo_mysql zip mbstring \
    && rm -rf /var/lib/apt/lists/*

RUN { \
        echo 'display_errors=Off'; \
        echo 'display_startup_errors=Off'; \
        echo 'log_errors=On'; \
        echo 'expose_php=Off'; \
        echo 'error_reporting=E_ALL & ~E_DEPRECATED & ~E_STRICT'; \
        echo 'memory_limit=128M'; \
        echo 'max_execution_time=30'; \
        echo 'upload_max_filesize=10M'; \
        echo 'post_max_size=12M'; \
    } > /usr/local/etc/php/conf.d/production.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY composer.json composer.lock* ./
RUN composer install --no-scripts --no-autoloader --no-dev --prefer-dist

COPY . .

RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p temp log www/uploads/tickets www/uploads/blueprints \
    && chmod -R 775 temp log www/uploads

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

ENTRYPOINT ["/entrypoint.sh"]
CMD ["php-fpm"]
