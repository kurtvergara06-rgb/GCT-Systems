#!/usr/bin/env bash

set -e

cd /var/www/html

echo "Preparing Laravel application..."

# ----------------------------------------------------------
# CREATE REQUIRED LARAVEL DIRECTORIES
# ----------------------------------------------------------

mkdir -p \
    storage/app/public \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# ----------------------------------------------------------
# PREPARE MYSQL SSL CERTIFICATE
# ----------------------------------------------------------

if [ -n "${MYSQL_ATTR_SSL_CA:-}" ]; then
    echo "MySQL SSL certificate is configured."

    if [ ! -f "$MYSQL_ATTR_SSL_CA" ]; then
        echo "ERROR: MySQL CA certificate was not found."
        echo "Expected path: $MYSQL_ATTR_SSL_CA"
        ls -la /etc/secrets || true
        exit 1
    fi

    if ! openssl x509 \
        -in "$MYSQL_ATTR_SSL_CA" \
        -noout \
        -subject \
        -issuer \
        >/dev/null 2>&1; then

        echo "ERROR: MySQL CA certificate is invalid."
        exit 1
    fi

    # Copy the Render secret to a location readable by PHP-FPM.
    cp "$MYSQL_ATTR_SSL_CA" /tmp/aiven-ca.pem
    chown www-data:www-data /tmp/aiven-ca.pem
    chmod 644 /tmp/aiven-ca.pem

    # Laravel will cache this readable path.
    export MYSQL_ATTR_SSL_CA=/tmp/aiven-ca.pem

    echo "MySQL CA certificate prepared at:"
    echo "$MYSQL_ATTR_SSL_CA"
else
    echo "MySQL SSL certificate is not configured."
fi

# ----------------------------------------------------------
# PREPARE LARAVEL
# ----------------------------------------------------------

php artisan optimize:clear

rm -rf public/storage
php artisan storage:link || true

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run pending migrations only.
php artisan migrate --force

echo "Laravel application prepared successfully."

# ----------------------------------------------------------
# START PHP-FPM AND NGINX
# ----------------------------------------------------------

echo "Starting PHP-FPM and Nginx..."

exec /usr/bin/supervisord \
    -c /etc/supervisor/conf.d/supervisord.conf