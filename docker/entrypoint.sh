#!/bin/sh
set -e

mkdir -p /var/www/html/temp/cache \
          /var/www/html/log \
          /var/www/html/www/uploads/tickets \
          /var/www/html/www/uploads/blueprints
chmod -R 775 /var/www/html/temp /var/www/html/log /var/www/html/www/uploads

exec "$@"
