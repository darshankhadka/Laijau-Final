<?php

namespace App\Services\Settings;

class ConfigurationImpactService
{
    /**
     * Map of known business settings to operational scope, severity, and dynamic impact evaluators.
     */
    protected static array $impactCatalog = [
        'inventory' => [
            'reservation_ttl_minutes' => [
                'affects' => 'Checkout Cart Holds & Stock Availability',
                'severity' => 'medium',
                'description' => 'Length of time stock remains reserved in customer carts before automatically expiring.',
                'impact_evaluator' => 'evaluateReservationTtl',
            ],
            'allow_negative_stock' => [
                'affects' => 'Physical Inventory Integrity & Backordering',
                'severity' => 'high',
                'description' => 'Whether stock levels are permitted to fall below zero on order fulfillment or POS sales.',
                'impact_evaluator' => 'evaluateNegativeStock',
            ],
            'default_reorder_point' => [
                'affects' => 'Automated Procurement Recommendations & Alerts',
                'severity' => 'low',
                'description' => 'Default inventory threshold triggering low-stock warnings and reorder recommendations.',
                'impact_evaluator' => 'evaluateReorderPoint',
            ],
            'valuation_method' => [
                'affects' => 'General Ledger Balance Sheet (Account 2210) & Landed Cost',
                'severity' => 'high',
                'description' => 'Inventory asset valuation accounting methodology.',
                'impact_evaluator' => 'evaluateValuationMethod',
            ],
            'require_transfer_dispatch_confirmation' => [
                'affects' => 'Inter-Warehouse Transit Ledger & In-Transit Custody',
                'severity' => 'medium',
                'description' => 'Enforces a two-step dispatch and receiving confirmation workflow for warehouse transfers.',
            ],
            'auto_expire_reservations' => [
                'affects' => 'Automated Stock Release Cron',
                'severity' => 'low',
                'description' => 'Enables automated background release of expired shopping cart reservations.',
            ],
        ],
        'purchasing' => [
            'po_approval_required' => [
                'affects' => 'Procurement Governance & Authorization Checks',
                'severity' => 'high',
                'description' => 'Mandates that all draft supplier purchase orders require explicit managerial approval before transmission.',
                'impact_evaluator' => 'evaluatePoApprovalRequired',
            ],
            'require_po_approval' => [
                'affects' => 'Procurement Governance & Authorization Checks',
                'severity' => 'high',
                'description' => 'Mandates that all draft supplier purchase orders require explicit managerial approval before transmission.',
                'impact_evaluator' => 'evaluatePoApprovalRequired',
            ],
            'po_approval_threshold_npr' => [
                'affects' => 'Financial Commitment Authority & Executive Sign-Off',
                'severity' => 'high',
                'description' => 'Threshold amount in NPR (Rs.) above which purchase orders require executive managerial approval.',
                'impact_evaluator' => 'evaluatePoApprovalThreshold',
            ],
            'approval_roles' => [
                'affects' => 'Purchasing Authorization & Executive Sign-Off Roles',
                'severity' => 'high',
                'description' => 'User roles authorized to approve purchase orders exceeding the approval threshold.',
                'impact_evaluator' => 'evaluateApprovalRoles',
            ],
            'approval_escalation_days' => [
                'affects' => 'Procurement Approval Escalation Timers',
                'severity' => 'medium',
                'description' => 'Elapsed days after submission before a pending purchase order triggers management escalation alerts.',
            ],
            'approval_expiry_days' => [
                'affects' => 'Purchase Requisition Expiration',
                'severity' => 'medium',
                'description' => 'Elapsed days after submission before an unapproved purchase order automatically expires.',
            ],
            'rejection_behavior' => [
                'affects' => 'Purchase Order Rejection Workflow State',
                'severity' => 'medium',
                'description' => 'Controls whether rejected purchase orders remain rejected, revert to draft for amendment, or transition to cancelled.',
                'impact_evaluator' => 'evaluateRejectionBehavior',
            ],
            'allow_partial_receiving' => [
                'affects' => 'Warehouse Goods Receiving & Consignment Processing',
                'severity' => 'medium',
                'description' => 'Permits warehouse staff to accept partial shipments against purchase orders.',
                'impact_evaluator' => 'evaluatePartialReceiving',
            ],
            'receiving_tolerance_percentage' => [
                'affects' => 'Supplier Over-Delivery Tolerance & Invoicing Limits',
                'severity' => 'medium',
                'description' => 'Maximum percentage by which delivered quantities can exceed ordered quantities without purchase requisition revision.',
                'impact_evaluator' => 'evaluateReceivingTolerance',
            ],
            'over_receipt_handling' => [
                'affects' => 'Supplier Over-Delivery Enforcement Policy',
                'severity' => 'medium',
                'description' => 'Strictness policy for handling shipments that exceed the receiving tolerance percentage.',
                'impact_evaluator' => 'evaluateOverReceiptHandling',
            ],
            'short_receipt_handling' => [
                'affects' => 'Supplier Short-Shipment Reconciliation',
                'severity' => 'low',
                'description' => 'Determines whether incomplete shipments remain open for backorders or close short.',
            ],
            'receiving_confirmation_requirements' => [
                'affects' => 'Goods Receipt Quality & Delivery Note Verification',
                'severity' => 'low',
                'description' => 'Mandatory verification steps required before completing goods intake.',
            ],
            'default_payment_terms' => [
                'affects' => 'Supplier Commercial Agreement & Payables Due Dates',
                'severity' => 'low',
                'description' => 'Default commercial payment terms assigned to newly created purchase orders.',
            ],
            'default_lead_time_days' => [
                'affects' => 'Inbound Supply Chain Lead Time Projections',
                'severity' => 'low',
                'description' => 'Default expected lead time in days between order submission and warehouse arrival.',
            ],
            'default_currency' => [
                'affects' => 'Supplier Settlement Currency',
                'severity' => 'low',
                'description' => 'Default transaction currency for international artisan and supplier purchase orders.',
            ],
            'enforce_active_supplier' => [
                'affects' => 'Supplier Sanctions & Inactive Vendor Procurement Blocking',
                'severity' => 'high',
                'description' => 'Strictly prohibits creating, submitting, or approving purchase orders for inactive or suspended suppliers.',
                'impact_evaluator' => 'evaluateEnforceActiveSupplier',
            ],
        ],
        'pos' => [
            'offline_sales_enabled' => [
                'affects' => 'Kathmandu Showroom POS Terminal',
                'severity' => 'high',
                'description' => 'Enables showroom and pop-up store staff to process walk-in customer sales.',
                'impact_evaluator' => 'evaluateOfflineSales',
            ],
            'auto_deduct_inventory' => [
                'affects' => 'Showroom Stock Deduction & Ledger Entries',
                'severity' => 'high',
                'description' => 'Automatically creates stock movements and decreases on-hand stock upon POS sale.',
                'impact_evaluator' => 'evaluatePosAutoDeduct',
            ],
            'max_pos_discount_percentage' => [
                'affects' => 'Showroom Pricing Margin & Staff Discount Limits',
                'severity' => 'medium',
                'description' => 'Maximum allowable discount percentage showroom staff can grant without managerial override.',
            ],
        ],
        'accounting' => [
            'default_vat_rate' => [
                'affects' => 'Domestic Invoicing & Tax Calculations (13% VAT)',
                'severity' => 'high',
                'description' => 'Default standard VAT rate applied to goods sold in Nepal.',
                'impact_evaluator' => 'evaluateVatRate',
            ],
            'standard_vat_rate' => [
                'affects' => 'Statutory Nepal VAT Rate & IRD Tax Authority Reporting',
                'severity' => 'high',
                'description' => 'Standard VAT rate under Nepal VAT Act applied across commerce, POS, and financial reporting.',
                'impact_evaluator' => 'evaluateStandardVatRate',
            ],
            'auto_post_journals' => [
                'affects' => 'General Ledger Double-Entry Vouchers',
                'severity' => 'high',
                'description' => 'Automatically creates balanced double-entry vouchers upon sales, fulfillment, and returns.',
                'impact_evaluator' => 'evaluateAutoJournals',
            ],
            'allow_backdated_postings' => [
                'affects' => 'Fiscal Period Governance & Nepal Accounting Standards (NAS)',
                'severity' => 'high',
                'description' => 'Controls whether journal vouchers can be posted with transaction dates prior to current fiscal period.',
                'impact_evaluator' => 'evaluateAllowBackdatedPostings',
            ],
            'max_backdated_days' => [
                'affects' => 'Backdated Journal Entry Grace Period',
                'severity' => 'medium',
                'description' => 'Maximum allowable elapsed calendar days between transaction date and posting date.',
            ],
            'lock_periods_on_close' => [
                'affects' => 'Fiscal Period Immutability & Audit Trail Integrity',
                'severity' => 'high',
                'description' => 'Permanently locks closed monthly/annual periods to prevent subsequent journal voucher insertion.',
            ],
            'auto_post_cogs' => [
                'affects' => 'Cost of Goods Sold (Account 1210) Automation',
                'severity' => 'high',
                'description' => 'Automatically posts COGS expense and reduces inventory asset on order dispatch.',
            ],
            'enable_inventory_gl_sync' => [
                'affects' => 'Perpetual Inventory General Ledger Integration',
                'severity' => 'high',
                'description' => 'Controls real-time synchronization between warehouse stock movements and GL Accounts 1210/2210.',
                'impact_evaluator' => 'evaluateInventoryGlSync',
            ],
            'inventory_valuation_method' => [
                'affects' => 'Cost-Flow Valuation Authority (FIFO vs WAC)',
                'severity' => 'high',
                'description' => 'Methodology used to value inventory assets and compute cost of goods sold.',
                'impact_evaluator' => 'evaluateInventoryValuationMethod',
            ],
            'enforce_balanced_journals' => [
                'affects' => 'Double-Entry Bookkeeping Equality Constraint',
                'severity' => 'high',
                'description' => 'Strictly prohibits creating unbalanced journal vouchers where sum(debit) != sum(credit).',
                'impact_evaluator' => 'evaluateEnforceBalancedJournals',
            ],
            'manual_journal_roles' => [
                'affects' => 'Manual Journal Voucher Creation Authorization',
                'severity' => 'high',
                'description' => 'Comma-delimited roles permitted to author manual journal vouchers (e.g. admin, accountant).',
            ],
            'require_reversal_reason' => [
                'affects' => 'Audit Justification for Reversing Entries',
                'severity' => 'medium',
                'description' => 'Requires a mandatory textual rationale before a posted voucher can be reversed.',
            ],
            'journal_voucher_prefix' => [
                'affects' => 'General Ledger Voucher Identifier Sequence',
                'severity' => 'medium',
                'description' => 'Prefix assigned to sequential journal voucher numbers (e.g. BIL-).',
            ],
            'sales_invoice_prefix' => [
                'affects' => 'Commercial Sales Invoice Identifier Sequence',
                'severity' => 'medium',
                'description' => 'Prefix assigned to sequential sales invoice numbers (e.g. FAK-).',
            ],
            'credit_note_prefix' => [
                'affects' => 'Credit Note Identifier Sequence',
                'severity' => 'medium',
                'description' => 'Prefix assigned to sequential credit note numbers (e.g. KRED-).',
            ],
            'lock_closed_periods' => [
                'affects' => 'Audit Compliance & Prior-Period Voucher Adjustments',
                'severity' => 'high',
                'description' => 'Prohibits creating or mutating journal entries in officially closed accounting quarters.',
            ],
        ],
        'commerce' => [
            'allow_backorders' => [
                'affects' => 'Customer Ordering when Stock is Zero',
                'severity' => 'high',
                'description' => 'Allows customers to purchase products even if on-hand inventory is depleted.',
                'impact_evaluator' => 'evaluateBackorders',
            ],
            'stock_visibility' => [
                'affects' => 'Storefront Inventory Transparency & Scarcity Signals',
                'severity' => 'medium',
                'description' => 'Controls whether customers see exact stock counts, generic in-stock badges, or hidden inventory.',
                'impact_evaluator' => 'evaluateStockVisibility',
            ],
            'out_of_stock_behavior' => [
                'affects' => 'Catalog Browsing & Out-of-Stock Product Visibility',
                'severity' => 'medium',
                'description' => 'Determines whether out-of-stock items are hidden, shown as unavailable, or opened for backorders.',
                'impact_evaluator' => 'evaluateOutOfStockBehavior',
            ],
            'enable_preorders' => [
                'affects' => 'New Product Launches & Pre-Launch Checkout',
                'severity' => 'medium',
                'description' => 'Allows items marked as pre-order to accept payment and reserve fulfillment priority.',
            ],
            'preorder_deposit_percentage' => [
                'affects' => 'Pre-order Cash Flow & Checkout Deposit Requirement',
                'severity' => 'medium',
                'description' => 'Percentage of retail price billed upfront during pre-order checkout.',
            ],
            'reservation_ttl_minutes' => [
                'affects' => 'Checkout Cart Holds & Stock Availability',
                'severity' => 'medium',
                'description' => 'Duration in minutes that inventory is reserved for a customer completing online checkout.',
                'impact_evaluator' => 'evaluateReservationTtl',
            ],
            'allow_guest_checkout' => [
                'affects' => 'Checkout Friction & Customer Account Acquisition',
                'severity' => 'medium',
                'description' => 'Permits shoppers to place orders without mandatory registered account creation.',
                'impact_evaluator' => 'evaluateGuestCheckout',
            ],
            'minimum_order_value_npr' => [
                'affects' => 'Minimum Order Size & Average Order Value Economics',
                'severity' => 'medium',
                'description' => 'Minimum cart subtotal in NPR required before checkout payment is unlocked.',
                'impact_evaluator' => 'evaluateMinOrderValue',
            ],
            'order_cancellation_window_minutes' => [
                'affects' => 'Customer Self-Service Order Cancellation',
                'severity' => 'medium',
                'description' => 'Window of time in minutes after checkout during which a customer can cancel their order directly.',
                'impact_evaluator' => 'evaluateCancellationWindow',
            ],
            'cancellation_window_minutes' => [
                'affects' => 'Customer Self-Service Order Cancellation',
                'severity' => 'medium',
                'description' => 'Window of time in minutes after checkout during which a customer can cancel their order directly.',
                'impact_evaluator' => 'evaluateCancellationWindow',
            ],
            'max_discount_percentage' => [
                'affects' => 'Promotional Margin Protection & Discount Ceilings',
                'severity' => 'high',
                'description' => 'Maximum cumulative discount percentage that coupon codes can deduct from order subtotal.',
                'impact_evaluator' => 'evaluateMaxDiscount',
            ],
            'allow_coupon_stacking' => [
                'affects' => 'Coupon Stacking & Promotional Policy',
                'severity' => 'medium',
                'description' => 'Allows customers to enter and apply multiple coupon codes simultaneously.',
            ],
            'return_window_days' => [
                'affects' => 'Consumer Rights & Return Processing Timeframe',
                'severity' => 'medium',
                'description' => 'Number of days from order delivery during which customers are eligible to submit return requests.',
                'impact_evaluator' => 'evaluateReturnWindow',
            ],
            'auto_restock_on_refund' => [
                'affects' => 'Physical Inventory Restoration on Customer Returns',
                'severity' => 'high',
                'description' => 'Automatically increments warehouse sellable stock upon processing approved customer refunds.',
                'impact_evaluator' => 'evaluateAutoRestock',
            ],
            'restocking_fee_percentage' => [
                'affects' => 'Return Handling Fees & Net Customer Refund Amount',
                'severity' => 'medium',
                'description' => 'Percentage deducted from refunded merchandise totals for return shipping and restocking.',
            ],
        ],
        'payments' => [
            'connectips_enabled' => [
                'affects' => 'Online Bank Transfer Gateway',
                'severity' => 'high',
                'description' => 'Enables or disables connectIPS direct bank transfer payment gateway.',
            ],
            'esewa_enabled' => [
                'affects' => 'Digital Wallet Checkout Availability',
                'severity' => 'high',
                'description' => 'Enables or disables eSewa wallet and QR payments.',
            ],
            'khalti_enabled' => [
                'affects' => 'Digital Wallet Checkout Availability',
                'severity' => 'high',
                'description' => 'Enables or disables Khalti wallet payments.',
            ],
        ],
        'shipping' => [
            'free_shipping_threshold_npr' => [
                'affects' => 'Shipping Cost & Checkout Cart Free-Delivery Rule',
                'severity' => 'high',
                'description' => 'Subtotal in NPR required for orders to qualify for zero delivery fee.',
                'impact_evaluator' => 'evaluateFreeShippingThreshold',
            ],
            'enable_free_shipping' => [
                'affects' => 'Complimentary Freight Promotional Availability',
                'severity' => 'high',
                'description' => 'Master switch enabling or disabling zero-cost shipping for qualifying orders.',
                'impact_evaluator' => 'evaluateFreeShippingToggle',
            ],
            'enable_outside_valley_shipping' => [
                'affects' => 'Nationwide Outside Valley Deliveries',
                'severity' => 'high',
                'description' => 'Allows customers outside Kathmandu Valley to select courier delivery.',
                'impact_evaluator' => 'evaluateNepalDomesticShipping',
            ],
            'enable_outside_valley_shipping' => [
                'affects' => 'Nationwide Outside Valley Deliveries',
                'severity' => 'high',
                'description' => 'Allows customers outside Kathmandu Valley to select courier delivery.',
                'impact_evaluator' => 'evaluateNepalDomesticShipping',
            ],
            'require_tracking_number_on_dispatch' => [
                'affects' => 'Order Dispatch Governance & Courier Accountability',
                'severity' => 'medium',
                'description' => 'Enforces parcel tracking assignment prior to order transition to Shipped.',
                'impact_evaluator' => 'evaluateRequireTracking',
            ],
            'return_shipping_covered_by' => [
                'affects' => 'Customer Return Economics & Postage Allocation',
                'severity' => 'medium',
                'description' => 'Policy determining whether store or customer pays return courier freight.',
                'impact_evaluator' => 'evaluateReturnShippingCoveredBy',
            ],
            'failed_delivery_action' => [
                'affects' => 'Undeliverable Parcel Routing & Inventory Intake',
                'severity' => 'medium',
                'description' => 'Automated workflow executed when courier reports abandoned or undeliverable shipment.',
                'impact_evaluator' => 'evaluateFailedDeliveryAction',
            ],
        ],
        'hrm' => [
            'standard_weekly_hours' => [
                'affects' => 'Full-Time Equivalents (FTE) & Overtime Thresholds',
                'severity' => 'high',
                'description' => 'Collective standard weekly working hours used for FTE metrics and daily threshold derivation.',
                'impact_evaluator' => 'evaluateStandardWeeklyHours',
            ],
            'hourly_leave_allowance_percentage' => [
                'affects' => 'Statutory Nepal Labour Act Leave Allowance',
                'severity' => 'high',
                'description' => 'Statutory leave allowance accrued for eligible employees under Nepal Labour Act.',
                'impact_evaluator' => 'evaluateHourlyLeaveAllowance',
            ],
            'tds_percentage' => [
                'affects' => 'Statutory Nepal TDS Withholding (IRD)',
                'severity' => 'high',
                'description' => 'Statutory income tax TDS withheld from gross employee salaries under Nepal IRD regulations.',
                'impact_evaluator' => 'evaluateTdsRate',
            ],
            'overtime_multiplier' => [
                'affects' => 'Hourly Overtime Compensation & Staff Cost',
                'severity' => 'medium',
                'description' => 'Multiplier applied to employee hourly rate for approved overtime hours.',
                'impact_evaluator' => 'evaluateOvertimeMultiplier',
            ],
            'require_timesheet_approval' => [
                'affects' => 'Payroll Timesheet Governance & Sign-Off Enforcement',
                'severity' => 'medium',
                'description' => 'Controls whether pending timesheets must receive explicit managerial approval before inclusion in payroll calculations.',
                'impact_evaluator' => 'evaluateRequireTimesheetApproval',
            ],
            'auto_post_payroll_to_accounting' => [
                'affects' => 'Automated General Ledger Voucher Posting',
                'severity' => 'medium',
                'description' => 'Automatically posts double-entry accounting journal vouchers upon payroll calculation.',
            ],
        ],
        'system' => [
            'maintenance_mode' => [
                'affects' => 'Entire Public Storefront Availability',
                'severity' => 'high',
                'description' => 'Immediately blocks all customer traffic with a maintenance landing screen.',
                'impact_evaluator' => 'evaluateMaintenanceMode',
            ],
            'session_lifetime_minutes' => [
                'affects' => 'Admin & Staff Inactivity Security Timeout',
                'severity' => 'medium',
                'description' => 'Duration of administrator inactivity before requiring re-authentication.',
            ],
        ],
    ];

