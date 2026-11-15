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

# Prepare the Aiven MySQL CA certificate.
if [ -n "${MYSQL_ATTR_SSL_CA:-}" ]; then
    if [ ! -f "$MYSQL_ATTR_SSL_CA" ]; then
        echo "ERROR: MySQL CA certificate was not found."
        echo "Expected path: $MYSQL_ATTR_SSL_CA"
        ls -la /etc/secrets || true
        exit 1
    fi

    cp "$MYSQL_ATTR_SSL_CA" /tmp/aiven-ca.pem
    chown www-data:www-data /tmp/aiven-ca.pem
    chmod 644 /tmp/aiven-ca.pem

    export MYSQL_ATTR_SSL_CA=/tmp/aiven-ca.pem

    echo "MySQL CA certificate prepared."
fi

# Do not use optimize:clear because it may access database cache.
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan config:cache

echo "Starting Reverb on 0.0.0.0:${PORT:-10000}..."

exec php artisan reverb:start \
    --host=0.0.0.0 \
    --port="${PORT:-10000}"