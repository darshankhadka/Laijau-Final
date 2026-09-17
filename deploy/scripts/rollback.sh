#!/usr/bin/env bash
# ==============================================================================
# Laijau ERP — Instant Release Rollback Script
# ==============================================================================
set -euo pipefail

BASE_DIR="${DEPLOY_BASE_DIR:-/var/www/laijau}"
RELEASES_DIR="${BASE_DIR}/releases"
CURRENT_LINK="${BASE_DIR}/current"

echo "==> [Laijau] Initiating Instant Release Rollback"

if [[ ! -L "${CURRENT_LINK}" ]]; then
    echo "ERROR: Current release link '${CURRENT_LINK}' is not a symlink. Aborting."
    exit 1
fi

CURRENT_TARGET=$(readlink -f "${CURRENT_LINK}")
CURRENT_NAME=$(basename "${CURRENT_TARGET}")

# Find candidate releases sorted in descending chronological order
cd "${RELEASES_DIR}"
RELEASES=($(ls -1dt 20* 2>/dev/null))

if [[ ${#RELEASES[@]} -lt 2 ]]; then
    echo "ERROR: Only ${#RELEASES[@]} release(s) found in '${RELEASES_DIR}'. Cannot roll back."
    exit 1
fi

PREVIOUS_RELEASE=""
for rel in "${RELEASES[@]}"; do
    if [[ "${rel}" != "${CURRENT_NAME}" ]]; then
        PREVIOUS_RELEASE="${RELEASES_DIR}/${rel}"
        break
    fi
done

if [[ -z "${PREVIOUS_RELEASE}" || ! -d "${PREVIOUS_RELEASE}" ]]; then
    echo "ERROR: Could not locate a valid previous release. Aborting."
    exit 1
fi

echo "==> Current active release:  ${CURRENT_NAME}"
echo "==> Rolling back to release: $(basename "${PREVIOUS_RELEASE}")"

# Atomically flip symlink back to the previous release
ln -sfn "${PREVIOUS_RELEASE}" "${CURRENT_LINK}"

# Re-warm production caches
echo "==> [Laijau] Re-caching configuration on rolled back release"
cd "${CURRENT_LINK}"
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart Queue workers and reload PHP-FPM
echo "==> [Laijau] Signaling queue workers to recycle"
php artisan queue:restart

if command -v systemctl >/dev/null 2>&1; then
    echo "==> [Laijau] Reloading PHP-FPM service"
    sudo systemctl reload php8.4-fpm 2>/dev/null || sudo systemctl reload php-fpm 2>/dev/null || true
fi

echo "==> [Laijau] Rollback successful! Current active release is now $(basename "${PREVIOUS_RELEASE}")."