    /**
     * Get the static "what this affects" metadata for a specific setting.
     */
    public static function getMetadata(string $module, string $key): array
    {
        return self::$impactCatalog[$module][$key] ?? [
            'affects' => 'Module Behavior & Automation',
            'severity' => 'low',
            'description' => 'Standard configuration setting.',
            'impact_evaluator' => null,
        ];
    }

    /**
     * Evaluate the operational impact of a proposed configuration change.
     */
    public static function evaluateImpact(string $module, string $key, $currentValue, $newValue): array
    {
        $meta = self::getMetadata($module, $key);
        $severity = $meta['severity'] ?? 'low';
        $affects = $meta['affects'] ?? 'Module Configuration';

        $evaluator = $meta['impact_evaluator'] ?? null;
        if ($evaluator && method_exists(self::class, $evaluator)) {
            $impactText = self::$evaluator($currentValue, $newValue);
        } else {
            $impactText = "Modifies {$affects} from '{$currentValue}' to '{$newValue}'.";
        }

        return [
            'module' => $module,
            'key' => $key,
            'affects' => $affects,
            'severity' => $severity,
            'current_value' => $currentValue,
            'new_value' => $newValue,
            'impact_text' => $impactText,
            'warnings' => !empty($impactText) ? [$impactText] : [],
        ];
    }

