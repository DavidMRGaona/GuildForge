#!/bin/sh
set -e

# Mark this deployment as failed in the deployment log, then stop the boot
record_failure() {
    php artisan core:record-deployment fail --error="$1" || true
    exit 1
}

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

echo "Recording deployment..."
php artisan core:record-deployment start || echo "Warning: Could not record the deployment"

echo "Running migrations..."
php artisan migrate --force || record_failure "Migrations failed (see the container logs)"

echo "Discovering modules..."
php artisan module:discover || echo "Warning: Module discovery had issues (check logs)"

# New core/module permissions only show up in Roles > Permissions once synced
echo "Syncing permissions..."
php artisan permissions:sync || echo "Warning: Permission sync had issues (check logs)"

echo "Caching configuration..."
php artisan config:cache || record_failure "Config cache failed (see the container logs)"
php artisan route:clear
php artisan view:cache || record_failure "View cache failed (see the container logs)"

php artisan core:record-deployment finish || echo "Warning: Could not record the deployment"

echo "Starting services..."
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
