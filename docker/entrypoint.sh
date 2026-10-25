#!/bin/sh
set -e
# Bildemappen er montert fra NAS-en og må være skrivbar for Apache.
mkdir -p /var/www/public/uploads
chown www-data:www-data /var/www/public/uploads
exec docker-php-entrypoint "$@"