    /**
     * Instance wrapper to evaluate impact for a setting key.
     */
    public function evaluate(string $module, string $key, $newValue, $currentValue = null): array
    {
        if ($currentValue === null) {
            $currentValue = app(\App\Services\Settings\SettingsService::class)->get($module, $key);
        }
        return self::evaluateImpact($module, $key, $currentValue, $newValue);
    }

    // --- Dynamic Evaluators ---

    protected static function evaluateReservationTtl($old, $new): string
    {
        $oldMin = (int)$old;
        $newMin = (int)$new;

        if ($newMin > $oldMin) {
            return "Customers can hold inventory for up to {$newMin} minutes during checkout (previously {$oldMin}m). This reduces stock available to other shoppers for a longer duration.";
        }

        return "Reservation hold duration reduced from {$oldMin}m to {$newMin}m. Carts will release abandoned items faster, returning inventory to the available pool quicker.";
    }

    protected static function evaluateNegativeStock($old, $new): string
    {
        $enabled = filter_var($new, FILTER_VALIDATE_BOOLEAN);
        if ($enabled) {
            return "CRITICAL: Physical inventory safety checks will be bypassed. Orders and adjustments can complete even when stock is 0 or negative, which may cause overselling and physical inventory variances.";
        }

        return "Strict inventory integrity enforced: Orders and stock adjustments will be strictly rejected if available stock is insufficient.";
    }

