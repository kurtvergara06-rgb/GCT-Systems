#!/usr/bin/env bash

set -e

cd /var/www/html

echo "Preparing Laravel application..."

mkdir -p \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

php artisan optimize:clear

php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run only pending migrations.
# Never use migrate:fresh here.
php artisan migrate --force

echo "Starting PHP-FPM and Nginx..."

exec /usr/bin/supervisord \
    -c /etc/supervisor/conf.d/supervisord.conf