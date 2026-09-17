#!/usr/bin/env bash
# ==============================================================================
# Laijau ERP — cPanel & Shared Hosting Deployment Automation Script
# Compatible with cPanel Terminal, SSH, or Git Version Control Post-Receive Hook
# ==============================================================================
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${APP_DIR}"

echo "================================================================================"
echo "          LAIJAU ERP — CPANEL / SHARED HOSTING DEPLOYMENT"
echo "================================================================================"
echo "Target Directory: ${APP_DIR}"
echo "Deployment Time:  $(date)"
echo "--------------------------------------------------------------------------------"

# 1. Pre-deployment Database Backup
echo "==> [1/8] Generating pre-deployment encrypted safety backup..."
php artisan erp:backup --type=db --encrypt || echo "Warning: Pre-deployment backup skipped or failed."

# 2. Activate Maintenance Mode
echo "==> [2/8] Entering maintenance mode (503)..."
php artisan down --render="errors::503" --secret="laijau-deploy-bypass" || true

# 3. Pull / Synchronize Code
if [ -d ".git" ]; then
    echo "==> [3/8] Pulling latest repository commits..."
    git pull origin main --quiet || true
else
    echo "==> [3/8] Non-git deployment detected (files synced via FTP/cPanel File Manager)."
fi

# 4. Install Optimized Dependencies
echo "==> [4/8] Installing production Composer packages..."
composer install --no-dev --optimize-autoloader --prefer-dist --no-interaction --quiet

# 5. Database Schema Migrations
echo "==> [5/8] Executing database migrations with --force..."
php artisan migrate --force

# 6. Warm Framework Caches
echo "==> [6/8] Caching configuration, routes, and views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 7. Public Storage Symlink
echo "==> [7/8] Verifying storage:link symlink..."
php artisan storage:link --relative --force --quiet || true

# 8. Pre-Flight Verification & Exit Maintenance
echo "==> [8/8] Running operational verification check..."
php artisan erp:staging-verify

echo "==> Exiting maintenance mode..."
php artisan up

echo "--------------------------------------------------------------------------------"
echo "✓ SUCCESS: Laijau ERP deployed successfully on shared hosting!"
echo "================================================================================"