    protected static function evaluateReorderPoint($old, $new): string
    {
        return "Items will now trigger low-stock alerts and procurement recommendations when available quantity falls below {$new} units (previously {$old}).";
    }

    protected static function evaluateValuationMethod($old, $new): string
    {
        return "CRITICAL: Changes inventory asset calculation from {$old} to {$new}. This impacts balance sheet reporting in GL Account 1210 and cost of goods sold calculations.";
    }

    protected static function evaluateOfflineSales($old, $new): string
    {
        $enabled = filter_var($new, FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            return "CRITICAL: POS sales channel will be immediately closed. Showroom staff cannot process walk-in purchases or print receipts.";
        }

        return "Showroom POS terminal activated for walk-in transactions and direct sales.";
    }

    protected static function evaluatePosAutoDeduct($old, $new): string
    {
        $enabled = filter_var($new, FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            return "WARNING: POS transactions will NOT deduct showroom inventory automatically. Stock takes and manual adjustments will be required.";
        }

        return "POS sales will automatically decrement inventory and post immutable stock movement ledger entries.";
    }

    protected static function evaluateVatRate($old, $new): string
    {
        return "Changes default VAT rate from {$old}% to {$new}%. Invoices and storefront tax calculations will calculate VAT using the new rate immediately.";
    }

