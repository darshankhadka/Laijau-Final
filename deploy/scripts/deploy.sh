#!/usr/bin/env bash
# ==============================================================================
# Laijau ERP — Zero-Downtime Atomic Deployment Script
# ==============================================================================
set -euo pipefail

BASE_DIR="${DEPLOY_BASE_DIR:-/var/www/laijau}"
SHARED_DIR="${BASE_DIR}/shared"
RELEASES_DIR="${BASE_DIR}/releases"
CURRENT_LINK="${BASE_DIR}/current"
TIMESTAMP=$(date +%Y%m%d%H%M%S)
NEW_RELEASE="${RELEASES_DIR}/${TIMESTAMP}"
KEEP_RELEASES=5

echo "==> [Laijau] Starting Zero-Downtime Deployment: ${TIMESTAMP}"

# 1. Ensure directory structure exists
mkdir -p "${SHARED_DIR}/storage/app/public"
mkdir -p "${SHARED_DIR}/storage/framework/cache"
mkdir -p "${SHARED_DIR}/storage/framework/sessions"
mkdir -p "${SHARED_DIR}/storage/framework/views"
mkdir -p "${SHARED_DIR}/storage/logs"
mkdir -p "${SHARED_DIR}/storage/backups"
mkdir -p "${RELEASES_DIR}"

if [[ ! -f "${SHARED_DIR}/.env" ]]; then
    echo "ERROR: Shared environment file '${SHARED_DIR}/.env' does not exist!"
    echo "Please provision the production .env file before deploying."
    exit 1
fi

# 2. Extract or Copy Application to New Release Directory
echo "==> [Laijau] Preparing release directory: ${NEW_RELEASE}"
mkdir -p "${NEW_RELEASE}"

if [[ -n "${RELEASE_ARCHIVE:-}" && -f "${RELEASE_ARCHIVE}" ]]; then
    echo "==> Unpacking release archive: ${RELEASE_ARCHIVE}"
    tar -xzf "${RELEASE_ARCHIVE}" -C "${NEW_RELEASE}"
else
    echo "==> Copying project files from current workspace"
    # Sync repository files excluding transient data
    rsync -a --exclude='.git' \
             --exclude='node_modules' \
             --exclude='storage' \
             --exclude='tests' \
             --exclude='.phpunit.cache' \
             ./ "${NEW_RELEASE}/"
fi

# 3. Bind Shared Assets (Persistent .env and storage)
echo "==> [Laijau] Symlinking shared persistent assets"
rm -rf "${NEW_RELEASE}/storage"
ln -sfn "${SHARED_DIR}/storage" "${NEW_RELEASE}/storage"
ln -sfn "${SHARED_DIR}/.env" "${NEW_RELEASE}/.env"

# 4. Install Optimized PHP Dependencies
echo "==> [Laijau] Installing optimized production Composer dependencies"
cd "${NEW_RELEASE}"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --quiet

# 5. Database Schema Migrations
echo "==> [Laijau] Running database migrations with --force"
php artisan migrate --force

# 6. Warm Framework Caches
echo "==> [Laijau] Warming production caches (config, routes, views)"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 7. Create public storage symlink if not present
php artisan storage:link --relative --force --quiet || true

# 8. Atomic Symlink Switch
echo "==> [Laijau] Atomically switching live release link"
ln -sfn "${NEW_RELEASE}" "${CURRENT_LINK}"

# 9. Reload PHP-FPM & Signal Queue Workers
echo "==> [Laijau] Signaling queue workers to restart gracefully"
php "${CURRENT_LINK}/artisan" queue:restart

if command -v systemctl >/dev/null 2>&1; then
    echo "==> [Laijau] Reloading PHP-FPM service"
    sudo systemctl reload php8.4-fpm 2>/dev/null || sudo systemctl reload php-fpm 2>/dev/null || true
fi

# 10. Clean Up Stale Releases (Retain last 5)
echo "==> [Laijau] Pruning old releases (keeping last ${KEEP_RELEASES})"
cd "${RELEASES_DIR}"
ls -1dt 20* 2>/dev/null | tail -n +$((KEEP_RELEASES + 1)) | xargs rm -rf 2>/dev/null || true

echo "==> [Laijau] Zero-downtime deployment complete! Live release: ${TIMESTAMP}"
