# LAIJAU.COM — PRODUCTION HOSTING & DEPLOYMENT MANUAL

This guide details the complete, step-by-step setup required to host and run **`https://laijau.com`** on production infrastructure with zero-downtime releases, automated SSL, background workers, and daily backups.

---

## 1. Production Server Architecture Overview

- **Domain**: `laijau.com` (Apex) & `www.laijau.com`
- **Application Root**: `/var/www/laijau/current`
- **Shared Persistent State**: `/var/www/laijau/shared` (`.env`, storage, logs, media)
- **Web Server**: Nginx (HTTP/2, SSL termination, rate-limiting)
- **Application Runtime**: PHP 8.4-FPM with Zend OPcache
- **Database**: MySQL 8.0 or MariaDB 10.11+
- **Cache & Queues**: Database or Redis 7.x
- **Background Jobs**: Supervisor (`laijau-worker-default`, `laijau-worker-fulfillment`)
- **Scheduler**: Linux Cron (`schedule:run`)

---

## 2. Server Prerequisites & Package Installation

On Ubuntu 24.04 / 22.04 LTS:

```bash
# Update repositories
sudo apt update && sudo apt upgrade -y

# Install Essential Tools
sudo apt install -y curl git unzip ufw certbot python3-certbot-nginx supervisor rsync

# Install PHP 8.4 and required extensions
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update
sudo apt install -y php8.4-fpm php8.4-cli php8.4-mysql php8.4-mbstring \
                    php8.4-xml php8.4-bcmath php8.4-curl php8.4-zip \
                    php8.4-intl php8.4-gd php8.4-opcache php8.4-redis

# Install Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

---

## 3. Directory Layout & Permissions

Run the following commands on the production host to create the isolated directory layout:

```bash
sudo mkdir -p /var/www/laijau/{releases,shared}
sudo mkdir -p /var/www/laijau/shared/storage/{app/public,framework/cache,framework/sessions,framework/views,logs,backups}
sudo mkdir -p /var/www/laijau/shared/certs

# Assign web server ownership
sudo chown -R www-data:www-data /var/www/laijau
sudo chmod -R 775 /var/www/laijau/shared/storage
```

---

## 4. Production Environment Configuration (`.env`)

1. Copy `.env.production.example` to `/var/www/laijau/shared/.env`:
   ```bash
   sudo cp deploy/.env.production.example /var/www/laijau/shared/.env
   sudo chown www-data:www-data /var/www/laijau/shared/.env
   sudo chmod 600 /var/www/laijau/shared/.env
   ```

2. Edit `/var/www/laijau/shared/.env` and configure:
   - `APP_KEY`: Generate using `php artisan key:generate --show`
   - `APP_URL`: `https://laijau.com`
   - `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`: Live database credentials
   - `MAIL_PASSWORD`: SMTP password for `info@laijau.com`
   - `NCM_API_TOKEN` & `PATHAO_CLIENT_SECRET`: When activating live courier dispatch
   - `CONNECTIPS_*`: Live merchant credentials when ConnectIPS agreement goes live

---

## 5. Nginx & SSL Configuration

1. Copy the Nginx configuration:
   ```bash
   sudo cp deploy/nginx/laijau.conf /etc/nginx/sites-available/laijau.conf
   sudo ln -s /etc/nginx/sites-available/laijau.conf /etc/nginx/sites-enabled/
   ```

2. Obtain Let's Encrypt SSL Certificate:
   ```bash
   sudo certbot --nginx -d laijau.com -d www.laijau.com
   ```

3. Test and reload Nginx:
   ```bash
   sudo nginx -t
   sudo systemctl reload nginx
   ```

---

## 6. Supervisor Background Queue Workers

1. Copy the Supervisor configuration:
   ```bash
   sudo cp deploy/supervisor/laijau-workers.conf /etc/supervisor/conf.d/laijau-workers.conf
   ```

2. Reload and start Supervisor:
   ```bash
   sudo supervisorctl reread
   sudo supervisorctl update
   sudo supervisorctl start all
   ```

---

## 7. Linux Crontab (Laravel Task Scheduler)

Add the Laravel scheduler to `www-data`'s crontab:

```bash
sudo crontab -u www-data -e
```

Add this single line:

```cron
* * * * * cd /var/www/laijau/current && php artisan schedule:run >> /dev/null 2>&1
```

---

## 8. Deployment Execution

To deploy new releases with zero downtime:

```bash
cd /media/arikar/laijau/Final\ Projects/Laijau
bash deploy/scripts/deploy-laijau.sh
```

Or when deploying via CI/CD (GitHub Actions / GitLab CI):
- Run `npm run build`
- Archive artifact (`.tar.gz`)
- Run `deploy-laijau.sh` on the remote server

---

## 9. Post-Deployment Smoke Test Checklist

Once the server is live:
1. Access `https://laijau.com` & verify SSL padlock.
2. Confirm `https://www.laijau.com` redirects 301 to `https://laijau.com`.
3. Check `https://laijau.com/sitemap.xml` returns valid XML.
4. Check `https://laijau.com/robots.txt` protects admin routes.
5. Log into Admin Panel at `https://laijau.com/admin`.
6. Verify live inventory numbers and GL balance with:
   ```bash
   php /var/www/laijau/current/artisan laijau:final-audit
   ```