    protected static function evaluateAutoJournals($old, $new): string
    {
        $enabled = filter_var($new, FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            return "WARNING: Automated double-entry voucher generation suspended. Sales, inventory movements, and payroll will not record General Ledger journal entries automatically.";
        }

        return "Standard double-entry journal vouchers will post automatically to the General Ledger.";
    }

    protected static function evaluateBackorders($old, $new): string
    {
        $enabled = filter_var($new, FILTER_VALIDATE_BOOLEAN);
        if ($enabled) {
            return "WARNING: E-commerce shoppers can order products with 0 stock. Ensure supply chain lead times can support pending backorders.";
        }

        return "Out-of-stock products cannot be checked out online by customers.";
    }



    protected static function evaluateMaintenanceMode($old, $new): string
    {
        $enabled = filter_var($new, FILTER_VALIDATE_BOOLEAN);
        if ($enabled) {
            return "CRITICAL: The public website is being taken OFFLINE. Customers will see a maintenance message and cannot browse or checkout.";
        }

        return "Storefront is ONLINE and accepting public customer traffic.";
    }

    protected static function evaluatePoApprovalRequired($old, $new): string
    {
        $enabled = filter_var($new, FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            return "CRITICAL: Supplier purchase orders will bypass approval checks entirely and automatically advance to ordered status.";
        }

        return "Formal purchase order approval mandated before orders can be dispatched to suppliers.";
    }

