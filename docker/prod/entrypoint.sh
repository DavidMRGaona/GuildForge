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
    modules \
    public/build/modules

# Sync modules from image (version-aware: preserves ZIP-updated modules in volume)
echo "Syncing modules from image..."
php artisan module:sync-from-image /opt/modules-image || echo "Warning: Module sync had issues"

# Ensure all module build assets are in public/build/modules/ (survives redeploys)
echo "Publishing module build assets..."
php artisan module:publish-build-assets || echo "Warning: Module asset publishing had issues"

# Set permissions
chmod -R 775 storage bootstrap/cache modules public/build
chown -R www-data:www-data storage bootstrap/cache modules public/build

# Create symlink if it doesn't exist (must be done before Laravel boots)
if [ ! -L public/storage ]; then
    ln -sf ../storage/app/public public/storage
fi

echo "Running migrations..."
php artisan migrate --force

echo "Discovering modules..."
php artisan module:discover || echo "Warning: Module discovery had issues (check logs)"

echo "Caching configuration..."
php artisan config:cache
php artisan route:clear
php artisan view:cache

echo "Starting services..."
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
