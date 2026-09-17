#!/usr/bin/env bash
# ==============================================================================
# Laijau ERP — Production Packaging Script (Preserving Relative Symlinks)
# ==============================================================================
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "${APP_DIR}"

OUTPUT_DIR="${APP_DIR}/release"
mkdir -p "${OUTPUT_DIR}"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
OUTPUT_ZIP="${OUTPUT_DIR}/laijau_production_${TIMESTAMP}.zip"

echo "================================================================================"
echo "          LAIJAU ERP — ZERO-DUPLICATION PACKAGING UTILITY"
echo "================================================================================"
echo "App Directory:  ${APP_DIR}"
echo "Target Archive: ${OUTPUT_ZIP}"
echo "--------------------------------------------------------------------------------"

# 1. Ensure relative symlink
echo "==> Ensuring public/storage is a relative shortcut..."
php artisan storage:link --relative --force --quiet || true

# 2. Package using zip preserving symlinks (-y) and excluding bloat & duplicate public/storage
echo "==> Packaging application archive..."
zip -r -y -q "${OUTPUT_ZIP}" . \
    -x ".git/*" \
    -x ".github/*" \
    -x ".idea/*" \
    -x ".vscode/*" \
    -x ".cursor/*" \
    -x ".zed/*" \
    -x "node_modules/*" \
    -x "tests/*" \
    -x "scratch/*" \
    -x "release/*" \
    -x "storage/logs/*" \
    -x "storage/framework/cache/data/*" \
    -x "storage/framework/sessions/*" \
    -x "storage/framework/views/*" \
    -x "storage/pail/*" \
    -x "storage/app/backups/*" \
    -x "public/storage/*" \
    -x ".env" \
    -x ".env.*" \
    -x "*.sqlite*" \
    -x "*.sql" \
    -x "*.dump" \
    -x "*.zip" \
    -x "*.tar.gz" \
    -x "*.enc" \
    -x ".DS_Store" \
    -x "Thumbs.db"

# 3. Add back environment example templates
zip -q "${OUTPUT_ZIP}" .env.example .env.production.example 2>/dev/null || true

SIZE=$(du -h "${OUTPUT_ZIP}" | cut -f1)
echo "--------------------------------------------------------------------------------"
echo "✓ SUCCESS: Production release package created: ${OUTPUT_ZIP} (${SIZE})"
echo "✓ Symlinks preserved as shortcuts (-y). Zero duplicated media files."
echo "================================================================================"
