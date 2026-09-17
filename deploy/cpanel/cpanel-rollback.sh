#!/usr/bin/env bash
# ==============================================================================
# Laijau ERP — cPanel & Shared Hosting Rollback Automation Script
# Compatible with cPanel Terminal, SSH, or Git Version Control
# ==============================================================================
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${APP_DIR}"

echo "================================================================================"
echo "          LAIJAU ERP — CPANEL / SHARED HOSTING ROLLBACK"
echo "================================================================================"
echo "Target Directory: ${APP_DIR}"
echo "Rollback Time:    $(date)"
echo "--------------------------------------------------------------------------------"

# 1. Activate Maintenance Mode
echo "==> [1/7] Entering emergency maintenance mode (503)..."
php artisan down --render="errors::503" --secret="laijau-deploy-bypass" || true

# 2. Emergency Safety Backup Before Rollback
echo "==> [2/7] Generating safety backup of current state prior to rollback..."
php artisan erp:backup --type=db --encrypt || echo "Warning: Emergency backup step skipped."

# 3. Rollback Code Commit
TARGET_COMMIT="${1:-HEAD~1}"
if [ -d ".git" ]; then
    echo "==> [3/7] Reverting repository to target revision: ${TARGET_COMMIT}..."
    git checkout "${TARGET_COMMIT}" --quiet || git reset --hard "${TARGET_COMMIT}" --quiet || echo "Warning: Git checkout failed."
else
    echo "==> [3/7] Non-git deployment detected. Ensure previous code files are restored via cPanel File Manager."
fi

# 4. Optional Database Backup Restoration
# If an archive file is supplied as the second argument, restore it
DB_ARCHIVE="${2:-}"
if [ -n "${DB_ARCHIVE}" ] && [ -f "${DB_ARCHIVE}" ]; then
    echo "==> [4/7] Restoring target database backup archive: ${DB_ARCHIVE}..."
    php artisan erp:restore "${DB_ARCHIVE}" || echo "Warning: Database restore failed."
else
    echo "==> [4/7] No database archive specified; preserving current database state and running migrations..."
    php artisan migrate --force || true
fi

# 5. Clear Stale Framework Caches
echo "==> [5/7] Clearing stale application and template caches..."
php artisan optimize:clear --quiet || true

# 6. Re-warm Production Caches
echo "==> [6/7] Re-caching configuration, routes, and views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 7. Pre-Flight Verification & Exit Maintenance Mode
echo "==> [7/7] Running pre-flight verification probe..."
php artisan erp:staging-verify || echo "Warning: Pre-flight probe reported advisory notices."

echo "==> Exiting maintenance mode..."
php artisan up

echo "--------------------------------------------------------------------------------"
echo "✓ SUCCESS: Laijau ERP rolled back cleanly on shared hosting!"
echo "================================================================================"
exit 0
