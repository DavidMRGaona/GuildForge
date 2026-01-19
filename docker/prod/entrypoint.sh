#!/bin/sh
set -e

echo "Running migrations..."
php artisan migrate --force

echo "Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Starting services..."
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
