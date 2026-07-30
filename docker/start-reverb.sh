#!/usr/bin/env bash

set -e

cd /var/www/html

echo "Preparing Laravel Reverb..."

mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

if [ -n "${MYSQL_ATTR_SSL_CA:-}" ] && [ -f "$MYSQL_ATTR_SSL_CA" ]; then
    cp "$MYSQL_ATTR_SSL_CA" /tmp/aiven-ca.pem
    chown www-data:www-data /tmp/aiven-ca.pem
    chmod 644 /tmp/aiven-ca.pem
    export MYSQL_ATTR_SSL_CA=/tmp/aiven-ca.pem
fi

php artisan optimize:clear
php artisan config:cache

echo "Starting Reverb on 0.0.0.0:${PORT:-10000}..."

exec php artisan reverb:start \
    --host=0.0.0.0 \
    --port="${PORT:-10000}"