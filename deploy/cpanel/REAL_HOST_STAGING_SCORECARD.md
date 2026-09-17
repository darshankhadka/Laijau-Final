# Laijau ERP — Real Shared-Hosting Staging Scorecard & Runbook

## Overview

This represents the **live shared-hosting pre-flight verification gate**. It evaluates the deployed application against real cPanel constraints across **18 operational dimensions**, calculates runtime resource consumption against cPanel quotas, renders an authoritative verdict (**GO LIVE / FIX / ROLLBACK**), and automatically executes a **post-drill teardown** to leave the hosting account clean.

---

## Operational Architecture & Strict Process Governance

$$\mathbf{Zero\ Persistent\ Application\text{-}Owned\ Processes}\quad+\quad\mathbf{On\text{-}Demand\ Execution}\quad+\quad\mathbf{cPanel\ Cron}\quad+\quad\mathbf{MySQL}\quad+\quad\mathbf{Apache}$$

### The Core Operational Guarantee
> [!IMPORTANT]
> **Laijau maintains zero persistent application-owned processes while idle. All Laijau execution is request-driven or cron-triggered and terminates after completing its work.**

While the hosting provider manages underlying Apache and PHP-FPM processes, Laijau itself operates completely stateless at the application-process level. Intentional data persistence is strictly isolated to durable storage layers:
- **MySQL / MariaDB**: Relational entities, transaction logs, document sequences, accounting ledgers.
- **Uploaded Media**: `storage/app/public/` (accessible via symlinked `public/storage`).
- **File Sessions & Caches**: `storage/framework/sessions/` and `storage/framework/cache/`.
- **Queue Records**: `jobs` and `failed_jobs` database tables.
- **System Logs**: `storage/logs/laravel.log`.
- **Safety Backups**: `storage/app/backups/`.

### What Is Strictly Prohibited on Shared Hosting
- ❌ **No Supervisor daemons**
- ❌ **No Redis background processes**
- ❌ **No Docker daemons or containers**
- ❌ **No systemd background services**
- ❌ **No permanently running queue workers** (`queue:work` without `--stop-when-empty`)
- ❌ **No Node background processes**
- ❌ **No development servers** (`php artisan serve`)
- ❌ **No `nohup`, `&`, or detached shell background jobs**
- ❌ **No long-running application daemons**

### Three Clean Lifecycle Pipelines

#### 1. Storefront & API Web Request Lifecycle
```
HTTP Request ──► Apache ──► PHP 8.4 ──► Laravel ──► HTTP Response ──► PROCESS ENDS
```

#### 2. Database Queue Processing Lifecycle (cPanel Cron)
```
cPanel Cron (* * * * *)
       │
       ▼
schedule:run
       │
       ▼
queue:work --stop-when-empty --max-time=50 --tries=3
       │
       ▼
Process pending jobs (orders, emails, inventory)
       │
       ▼
Queue empty or 50s reached ──► Worker Exits Cleanly (Exit 0) ──► ZERO PROCESSES REMAIN
```

#### 3. Scheduled Maintenance Lifecycle (Backups, Pruning, Health)
```
cPanel Cron (Daily / Nightly)
       │
       ▼
Backup / cleanup / model:prune / health checks
       │
       ▼
Task completes synchronously ──► Process Exits Cleanly ──► ZERO PROCESSES REMAIN
```

---

## How to Execute the Staging Drill

From **cPanel Terminal** or via **SSH** in the project directory:

```bash
# Full 18-Probe Drill with physical encrypted backup and automatic teardown
php artisan erp:staging-drill --cleanup

# Fast 18-Probe Drill (skipping large physical backup generation)
php artisan erp:staging-drill --skip-backup --cleanup
```

---

## The 18 Real-Host Verification Probes

