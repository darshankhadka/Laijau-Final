# ==============================================================================
# Laijau ERP — Production Go-Live & Cutover Runbook
# ==============================================================================

This runbook outlines the authoritative, step-by-step procedures for deploying Laijau Enterprise ERP to production with zero downtime, zero data loss, and immediate rollback capability.

---

## 1. Phase 1: Pre-Flight Certification Gate

Execute these commands before initiating any production release:

```bash
# 1. Verify Operational Staging Pre-Flight
php artisan erp:staging-verify --strict

# 2. Verify Security Audit & Secret Leak Guards
php artisan erp:security-audit

# 3. Verify Shell Scripts Syntax
bash -n deploy/scripts/deploy.sh
bash -n deploy/scripts/rollback.sh
```

> [!CAUTION]
> If any check fails, or if `erp:staging-verify` reports blocking errors, **HALT CUTOVER IMMEDIATELY**. Do not proceed to Phase 2.

---

## 2. Phase 2: Production Server Environment & Secrets Setup

Ensure the target server directory structure and secrets exist:

```bash
# Target Base Directory: /var/www/laijau
sudo mkdir -p /var/www/laijau/{releases,shared}
sudo mkdir -p /var/www/laijau/shared/storage/{app/public,framework/cache,framework/sessions,framework/views,logs,backups}
sudo chown -R www-data:www-data /var/www/laijau
sudo chmod -R 775 /var/www/laijau/shared/storage

# Provision Production Secrets (NEVER COMMIT TO GIT)
sudo cp .env.production.example /var/www/laijau/shared/.env
sudo chmod 600 /var/www/laijau/shared/.env
# Edit /var/www/laijau/shared/.env with live credentials
```

---

## 3. Phase 3: Pre-Deployment Encrypted Backup Drill

Before touching live schemas or code, take an encrypted snapshot of current production state:

```bash
# Run full database + media backup with AES-256 encryption
php artisan erp:backup --type=all --encrypt

# Verify dry-run against the created archive
php artisan erp:restore <ARCHIVE_NAME>.enc --dry-run
```

---

## 4. Phase 4: Zero-Downtime Deployment Execution

Execute the atomic release script:

```bash
cd /var/www/laijau
bash deploy/scripts/deploy.sh
```

**What `deploy.sh` executes automatically**:
1. Creates `/var/www/laijau/releases/<TIMESTAMP>`.
2. Links shared persistent `/shared/storage` and `/shared/.env`.
3. Runs `composer install --no-dev --optimize-autoloader`.
4. Executes `php artisan migrate --force`.
5. Warms production bytecode and framework caches (`config:cache`, `route:cache`, `view:cache`).
6. Atomically updates symlink `/var/www/laijau/current`.
7. Signals queue workers to recycle (`queue:restart`).
8. Reloads PHP 8.4-FPM gracefully.
9. Purges old releases, keeping the last 5 versions.

---

## 5. Phase 5: Post-Deployment Smoke Verification

Verify production operational health immediately after symlink flip:

```bash
# 1. Query Health Endpoint (Must return HTTP 200 with status: healthy)
curl -s -i https://laijau.com/api/health

# 2. Verify Nginx Security Headers
curl -s -I https://laijau.com | grep -E "(Strict-Transport-Security|Content-Security-Policy|X-Frame-Options)"

# 3. Check Queue Workers Active
sudo supervisorctl status

# 4. Check Scheduler Heartbeat
php /var/www/laijau/current/artisan erp:health-ping
```

---

## 6. Phase 6: Emergency Rollback Procedure

If severe anomalies or errors are detected post-cutover:

```bash
# 1. Instant Application Rollback (Atomically reverts symlink to previous release)
bash deploy/scripts/rollback.sh

# 2. Verify Health Restored
curl -s https://laijau.com/api/health

# 3. Database Disaster Restoration (Only if catastrophic schema corruption occurred)
php artisan erp:restore <PRE_CUTOVER_BACKUP>.enc --decrypt-key=<KEY> --force
```
