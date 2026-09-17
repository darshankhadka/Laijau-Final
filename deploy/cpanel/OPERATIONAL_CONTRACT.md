# Architecture Decision Record: Zero Persistent Process Operational Contract

## Status
**ACCEPTED & LOCKED** (Laijau Enterprise Architecture)

---

## Context & Motivation
Laijau Enterprise ERP is deployed to ordinary shared hosting running on cPanel (Apache 2.4, PHP 8.4, MySQL/MariaDB 10.6+). 

Shared hosting environments operate under strict CloudLinux / cPanel resource governance:
- Process table limits (`max_user_processes`)
- Ephemeral memory ceilings (e.g. 512MB–1024MB per cPanel user)
- Automated cPanel process killers that terminate long-running processes or background loops
- Absence of root privileges, preventing systemd or Supervisor management

Attempting to run long-running daemons on shared hosting leads to process throttling, random task kills, memory leaks, and silent queue failures.

---

## The Core Invariant

$$\mathbf{Zero\ Persistent\ Application\text{-}Owned\ Processes}\quad\neq\quad\mathbf{Zero\ Persistent\ Data}$$

> [!IMPORTANT]
> **Operational Guarantee**:
> **Laijau maintains zero persistent application-owned processes while idle. All Laijau execution is request-driven or cron-triggered and terminates after completing its work.**

- **Application-Owned Process Layer (Ephemeral)**: While the hosting provider manages underlying Apache/PHP-FPM worker pools, Laijau itself maintains strictly **zero persistent application-owned processes** while idle.
- **Data Layer (Durable)**: Persistent business state is deliberately and safely stored in MySQL/MariaDB, uploaded media, file sessions/cache, database queues, logs, and backups.

---

## When Laijau Is Completely Idle

```
Laijau-owned processes:    0
Queue workers:             0
Redis processes:           0
Node processes:            0
Docker containers:         0
Supervisor workers:        0
Octane / Swoole workers:   0
Background subshells:      0
```

Provider-managed Apache and PHP-FPM web servers remain ready to dispatch incoming requests, but no application-owned background daemons sit idle consuming CPU or RAM.

---

## Three Clean Lifecycle Pipelines

### 1. Normal Customer / Admin Request
```
HTTP Request ──► Apache ──► PHP 8.4 ──► Laravel ──► Response ──► PROCESS ENDS
```

### 2. Database Queue Processing (cPanel Cron)
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

### 3. Scheduled Maintenance (Backups, Cleanup, Health Checks)
```
cPanel Cron (Scheduled)
       │
       ▼
Backup / cleanup / model:prune / health-ping
       │
       ▼
Task completes synchronously ──► Process Exits Cleanly ──► ZERO PROCESSES REMAIN
```

---

## Strictly Prohibited "Improvements"

Future engineers and contributors are **strictly forbidden** from introducing any of the following without an explicit, formal architecture migration to dedicated VPS or Kubernetes infrastructure:

| Prohibited Component | Why It Is Forbidden on Shared Hosting |
|---|---|
| **Permanent `queue:work`** | Running `queue:work` without `--stop-when-empty` creates an infinite loop that leaks memory and gets killed by cPanel. |
| **Supervisor / systemd** | Requires root access and persistent daemons not supported on shared hosting accounts. |
| **Redis Daemon** | In-memory server daemon consuming memory when idle; replaced with native MySQL and file drivers. |
| **WebSockets / Soketi / Node server** | Long-running TCP listener requiring background daemons. |
| **Laravel Octane** | FrankenPHP, Swoole, or RoadRunner workers sit continuously in memory; incompatible with shared Apache/PHP-FPM. |
| **Development Server (`php artisan serve`)** | Single-threaded test server never permitted in production. |
| **Background Shell Hacks (`nohup`, `&`, `disown`)** | Spawns unmonitored orphan subshells that evade cPanel process management and resource accounting. |
| **Infinite loops in CLI commands** | Any `while (true)` or `sleep()` loop blocking execution. |

---

## Testing & Compliance Enforcement

This contract is enforced via automated test gates:
- `tests/Feature/Operational/ZeroPersistentProcessComplianceTest.php`
- `tests/Feature/Operational/SharedHostingStagingDrillTest.php`
- `tests/Feature/Operational/SharedHostingAdaptationTest.php`
- `app/Console/Commands/StagingDrillCommand.php`
- `app/Console/Commands/StagingVerificationCommand.php`
