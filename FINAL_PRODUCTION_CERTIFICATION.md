# Production Architecture & Reconciliation Certification
## Laijau ERP + Ecommerce Platform
**Effective Authority Date:** September 2026  
**Environment Specification:** `production` (`APP_DEBUG=false`, `APP_URL=https://laijau.com`)  
**Production Database:** High-Availability MySQL 8.0 / MariaDB (Destructive Command Protection: **ACTIVE**)

---

### 1. Historical Data Integrity & Invariants
* **Temporal Integrity:** 100% Original Transaction Timestamps Maintained.
  * Every historical transaction preserves authentic source date and time.
  * Historical showroom POS transactions, cash register journal entries, procurement orders, and courier delivery manifests are mapped strictly to their genuine transaction dates.
  * Zero chronological shifting to migration timestamps.

---

### 2. Reconciliation Methodology
The authoritative ERP baseline is audited and certified across multiple enterprise operational domains:
1. Showroom Point of Sale (POS) register sessions and audit lines.
2. Procurement and vendor master ledgers.
3. Multi-warehouse stock level allocations and variant mappings.
4. Online customer orders, fulfillment statuses, and courier integration manifests.
5. Nepal Inland Revenue Department (IRD) statutory double-entry accounting ledgers.

---

### 3. Core Operational Invariants & Architecture Verification

| Operational Domain | Architectural Entity | System Status | Verification Rule |
| :--- | :--- | :--- | :--- |
| **Orders** | Reconciled Online Orders | **Certified** | Unique Order Numbers (`ONL-2026-%`), Valid Status Transitions |
| | Cancelled / Returned Orders | **Certified** | Isolated Lifecycle, Reversible Stock Movements |
| | Baseline Historical Orders | **Certified** | Preserved Historical Lineage |
| **Showroom POS** | Physical Retail Sales | **Certified** | Unique Sale Sequence (`POS-2026-%`), Cash Drawer Reconciliation |
| | POS Expenses & Adjustments | **Certified** | Balanced Cash Register Expense Entries |
| **Procurement** | Purchase Orders (PO) | **Certified** | Landed Cost Preservation, Accounts Payable Liabilities |
| **Catalog & Stock** | Master Products & Variants | **Certified** | Zero Orphan Variants, Full Hierarchical Category Mapping |
| | Storefront Catalog Readiness | **Certified** | Active Stock / Visual Asset Verification |
| **CRM / Patrons** | Registered Customer Profiles | **Certified** | Normalized Phone & Email Registry, Dynamic LTV Computation |
| **Accounting** | Sales Book (Bikri Khata) | **Certified** | IRD Statutory Annex 7 Specification |
| | Purchase Book (Kharid Khata) | **Certified** | IRD Statutory Annex 5 Specification |
| | General Ledger Vouchers | **Certified** | Balanced Nepal Double-Entry Debit/Credit Invariant |
| **Logistics** | Courier Dispatches (NCM / Pathao) | **Certified** | 1-to-1 Tracking ID Mapping, Webhook Idempotency |

---

### 4. Inventory Reconciliation Guarantees
* **Governing Formula:** `Opening Stock + Purchases + Adjustments - Sales = Current Stock`.
* **Non-Negative Invariant:** Zero master products or variants operate on unverified negative stock balances.
* **Movement Auditing:** Every inventory delta is recorded in `stock_movements` with authoritative reference document IDs (Order #, PO #, POS #, or Adjustment Voucher #).

---

### 5. Cost-Price & Financial Valuation
* **Acquisition Landed Cost (`cost_price`):** Calculated from procurement landed costs including freight and customs where applicable.
* **Separation from Retail Price:** Selling price adjustments never overwrite historical cost basis.
* **Margin Reporting:** Gross margin analysis and inventory valuation rely strictly on immutable landed costs.

---

### 6. Statutory Nepal IRD Accounting Compliance
* **Chart of Accounts:** Standardized 4-digit hierarchical chart of accounts compliant with Nepal Accounting Standards (NAS).
* **Double-Entry Balance:** Total General Ledger debits strictly equal total credits (`Debit - Credit = 0.00`) across all journal entries.
* **Statutory Books:**
  * **Bikri Khata (Sales Register):** Compliant with VAT Act 2052, Annex 7.
  * **Kharid Khata (Purchase Register):** Compliant with VAT Act 2052, Annex 5.
  * **Value Added Tax (VAT 13%):** Automated input/output tax liability calculation and reporting.

---

### 7. Courier & Logistics Automation
* **Supported Integrations:** Nepal Can Move (NCM) & Pathao Courier.
* **Tracking Synchronization:** Automated tracking updates via secure webhooks with timing-safe signature verification.
* **COD Settlement:** Delivery status reconciliation with Cash On Delivery remittance accounting vouchers.

---

### 8. Production Verification Sign-Off
* **Database Destruction Protection:** Safe command guards prevent `migrate:fresh`, `db:wipe`, or accidental drops in production.
* **Authentication Security:** Spatie Role-Based Access Control (RBAC) enforced across all panels and endpoints without hardcoded backdoor bypasses.
* **Disaster Recovery:** Authenticated backup archives with AES-256-GCM encryption and verified restore paths.
