#!/usr/bin/env bash
# ==============================================================================
# Laijau — Production Zero-Downtime Atomic Deployment Script
# Target: https://laijau.com
# ==============================================================================
set -Npo pipefail

BASE_DIR="${DEPLOY_BASE_DIR:-/var/www/laijau}"
SHARED_DIR="${BASE_DIR}/shared"
RELEASES_DIR="${BASE_DIR}/releases"
CURRENT_LINK="${BASE_DIR}/current"
TIMESTAMP=$(date +%Y%m%d%H%M%S)
NEW_RELEASE="${RELEASES_DIR}/${TIMESTAMP}"
KEEP_RELEASES=5

echo "=========================================================="
echo " ==> [Laijau] Starting Production Deployment: ${TIMESTAMP}"
echo "=========================================================="

# 1. Ensure shared directory structure exists
mkdir -p "${SHARED_DIR}/storage/app/public"
mkdir -p "${SHARED_DIR}/storage/framework/cache"
mkdir -p "${SHARED_DIR}/storage/framework/sessions"
mkdir -p "${SHARED_DIR}/storage/framework/views"
mkdir -p "${SHARED_DIR}/storage/logs"
mkdir -p "${SHARED_DIR}/storage/backups"
mkdir -p "${RELEASES_DIR}"

if [[ ! -f "${SHARED_DIR}/.env" ]]; then
    echo "ERROR: Shared environment file '${SHARED_DIR}/.env' does not exist!"
    echo "Please provision the production .env file before running deploy."
    exit 1
fi

# 2. Prepare Release Directory
echo "==> [Laijau] Preparing release directory: ${NEW_RELEASE}"
mkdir -p "${NEW_RELEASE}"

if [[ -n "${RELEASE_ARCHIVE:-}" && -f "${RELEASE_ARCHIVE}" ]]; then
    echo "==> Unpacking release archive: ${RELEASE_ARCHIVE}"
    tar -xzf "${RELEASE_ARCHIVE}" -C "${NEW_RELEASE}"
else
    echo "==> Copying project files to release target"
    rsync -a --exclude='.git' \
             --exclude='node_modules' \
             --exclude='storage' \
             --exclude='tests' \
             --exclude='.phpunit.cache' \
             ./ "${NEW_RELEASE}/"
fi

# 3. Symlink Shared Persistent Assets
echo "==> [Laijau] Symlinking shared persistent storage and environment"
rm -rf "${NEW_RELEASE}/storage"
ln -sfn "${SHARED_DIR}/storage" "${NEW_RELEASE}/storage"
ln -sfn "${SHARED_DIR}/.env" "${NEW_RELEASE}/.env"

# 4. Install Production Dependencies
echo "==> [Laijau] Installing production Composer dependencies"
cd "${NEW_RELEASE}"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --quiet

# 5. Database Migrations (Safe Mode)
echo "==> [Laijau] Running database migrations"
php artisan migrate --force --no-interaction

# 6. Warm Bytecode & Framework Caches
echo "==> [Laijau] Warming production caches (config, route, view)"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache 2>/dev/null || true

# 7. Ensure Storage Symlink
php artisan storage:link --relative --force --quiet || true

# 8. Atomic Symlink Switch
echo "==> [Laijau] Activating new release atomically"
ln -sfn "${NEW_RELEASE}" "${CURRENT_LINK}.tmp"
mv -Tf "${CURRENT_LINK}.tmp" "${CURRENT_LINK}"

# 9. Reload PHP-FPM & Restart Queues
echo "==> [Laijau] Reloading PHP-FPM & restarting Queue workers"
if command -v systemctl >/dev/null 2>&1; then
    sudo systemctl reload php8.4-fpm 2>/dev/null || sudo systemctl reload php-fpm 2>/dev/null || true
fi
php "${CURRENT_LINK}/artisan" queue:restart

# 10. Release Housekeeping (Retain last 5 releases)
echo "==> [Laijau] Pruning old releases (keeping last ${KEEP_RELEASES})"
cd "${RELEASES_DIR}"
ls -dt */ 2>/dev/null | tail -n +$((KEEP_RELEASES + 1)) | xargs -I {} rm -rf "{}" 2>/dev/null || true

echo "=========================================================="
echo " ==> [Laijau] Production Deployment COMPLETED Successfully!"
echo " Target URL: https://laijau.com"
echo "=========================================================="
