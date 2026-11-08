#!/bin/sh
set -e

# The app (php-fpm, queue worker, scheduler) runs as www-data, so every file Laravel
# writes during the boot (compiled views, config cache, logs) must belong to www-data
# too: run each artisan command through this function, never as root
artisan() {
    su-exec www-data php artisan "$@"
}

# Mark this deployment as failed in the deployment log, then stop the boot
record_failure() {
    artisan core:record-deployment fail --error="$1" || true
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

# Set permissions before the first artisan command, which runs as www-data. This also
# repairs volumes holding files that a boot run as root left behind
chmod -R 775 storage bootstrap/cache modules public/build
chown -R www-data:www-data storage bootstrap/cache modules public/build

# Sync modules from image (version-aware: preserves ZIP-updated modules in volume)
echo "Syncing modules from image..."
artisan module:sync-from-image /opt/modules-image || echo "Warning: Module sync had issues"

# Ensure all module build assets are in public/build/modules/ (survives redeploys)
echo "Publishing module build assets..."
artisan module:publish-build-assets || echo "Warning: Module asset publishing had issues"

# Create symlink if it doesn't exist (must be done before Laravel boots)
if [ ! -L public/storage ]; then
    ln -sf ../storage/app/public public/storage
fi

echo "Recording deployment..."
artisan core:record-deployment start || echo "Warning: Could not record the deployment"

echo "Running migrations..."
artisan migrate --force || record_failure "Migrations failed (see the container logs)"

echo "Discovering modules..."
artisan module:discover || echo "Warning: Module discovery had issues (check logs)"

# New core/module permissions only show up in Roles > Permissions once synced
echo "Syncing permissions..."
artisan permissions:sync || echo "Warning: Permission sync had issues (check logs)"

# Guides from the core and the enabled modules; the search index is refreshed too, and a failure never stops the deploy
echo "Syncing knowledge base..."
artisan knowledge:sync || echo "Warning: Knowledge sync had issues (check logs)"

# Documents of the site search from the core and the enabled modules; a failure never stops the deploy
echo "Syncing site search..."
artisan search:sync || echo "Warning: Search sync had issues (check logs)"

echo "Caching configuration..."
artisan config:cache || record_failure "Config cache failed (see the container logs)"
artisan route:clear
artisan view:cache || record_failure "View cache failed (see the container logs)"

artisan core:record-deployment finish || echo "Warning: Could not record the deployment"

# A file the app cannot rewrite breaks module updates (their view:cache) and any page whose
# compiled view changes. Nothing above should leave one; if something does, list it and hand
# it back to www-data instead of stopping the boot
not_owned=$(find storage/framework storage/logs bootstrap/cache ! -user www-data)
if [ -n "$not_owned" ]; then
    echo "Warning: files not owned by www-data after the boot, handing them back:"
    echo "$not_owned"
    chown -R www-data:www-data storage/framework storage/logs bootstrap/cache
fi

echo "Starting services..."
exec supervisord -c /etc/supervisor/conf.d/supervisord.conf
