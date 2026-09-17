# Laijau Enterprise ERP & Commerce Platform

Laijau is a high-performance, enterprise-grade ERP and retail commerce platform built on Laravel 11 and PHP 8.4 for the Nepal market, fully adapted for reliable operation on **cPanel / shared-hosting infrastructure**.

---

## Architectural Philosophy

$$\mathbf{Zero\ Persistent\ Application\text{-}Owned\ Processes}\quad+\quad\mathbf{On\text{-}Demand\ Execution}\quad+\quad\mathbf{cPanel\ Cron}\quad+\quad\mathbf{MySQL}\quad+\quad\mathbf{Apache}$$

> **Laijau maintains zero persistent application-owned processes while idle. All execution is request-driven or cron-triggered and terminates after completing its work.**

### Runtime Contract
- **Web Requests**: Apache $\rightarrow$ PHP 8.4 $\rightarrow$ Laravel $\rightarrow$ Response $\rightarrow$ **Process Ends**
- **Queue Draining**: cPanel Cron $\rightarrow$ `queue:work --stop-when-empty --max-time=50` $\rightarrow$ **Worker Exits**
- **Scheduled Maintenance**: cPanel Cron $\rightarrow$ Backups / health / prune $\rightarrow$ **Process Exits**
- **Idle State**: 0 application-owned processes, 0 queue workers, 0 daemons.

For full details, review the [Operational Architecture Contract](file:///media/arikar/laijau/Final%20Projects/Laijau/deploy/cpanel/OPERATIONAL_CONTRACT.md).

---

## Core Capabilities

1. **Configuration Control Plane**:
   - Centralized, audited module settings across Commerce, Inventory, Purchasing, POS, Shipping, HRM, and Accounting.
   - Nepal Labour Act 2074 HRM compliance, Nepal local courier logistics, NPR currency architecture.
2. **Authorization & IDOR Protection**:
   - Explicit policies, fine-grained permissions, roles, and page-level access gates.
3. **Database & Transaction Integrity**:
   - Pessimistic locking (`lockForUpdate()`), deadlock-free deterministic lock ordering, atomic sequential numbering (`DocumentSequenceService`).
4. **Production Reliability & Observability**:
   - Automated health probes (`/api/health`), AES-256-CBC encrypted backups and disaster recovery restore engine (`erp:backup`, `erp:restore`), SSRF defense, and structured audit logging.
5. **Shared Hosting & cPanel Deployment**:
   - Root and public `.htaccess` security shields, one-command deployment (`cpanel-deploy.sh`), emergency rollback (`cpanel-rollback.sh`), staging drill (`erp:staging-drill`), and post-drill account cleanup (`erp:staging-cleanup`).

---

## Deployment to cPanel

Refer to the authoritative guides:
- [cPanel Deployment Guide](file:///media/arikar/laijau/Final%20Projects/Laijau/deploy/cpanel/CPANEL_DEPLOYMENT_GUIDE.md)
- [Real-Host Staging Scorecard](file:///media/arikar/laijau/Final%20Projects/Laijau/deploy/cpanel/REAL_HOST_STAGING_SCORECARD.md)
- [Operational Contract ADR](file:///media/arikar/laijau/Final%20Projects/Laijau/deploy/cpanel/OPERATIONAL_CONTRACT.md)

### Deployment Commands
```bash
# 1. Deploy or update release
bash deploy/cpanel/cpanel-deploy.sh

# 2. Run staging verification
php artisan erp:staging-verify

# 3. Emergency rollback (if needed)
bash deploy/cpanel/cpanel-rollback.sh
```
