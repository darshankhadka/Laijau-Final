<?php

namespace App\Services\Settings;

class ModuleRegistry
{
    /**
     * Get all registered modules in Laijau.
     */
    public static function all(): array
    {
        return [
            'commerce' => [
                'id' => 'commerce',
                'name' => 'Commerce & Catalog',
                'description' => 'Product catalog, customer ordering, checkout flow, pricing, promotions, and restock waitlists.',
                'icon' => 'heroicon-o-shopping-bag',
                'navigationGroup' => 'Commerce',
                'category' => 'commercial',
                'version' => '3.0.0',
                'is_core' => true,
                'dependencies' => [],
                'groups' => [
                    'general' => 'General Catalog & Availability',
                    'checkout_orders' => 'Checkout & Order Lifecycle',
                    'preorders_restock' => 'Pre-Orders & Waitlists',
                    'promotions' => 'Discounts & Promotions',
                ],
            ],

            'inventory' => [
                'id' => 'inventory',
                'name' => 'Enterprise Inventory',
                'description' => 'Physical goods ledger, multi-location stock control, reservations, transfers, and valuation.',
                'icon' => 'heroicon-o-archive-box',
                'navigationGroup' => 'Inventory',
                'category' => 'operational',
                'version' => '2.5.0',
                'is_core' => false,
                'dependencies' => ['commerce'],
                'groups' => [
                    'general' => 'General & Warehouse Policies',
                    'reservations' => 'Cart & Order Stock Reservations',
                    'stock_control' => 'Stock Levels & Safety Buffers',
                    'transfers' => 'Inter-Warehouse Routing',
                    'returns' => 'Customer Returns & Restocking',
                    'valuation' => 'Valuation & Landed Costing',
                ],
            ],

            'purchasing' => [
                'id' => 'purchasing',
                'name' => 'Purchasing & Procurement',
                'description' => 'Supplier purchase orders, inbound goods receiving, landed costs, and lead times.',
                'icon' => 'heroicon-o-clipboard-document-check',
                'navigationGroup' => 'Inventory',
                'category' => 'operational',
                'version' => '2.0.0',
                'is_core' => false,
                'dependencies' => ['inventory'],
                'groups' => [
                    'procurement' => 'Purchase Order Approval & Policies',
                    'receiving' => 'Goods Inward & Tolerances',
                ],
            ],

            'pos' => [
                'id' => 'pos',
                'name' => 'POS & Offline Showroom',
                'description' => 'In-person physical retail, cash drawer reconciliation, instant stock depletion, and receipts.',
                'icon' => 'heroicon-o-building-storefront',
                'navigationGroup' => 'Commerce',
                'category' => 'commercial',
                'version' => '2.2.0',
                'is_core' => false,
                'dependencies' => ['commerce', 'inventory'],
                'groups' => [
                    'register' => 'Register & Cash Policies',
                    'receipts_hardware' => 'Receipts & Showroom Defaults',
                ],
            ],

            'accounting' => [
                'id' => 'accounting',
                'name' => 'Nepal Accounting & GL',
                'description' => 'Double-entry general ledger (NAS), Nepal 13% VAT, and automated inventory journal sync.',
                'icon' => 'heroicon-o-calculator',
                'navigationGroup' => 'Accounting',
                'category' => 'financial',
                'version' => '4.0.0',
                'is_core' => false,
                'dependencies' => [],
                'groups' => [
                    'fiscal_general' => 'Fiscal Year & General Ledger',
                    'vat_taxation' => 'Nepal VAT (13%) & Rates',
                    'inventory_integration' => 'Inventory Asset & COGS Posting',
                ],
            ],

            'hrm' => [
                'id' => 'hrm',
                'name' => 'HRM & Workforce',
                'description' => 'Employees, departments, attendance, timesheets, and Nepal Labour Act leave policies.',
                'icon' => 'heroicon-o-user-group',
                'navigationGroup' => 'HRM',
                'category' => 'operational',
                'version' => '2.1.0',
                'is_core' => false,
                'dependencies' => [],
                'groups' => [
                    'workforce' => 'Employee & Workforce Policies',
                    'leaves_attendance' => 'Leave Management & Timesheets',
                    'payroll' => 'Payroll & Statutory Deductions (PF/CIT/TDS)',
                ],
            ],

            'crm' => [
                'id' => 'crm',
                'name' => 'CRM & Client Relations',
                'description' => 'Customer lifecycle management, duplicate detection, VIP tiering, and concierge leads.',
                'icon' => 'heroicon-o-users',
                'navigationGroup' => 'CRM',
                'category' => 'commercial',
                'version' => '2.0.0',
                'is_core' => false,
                'dependencies' => [],
                'groups' => [
                    'customers' => 'Customer Identification & Lifecycle',
                    'leads_concierge' => 'VIP Concierge & Leads',
                ],
            ],

            'shipping' => [
                'id' => 'shipping',
                'name' => 'Shipping & Delivery',
                'description' => 'Fulfillment logistics, Nepal Can Move (NCM) & Pathao Parcel rules, free delivery thresholds, and Nepal nationwide delivery.',
                'icon' => 'heroicon-o-truck',
                'navigationGroup' => 'Configuration',
                'category' => 'operational',
                'version' => '2.3.0',
                'is_core' => false,
                'dependencies' => ['commerce'],
                'groups' => [
                    'logistics' => 'Carriers & Tracking',
                    'rates_thresholds' => 'Delivery Rates & Thresholds',
                ],
            ],

            'payments' => [
                'id' => 'payments',
                'name' => 'Payment Gateways',
                'description' => 'eSewa, Khalti, ConnectIPS, and Cash on Delivery with encrypted API credential storage.',
                'icon' => 'heroicon-o-credit-card',
                'navigationGroup' => 'Configuration',
                'category' => 'financial',
                'version' => '3.1.0',
                'is_core' => false,
                'dependencies' => ['commerce'],
                'groups' => [
                    'gateways' => 'Active Payment Providers',
                    'limits_currencies' => 'Order Value Limits & Currencies',
                ],
            ],

            'notifications' => [
                'id' => 'notifications',
                'name' => 'Notifications & Alerts',
                'description' => 'Customer order confirmation emails, SMS dispatch alerts, low-stock warnings, and staff notifications.',
                'icon' => 'heroicon-o-bell',
                'navigationGroup' => 'Communication',
                'category' => 'system',
                'version' => '2.0.0',
                'is_core' => false,
                'dependencies' => [],
                'groups' => [
                    'channels' => 'Notification Delivery Channels',
                    'event_triggers' => 'Operational Event Alerts',
                ],
            ],

            'marketing' => [
                'id' => 'marketing',
                'name' => 'Content & Marketing',
                'description' => 'SEO meta tags, XML sitemaps, Google Analytics, Meta Pixel, and promotional store banners.',
                'icon' => 'heroicon-o-sparkles',
                'navigationGroup' => 'Commerce',
                'category' => 'commercial',
                'version' => '2.0.0',
                'is_core' => false,
                'dependencies' => [],
                'groups' => [
                    'seo_metadata' => 'Storefront SEO & Metadata',
                    'tracking_pixels' => 'Marketing Pixels & Scripts',
                ],
            ],

            'analytics' => [
                'id' => 'analytics',
                'name' => 'Analytics & Data',
                'description' => 'E-commerce conversion tracking, customer search analytics, and GDPR data retention schedules.',
                'icon' => 'heroicon-o-chart-bar',
                'navigationGroup' => 'Configuration',
                'category' => 'system',
                'version' => '1.5.0',
                'is_core' => false,
                'dependencies' => [],
                'groups' => [
                    'tracking' => 'Metrics & Event Logging',
                    'data_retention' => 'Privacy & GDPR Data Retention',
                ],
            ],

            'system' => [
                'id' => 'system',
                'name' => 'System & Platform',
                'description' => 'Store maintenance mode, session lifetimes, API rate limiting, and administrative security policies.',
                'icon' => 'heroicon-o-server-stack',
                'navigationGroup' => 'Configuration',
                'category' => 'core',
                'version' => '3.0.0',
                'is_core' => true,
                'dependencies' => [],
                'groups' => [
                    'platform_maintenance' => 'Platform Status & Maintenance',
                    'security_api' => 'Security, Sessions & API Limits',
                ],
            ],
        ];
    }

    /**
     * Retrieve a specific module descriptor.
     */
    public static function get(string $module): ?array
    {
        return self::all()[$module] ?? null;
    }

    /**
     * Check if a module exists.
     */
    public static function exists(string $module): bool
    {
        return array_key_exists($module, self::all());
    }

    /**
     * Check whether a module can be safely disabled.
     * Returns true if safe; returns false and populates $dependents if blocked.
     */
    public static function canDisable(string $module, array &$dependents = []): bool
    {
        $descriptor = self::get($module);
        if (!$descriptor) {
            return false;
        }

        // Core modules cannot be disabled
        if (!empty($descriptor['is_core'])) {
            $dependents[] = 'System Core Architecture (Protected)';
            return false;
        }

        $all = self::all();
        $dependents = [];

        // Check if any other registered module declares this module as dependency
        foreach ($all as $otherId => $other) {
            if ($otherId === $module) {
                continue;
            }

            if (in_array($module, $other['dependencies'] ?? [], true)) {
                // If the other module is currently active, blocking applies
                $dependents[] = $other['name'];
            }
        }

        return empty($dependents);
    }
}