    protected static function evaluatePoApprovalThreshold($old, $new): string
    {
        $oldFormatted = number_format((float)$old, 2);
        $newFormatted = number_format((float)$new, 2);

        return "HIGH IMPACT: Changing this value alters the purchasing authorization boundary and may allow larger supplier commitments without the previous approval level (threshold updated from Rs. {$oldFormatted} to Rs. {$newFormatted}).";
    }

    protected static function evaluateApprovalRoles($old, $new): string
    {
        $rolesStr = is_array($new) ? implode(', ', $new) : (string)$new;
        return "HIGH IMPACT: Purchasing commitment authorization roles updated to [{$rolesStr}]. Users without these roles cannot approve purchase orders exceeding the financial threshold.";
    }

    protected static function evaluateRejectionBehavior($old, $new): string
    {
        return match ((string)$new) {
            'draft' => "Rejected purchase orders will automatically revert to Draft status, allowing purchasing agents to revise quantities or pricing and resubmit.",
            'cancelled' => "Rejected purchase orders will be permanently marked Cancelled and cannot be resubmitted.",
            default => "Rejected purchase orders will remain in Rejected status for auditing and procurement review.",
        };
    }

    protected static function evaluatePartialReceiving($old, $new): string
    {
        $enabled = filter_var($new, FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            return "WARNING: Staggered goods receiving disabled. Shipments must be received in full in a single consignment; partial receipts will be blocked.";
        }

        return "Warehouse staff permitted to receive purchase order shipments in multiple staggered deliveries.";
    }

