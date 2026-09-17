# ==============================================================================
# Laijau ERP — Comprehensive cPanel / Shared Hosting Deployment Guide
# ==============================================================================

This guide provides step-by-step instructions for deploying Laijau Enterprise ERP on ordinary shared hosting or cPanel without root access, Docker, Redis, or Supervisor.

---

## 1. Prerequisites in cPanel

Before uploading the application:
1. **MultiPHP Manager**:
   - Navigate to **cPanel $\rightarrow$ Software $\rightarrow$ MultiPHP Manager**.
   - Select your domain and set the PHP version to **PHP 8.4**.
2. **PHP Extensions (Select PHP Version / MultiPHP INI)**:
   - Ensure the following core extensions are enabled:
     - `bcmath`, `fileinfo`, `gd`, `intl`, `mbstring`, `openssl`, `pdo_mysql`, `zip`, `opcache`.
   - Recommended PHP INI values:
     - `memory_limit = 256M` (or 512M)
     - `upload_max_filesize = 25M`
     - `post_max_size = 25M`
     - `max_execution_time = 120`
3. **Database Creation**:
   - Navigate to **cPanel $\rightarrow$ Databases $\rightarrow$ MySQL Database Wizard**.
   - Create database: e.g. `laijauco_laijau`.
   - Create user: e.g. `laijauco_dbuser` with a strong password.
   - Assign user to database with **ALL PRIVILEGES**.

---

## 2. Directory Layout Options

Choose one of two standard cPanel deployment architectures:

### Option A: Clean Subdirectory Isolation (Strongly Recommended)
Place the application files outside the webroot for maximum security:
```
/home/username/
├── laijau/                    <-- All Laravel application files
│   ├── app/
│   ├── bootstrap/
│   ├── config/
│   ├── database/
│   ├── public/
│   ├── storage/
│   └── .env
└── public_html/               <-- Symlink to /home/username/laijau/public
```
To link `public_html`:
```bash
cd /home/username
rm -rf public_html
ln -s laijau/public public_html
```

