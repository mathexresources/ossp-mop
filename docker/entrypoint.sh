#!/bin/sh
set -e

mkdir -p /var/www/html/temp /var/www/html/log
rm -rf /var/www/html/temp/cache
chown -R www-data:www-data /var/www/html/temp /var/www/html/log
chmod -R 775 /var/www/html/temp /var/www/html/log

exec "$@"