    protected static function evaluateReceivingTolerance($old, $new): string
    {
        return "Goods receiving quantity tolerance updated from {$old}% to {$new}%. Deliveries exceeding order quantity lines by more than {$new}% will be rejected.";
    }

    protected static function evaluateOverReceiptHandling($old, $new): string
    {
        return match ((string)$new) {
            'warn' => "Deliveries exceeding the receiving tolerance limit will generate a warning but allow warehouse staff to intake the excess items.",
            default => "Deliveries exceeding the receiving tolerance percentage will be strictly rejected at warehouse intake.",
        };
    }

    protected static function evaluateEnforceActiveSupplier($old, $new): string
    {
        $enabled = filter_var($new, FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            return "CRITICAL: Inactive supplier enforcement disabled. Procurement staff can place orders with deactivated or suspended vendors.";
        }

        return "Active supplier enforcement strictly enabled: Draft, submission, and approval of purchase orders for deactivated suppliers are blocked.";
    }

    protected static function evaluateStockVisibility($old, $new): string
    {
        return match ((string)$new) {
            'show_exact' => "Customers will see exact unit stock quantities (e.g. '3 units left') across product and storefront catalog views.",
            'hide' => "Inventory counts and stock badges will be completely hidden from storefront shoppers.",
            default => "Stock availability will display generic 'In Stock' / 'Out of Stock' badges without disclosing exact physical inventory counts.",
        };
    }

    protected static function evaluateOutOfStockBehavior($old, $new): string
    {
        return match ((string)$new) {
            'hide' => "Depleted garments with 0 stock will be automatically hidden from storefront catalog navigation.",
            'allow_backorder' => "Depleted garments remain available for purchase on backorder with notification of extended lead time.",
            default => "Depleted garments will remain visible in catalog browsing marked as 'Out of Stock' or 'Sold Out'.",
        };
    }

    protected static function evaluateGuestCheckout($old, $new): string
    {
        $enabled = filter_var($new, FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            return "WARNING: Guest checkout disabled. Customers must create or log into a registered account prior to completing checkout.";
        }

        return "Guest checkout enabled: Shoppers can checkout quickly using email address without creating an account.";
    }

    protected static function evaluateMinOrderValue($old, $new): string
    {
        $amt = number_format((float)$new, 2);
        if ((float)$new > 0) {
            return "Carts below {$amt} will be blocked from proceeding to payment.";
        }

        return "Minimum order value requirement removed. Customers can checkout with any cart total.";
    }

    protected static function evaluateCancellationWindow($old, $new): string
    {
        $oldMin = (int)$old;
        $newMin = (int)$new;

        return "Order self-service cancellation window adjusted from {$oldMin} minutes to {$newMin} minutes. Customers can cancel unfulfilled orders directly within {$newMin}m of placement.";
    }

    protected static function evaluateMaxDiscount($old, $new): string
    {
        return "HIGH IMPACT: Maximum discount ceiling updated from {$old}% to {$new}%. No promotional coupon or discount code can deduct more than {$new}% of order subtotal.";
    }

    protected static function evaluateReturnWindow($old, $new): string
    {
        return "Customer return request window adjusted from {$old} days to {$new} days after order delivery date.";
    }

    protected static function evaluateAutoRestock($old, $new): string
    {
        $enabled = filter_var($new, FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            return "WARNING: Automatic inventory restocking upon refund disabled. Returned merchandise must be manually quarantined and adjusted.";
        }

        return "HIGH IMPACT: Approved returned merchandise will automatically increment sellable warehouse stock and record return ledger movements.";
    }

    protected static function evaluateFreeShippingThreshold($old, $new): string
    {
        $oldAmt = number_format((float)$old, 2);
        $newAmt = number_format((float)$new, 2);
        return "HIGH IMPACT: Free shipping qualification threshold updated from Rs. {$oldAmt} to Rs. {$newAmt}. Carts must meet this subtotal to receive complimentary delivery.";
    }

    protected static function evaluateFreeShippingToggle($old, $new): string
    {
        $enabled = filter_var($new, FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            return "HIGH IMPACT: Complimentary free shipping promotions disabled across all orders. Customers will be charged standard carrier freight regardless of cart total.";
        }

        return "Free shipping promotion active: Eligible orders exceeding the configured threshold will receive zero freight fee.";
    }

    protected static function evaluateNepalDomesticShipping($old, $new): string
    {
        $enabled = filter_var($new, FILTER_VALIDATE_BOOLEAN);
        if (!$enabled) {
            return "HIGH IMPACT: Outside valley courier delivery is disabled. Storefront checkout is restricted strictly to Kathmandu Valley.";
        }

        return "Nationwide delivery enabled: Customers across all 77 districts of Nepal can place and receive shipments.";
    }