### Option B: All-in-One in `public_html` (With Root .htaccess Shield)
If your host prevents creating symlinks or changing document roots:
- Upload all files directly into `/home/username/public_html/`.
- The repository includes a root [`.htaccess`](file:///.htaccess) file that automatically routes visitors to `public/` and blocks direct access to `.env`, `storage/`, `app/`, `bootstrap/`, `vendor/`.

---

## 3. Environment Configuration (`.env`)

1. Copy `.env.production.example` to `.env`:
   ```bash
   cp .env.production.example .env
   ```
2. Open `.env` in cPanel File Manager Code Editor:
   - Set `APP_ENV=production` and `APP_DEBUG=false`.
   - Generate app key: `php artisan key:generate`.
   - Enter your database credentials:
     ```env
     DB_CONNECTION=mysql
     DB_HOST=localhost
     DB_PORT=3306
     DB_DATABASE=laijauco_laijau
     DB_USERNAME=laijauco_dbuser
     DB_PASSWORD=your_mysql_password
     ```
   - Shared hosting queue and cache settings:
     ```env
     QUEUE_CONNECTION=database
     CACHE_STORE=file
     SESSION_DRIVER=file
     SESSION_ENCRYPT=true
     ```
   - Set a strong secret for encrypted backups:
     ```env
     BACKUP_ENCRYPTION_KEY=your_secure_random_string
     ```

---

## 4. Install Dependencies & Initialize Database

Open **cPanel Terminal** (or SSH):
```bash
cd /home/username/laijau

# Install optimized production dependencies
composer install --no-dev --optimize-autoloader

# Run database migrations
php artisan migrate --force

# Link public storage (relative shortcut)
php artisan storage:link --relative --force

# Cache configurations for speed
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Run operational pre-flight test
php artisan erp:staging-verify
```

---

## 5. Setup cPanel Cron Job

To run the scheduler (which automatically processes the database queue, runs daily backups, and performs cleanup):
1. Navigate to **cPanel $\rightarrow$ Advanced $\rightarrow$ Cron Jobs**.
2. Select **Once Per Minute** (`* * * * *`).
3. Add the command:
   ```bash
   cd /home/username/laijau && /usr/local/bin/php artisan schedule:run >> /dev/null 2>&1
   ```
4. Click **Add New Cron Job**.

> [!NOTE]
> When `QUEUE_CONNECTION=database`, every time `schedule:run` triggers, it executes `php artisan queue:work --stop-when-empty --max-time=50`, draining order fulfillment and email notification jobs within 60 seconds without requiring a Supervisor daemon!

---

## 6. Future Updates & Continuous Deployment

Whenever you upload a new release or pull updates via Git, run the automated deployment script:
```bash
bash deploy/cpanel/cpanel-deploy.sh
```
This handles maintenance mode, backups, migrations, cache warming, and operational verification in one atomic command.

---

## 7. Zero Persistent Process Architecture Guarantee

Laijau strictly adheres to a shared-hosting process compliance policy:
> **Laijau maintains zero persistent application-owned processes while idle. All Laijau execution is request-driven or cron-triggered and terminates after completing its work.**

- **Application-Owned Process Statelessness**: While the hosting provider operates underlying web server processes, Laijau runs no background PHP worker loops (`while (true)`), Supervisor, Docker, Redis, or Node daemons.
- **Intentional Data Persistence**: Durable data is safely stored in MySQL/MariaDB, uploaded media (`storage/app/public/`), file sessions/cache (`storage/framework/`), logs (`storage/logs/`), and backups (`storage/app/backups/`).
- **On-Demand Queue Processing**: Database queue jobs are drained on a 1-minute cadence via cPanel Cron (`queue:work --stop-when-empty --max-time=50 --tries=3`), terminating with exit code `0` when empty.
- **Synchronous Scheduler Tasks**: Backups, health checks, model pruning, and cleanup run synchronously within the scheduled invocation.
- **Predictable Resource Footprint**: Eliminates memory fragmentation and prevents cPanel CloudLinux resource throttling (`max_user_processes`, memory kill thresholds).

---

## 8. Real Shared-Hosting Staging Drill & Scorecard

Before cutting over to live traffic, execute the comprehensive staging drill:
```bash
# Execute the drill with automatic post-drill cleanup
php artisan erp:staging-drill --cleanup
```

The drill evaluates hosting dimensions (PHP 8.4, Apache, MySQL, migrations, uploads, checkout, webhooks, queues, backups, health endpoint, encryption, security headers, permissions, zero persistent daemons, deploy/rollback scripts, application smoke test, and resource quotas).

The command outputs an authoritative scorecard verdict:
- **`[ GO LIVE ]`**: All probes passed. Ready for production cutover.
- **`[ FIX ]`**: Non-blocking advisories detected. Review before proceeding.
- **`[ ROLLBACK ]`**: Critical failures detected. Do not go live.

Refer to [REAL_HOST_STAGING_SCORECARD.md](file:///media/arikar/laijau/Final%20Projects/Laijau/deploy/cpanel/REAL_HOST_STAGING_SCORECARD.md) for the complete scorecard specification.

---

## 9. Staging Account Teardown & Sweep

To clean up all temporary drill files, canary upload items, synthetic webhook records, and staging queue items at any time:
```bash
php artisan erp:staging-cleanup --force
```
This guarantees the shared-hosting account is pristine and free of residual test artifacts.

---

## 10. Instant Shared-Hosting Rollback Automation

If an update fails or a critical operational bug is detected in production, execute the automated rollback script:
```bash
bash deploy/cpanel/cpanel-rollback.sh
```
This automatically:
1. Enters emergency maintenance mode.
2. Captures an emergency state backup.
3. Reverts git to the previous commit (or designated revision).
4. Restores a target database backup archive (if specified).
5. Clears stale application caches and re-warms production caches.
6. Verifies system health and exits maintenance mode.