| # | Staging Probe | Scope & Pass Criteria |
|---|---|---|
| **1** | **PHP 8.4 & Extensions** | Verifies `PHP_VERSION_ID >= 80400` and all 9 required extensions (`pdo_mysql`, `bcmath`, `gd`, `intl`, `zip`, `pcntl`, `openssl`, `mbstring`, `curl`). |
| **2** | **Apache + .htaccess** | Asserts root `.htaccess` shield and `public/.htaccess` gateway exist with `RewriteEngine On` and block rules for `.env`, `storage/`, and sensitive files. |
| **3** | **MySQL/MariaDB Engine** | Measures PDO connection latency ($< 150\text{ms}$), verifies active driver, and checks InnoDB transaction support. |
| **4** | **Laravel Migrations** | Asserts `migrations` table exists and verifies core domain tables (`users`, `orders`, `products`, `settings`, `accounting_journal_entries`, `offline_sales`). |
| **5** | **Storage & Uploads** | Tests physical disk write to `storage/app/public/staging-drill/`, verifies file readability, public symlink mapping, and marks for automated teardown. |
| **6** | **Checkout & Payments** | Validates Nepal tax calculation engine, NPR currency handling, and payment gateways. |
| **7** | **Webhooks** | Simulates a webhook event, writes to `webhook_events`, verifies dual-layer idempotency detection, and marks for cleanup. |
| **8** | **Queue + Cron** | Executes `queue:work --stop-when-empty --max-time=50`, confirms clean termination with exit code `0`, and verifies zero persistent workers. |
| **9** | **Scheduled Backups** | Executes an on-demand database backup, verifies AES-256 encryption, manifest generation, and archive creation in `storage/app/backups/`. |
| **10** | **Health Endpoint** | Calls `HealthCheckService::check()` and asserts all subsystems (database, cache, storage, migrations, queue, accounting ledger) report `healthy`. |
| **11** | **Encrypted Restore** | Asserts `ZipArchive` and `openssl` `aes-256-cbc` ciphers are available for disaster recovery restoration. |
| **12** | **Security Headers** | Verifies HSTS (`Strict-Transport-Security`), CSP, `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, and `Referrer-Policy`. |
| **13** | **File Permissions** | Verifies `storage/` and `bootstrap/cache/` are writable ($0775$), and `.env` has restricted permissions ($\le 0640$). |
| **14** | **Zero Persistent Daemons** | Confirms active queue driver is stateless (`database` or `sync`) with zero background process requirements. |
| **15** | **Deployment Automation** | Inspects `deploy/cpanel/cpanel-deploy.sh`: validates `set -euo pipefail`, maintenance mode toggles (`down`/`up`), and zero detached background operators (`&`, `nohup`). |
| **16** | **Rollback Automation** | Inspects `deploy/cpanel/cpanel-rollback.sh`: validates emergency safety backup, git revision revert, database backup restore flow, and cache re-warming. |
| **17** | **Application Smoke Test** | Dispatches internal sub-requests across core storefront routes (`/`, `/cart`, `/api/health`) and asserts clean HTTP 200 responses without crashes. |
| **18** | **Resource & Quotas** | Measures RAM peak usage ($< 256\text{MB}$), disk free space, execution duration, and compares against shared-hosting quotas. |

---

## Decision Scorecard Matrix

```
================================================================================
                      STAGING DRILL DECISION SCORECARD                          
================================================================================
```

| Decision | Condition | Action Required |
|---|---|---|
| **`GO LIVE`** | **0 Failures**, **0 Warnings** | Environment is 100% certified ready for production. Proceed with DNS cutover or client handoff. |
| **`FIX`** | **0 Failures**, $\ge 1$ Warnings | Non-blocking advisories detected. Review advisories and remediate. |
| **`ROLLBACK`** | $\ge 1$ Failures | Blocking operational failure detected. **Do not cut over**. Execute `deploy/cpanel/cpanel-rollback.sh` immediately. |

---

## Automated Post-Drill Teardown & Account Sweep

When the `--cleanup` flag is used with `erp:staging-drill`, or when executing:

```bash
php artisan erp:staging-cleanup --force
```

The system automatically sweeps:
1. `storage/app/public/staging-drill/`: Deletes all canary upload files and directories.
2. `storage/app/backups/`: Deletes all temporary staging drill backup archives.
3. Webhook test events: Deletes synthetic test webhook records.
4. `failed_jobs` & `jobs`: Prunes staging canary jobs.
5. Framework cache: Clears temporary canary keys.

This guarantees that testing leaves **zero residual files or database records** on the live shared-hosting account.

---

## Emergency Rollback Runbook

If `erp:staging-drill` reports `ROLLBACK` or an unexpected issue occurs post-deployment:

```bash
# Roll back to previous git commit and previous verified database backup
bash deploy/cpanel/cpanel-rollback.sh

# Or specify an explicit target commit:
bash deploy/cpanel/cpanel-rollback.sh HEAD~1

# Or specify an explicit target commit and backup archive:
bash deploy/cpanel/cpanel-rollback.sh HEAD~1 storage/app/backups/backup_db_2026-09-07_020000.zip
```