    protected static function evaluateRequireTracking($old, $new): string
    {
        $enabled = filter_var($new, FILTER_VALIDATE_BOOLEAN);
        if ($enabled) {
            return "Parcel tracking number is strictly enforced prior to advancing orders to Shipped status.";
        }

        return "Orders may be advanced to Shipped without an assigned carrier tracking identifier.";
    }

    protected static function evaluateReturnShippingCoveredBy($old, $new): string
    {
        return match ((string)$new) {
            'store' => "Store absorbs all return freight costs: Complimentary prepaid return labels provided to customers.",
            'defects_only' => "Return shipping is deducted from customer refund unless goods are verified defective upon return inspection.",
            default => "Customer pays return freight: Return label fee is automatically deducted from gross customer refund.",
        };
    }

    protected static function evaluateFailedDeliveryAction($old, $new): string
    {
        return match ((string)$new) {
            'quarantine' => "Undelivered consignments will be directed to quarantine hold for discrepancy analysis.",
            'reschedule' => "Undelivered consignments remain open pending customer contact and courier re-dispatch.",
            default => "Undelivered consignments automatically restock to active warehouse inventory and mark order cancelled.",
        };
    }

    protected static function evaluateStandardWeeklyHours($old, $new): string
    {
        $val = (float)$new;
        return "CRITICAL WORKFORCE POLICY: Standard workweek adjusted to {$val} hours. This recalibrates full-time equivalent (FTE) calculations and the standard daily overtime trigger across all staff.";
    }

    protected static function evaluateHourlyLeaveAllowance($old, $new): string
    {
        $val = (float)$new;
        return "CRITICAL PAYROLL POLICY: Statutory leave allowance rate set to {$val}%. Modifies company leave allowance liability for all hourly employees under Nepal Labour Act.";
    }

    protected static function evaluateTdsRate($old, $new): string
    {
        $val = (float)$new;
        return "CRITICAL TAX POLICY: Statutory TDS withholding rate set to {$val}%. Directly impacts net payout calculations and Nepal Inland Revenue Department (IRD) liabilities.";
    }

    protected static function evaluateOvertimeMultiplier($old, $new): string
    {
        $val = (float)$new;
        return "Overtime compensation rate adjusted to {$val}x regular hourly wage.";
    }

    protected static function evaluateRequireTimesheetApproval($old, $new): string
    {
        $enabled = filter_var($new, FILTER_VALIDATE_BOOLEAN);
        if ($enabled) {
            return "Managerial sign-off is strictly enforced: Unapproved timesheets will be excluded from monthly payroll compensation.";
        }

        return "Unapproved timesheets will be included in monthly payroll calculations without mandatory managerial sign-off.";
    }

    protected static function evaluateStandardVatRate($old, $new): string
    {
        $val = (float)$new;
        return "CRITICAL FISCAL POLICY: Statutory standard Nepal VAT rate adjusted to {$val}%. All sales, POS transactions, invoices, and VAT returns will calculate VAT using {$val}%.";
    }

    protected static function evaluateAllowBackdatedPostings($old, $new): string
    {
        $enabled = filter_var($new, FILTER_VALIDATE_BOOLEAN);
        if ($enabled) {
            return "WARNING: Backdated journal postings are permitted up to policy limit. Ensure compliance with Nepal Accounting Standards (NAS) immutability and consecutive numbering requirements.";
        }

        return "Strict fiscal policy enforced: Transactions dated in the past exceeding grace tolerance will be rejected by the accounting service.";
    }

    protected static function evaluateInventoryGlSync($old, $new): string
    {
        $enabled = filter_var($new, FILTER_VALIDATE_BOOLEAN);
        if ($enabled) {
            return "Real-time perpetual inventory GL integration active. Fulfillment, returns, and write-offs will post balanced entries between Account 1210 (COGS) and Account 2210 (Inventory).";
        }

        return "WARNING: Inventory transactions will not synchronize with General Ledger. Inventory asset accounts must be reconciled periodically via manual journals.";
    }

    protected static function evaluateInventoryValuationMethod($old, $new): string
    {
        return "Changes inventory valuation authority from {$old} to {$new}. Cost of Goods Sold calculations will adjust cost-flow methodology across inventory batches.";
    }

    protected static function evaluateEnforceBalancedJournals($old, $new): string
    {
        $enabled = filter_var($new, FILTER_VALIDATE_BOOLEAN);
        if ($enabled) {
            return "Strict double-entry equality enforced. Every journal voucher must satisfy sum(debit) == sum(credit).";
        }

        return "CRITICAL AUDIT WARNING: Unbalanced journal vouchers permitted. This violates fundamental accounting principles and Nepal Accounting Standards (NAS)!";
    }
}
