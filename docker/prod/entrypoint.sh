#!/bin/sh
set -e

echo "Initializing directories..."

# Create subdirectories of storage (volume may be empty on first deploy)
mkdir -p storage/framework/{cache,sessions,views} storage/logs storage/app/public storage/app/backups
chmod -R 775 storage
chown -R www-data:www-data storage

# Create modules directory
mkdir -p modules
chmod -R 775 modules
chown -R www-data:www-data modules

# Create symlink of storage if it doesn't exist
if [ ! -L public/storage ]; then
    php artisan storage:link || true
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
