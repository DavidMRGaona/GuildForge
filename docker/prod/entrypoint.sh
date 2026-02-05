#!/bin/sh
set -e

echo "Initializing directories..."

# Create ALL required directories BEFORE any Laravel command
# (volumes may be empty on first deploy, overwriting Dockerfile-created dirs)
mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    storage/app/public \
    storage/app/backups \
    bootstrap/cache \
    modules

# Set permissions
chmod -R 775 storage bootstrap/cache modules
chown -R www-data:www-data storage bootstrap/cache modules

# Create symlink if it doesn't exist (must be done before Laravel boots)
if [ ! -L public/storage ]; then
    ln -sf ../storage/app/public public/storage
fi

echo "Running migrations..."
php artisan migrate --force

echo "Caching configuration..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "Discovering modules..."
php artisan module:discover || true

echo "Starting services..."
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
