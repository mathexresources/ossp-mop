#!/bin/sh
set -e

mkdir -p /var/www/html/temp/cache \
          /var/www/html/log \
          /var/www/html/www/uploads/tickets \
          /var/www/html/www/uploads/blueprints

exec "$@"
