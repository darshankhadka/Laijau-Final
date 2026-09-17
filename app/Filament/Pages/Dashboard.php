<?php

namespace App\Filament\Pages;

use App\Helpers\NepaliNumberHelper;
use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\BikriKhataEntry;
use App\Models\Accounting\KharidKhataEntry;
use App\Models\ContactMessage;
use App\Models\Inventory\PurchaseOrder;
use App\Models\OfflineSale;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.dashboard';
    protected Width | string | null $maxWidth = 'full';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-home';
    protected static ?string $navigationLabel = 'Dashboard';
    protected static ?int $navigationSort = 1;

    public string $period = 'today';

    public function getHeading(): string | Htmlable
    {
        return 'Executive Business Dashboard';
    }

    public function getSubheading(): string | Htmlable | null
    {
        return null;
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    /**
     * Compact header actions replacing large dashboard cards.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('new_product')
                ->label('+ New Product')
                ->icon('heroicon-m-plus')
                ->url(route('filament.admin.resources.products.create'))
                ->color('primary')
                ->size('sm'),

            Action::make('quick_stock')
                ->label('+ Stock Entry')
                ->icon('heroicon-m-bolt')
                ->url(route('filament.admin.pages.quick-stock-entry'))
                ->color('success')
                ->outlined()
                ->size('sm'),

            Action::make('pos_sale')
                ->label('+ POS Sale')
                ->icon('heroicon-m-shopping-bag')
                ->url(route('filament.admin.pages.offline-sales'))
                ->color('warning')
                ->outlined()
                ->size('sm'),

            Action::make('new_purchase')
                ->label('+ Purchase')
                ->icon('heroicon-m-clipboard-document-check')
                ->url(route('filament.admin.resources.purchase-orders.create'))
                ->color('gray')
                ->outlined()
                ->size('sm'),

            Action::make('fulfillment_hub')
                ->label('Shipments')
                ->icon('heroicon-m-truck')
                ->url(route('filament.admin.pages.fulfillment-hub'))
                ->color('gray')
                ->outlined()
                ->size('sm'),

            Action::make('customers')
                ->label('Customers')
                ->icon('heroicon-m-users')
                ->url(route('filament.admin.resources.customers.index'))
                ->color('gray')
                ->outlined()
                ->size('sm'),

            ActionGroup::make([
                Action::make('barcode_labels')
                    ->label('Print Barcodes & Labels')
                    ->icon('heroicon-m-qr-code')
                    ->url(route('filament.admin.pages.barcodes-labels')),

                Action::make('payment_reconciliation')
                    ->label('Payment Reconciliation')
                    ->icon('heroicon-m-scale')
                    ->url(route('filament.admin.pages.payment-reconciliation')),

                Action::make('view_orders')
                    ->label('All Orders')
                    ->icon('heroicon-m-shopping-cart')
                    ->url(route('filament.admin.resources.orders.index')),

                Action::make('crm_kanban')
                    ->label('CRM Follow-ups Pipeline')
                    ->icon('heroicon-m-view-columns')
                    ->url(route('filament.admin.pages.crm-kanban')),

                Action::make('adjust_inventory')
                    ->label('Stock Overview')
                    ->icon('heroicon-m-archive-box')
                    ->url(route('filament.admin.resources.stock-levels.index')),

                Action::make('store_settings')
                    ->label('Store Settings')
                    ->icon('heroicon-m-cog-6-tooth')
                    ->url(route('filament.admin.pages.manage-settings')),
            ])
                ->label('More Actions')
                ->icon('heroicon-m-chevron-down')
                ->color('gray')
                ->button()
                ->size('sm'),
        ];
    }

    public function getPeriodOptions(): array
    {
        $now = Carbon::now();
        $yesterday = Carbon::yesterday();

        return [
            'today' => 'Today (' . $now->format('d M Y') . ')',
            'yesterday' => 'Yesterday (' . $yesterday->format('d M Y') . ')',
            'this_week' => 'This Week',
            'this_month' => 'This Month (' . $now->format('M Y') . ')',
            'last_month' => 'Last Month (' . $now->copy()->subMonth()->format('M Y') . ')',
            'all_time' => 'All-Time Analytics',
            'jan_2026' => 'January 2026',
            'feb_2026' => 'February 2026',
            'mar_2026' => 'March 2026',
            'apr_2026' => 'April 2026',
            'may_2026' => 'May 2026',
            'jun_2026' => 'June 2026',
            'jul_2026' => 'July 2026',
            'aug_2026' => 'August 2026',
            'sep_2026' => 'September 2026',
        ];
    }

    public function getDateRange(): ?array
    {
        $now = Carbon::now();

        return match ($this->period) {
            'today' => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            'yesterday' => [$now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay()],
            'this_week' => [$now->copy()->startOfWeek(), $now->copy()->endOfDay()],
            'this_month' => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            'last_month' => [$now->copy()->subMonth()->startOfMonth(), $now->copy()->subMonth()->endOfMonth()],
            'jan_2026' => [Carbon::parse('2026-01-01')->startOfDay(), Carbon::parse('2026-01-31')->endOfDay()],
            'feb_2026' => [Carbon::parse('2026-02-01')->startOfDay(), Carbon::parse('2026-02-28')->endOfDay()],
            'mar_2026' => [Carbon::parse('2026-03-01')->startOfDay(), Carbon::parse('2026-03-31')->endOfDay()],
            'apr_2026' => [Carbon::parse('2026-04-01')->startOfDay(), Carbon::parse('2026-04-30')->endOfDay()],
            'may_2026' => [Carbon::parse('2026-05-01')->startOfDay(), Carbon::parse('2026-05-31')->endOfDay()],
            'jun_2026' => [Carbon::parse('2026-06-01')->startOfDay(), Carbon::parse('2026-06-30')->endOfDay()],
            'jul_2026' => [Carbon::parse('2026-07-01')->startOfDay(), Carbon::parse('2026-07-31')->endOfDay()],
            'aug_2026' => [Carbon::parse('2026-08-01')->startOfDay(), Carbon::parse('2026-08-31')->endOfDay()],
            'sep_2026' => [Carbon::parse('2026-09-01')->startOfDay(), Carbon::parse('2026-09-30')->endOfDay()],
            default => null, // all_time
        };
    }

    /**
     * Valid paid order statuses per existing business logic.
     */
    protected function getPaidStatuses(): array
    {
        return [
            Order::STATUS_PAID,
            Order::STATUS_PAYMENT_VERIFIED,
            Order::STATUS_CUSTOMER_CONFIRMED,
            Order::STATUS_PROCESSING,
            Order::STATUS_PACKING,
            Order::STATUS_QUALITY_CHECK,
            Order::STATUS_READY_FOR_DELIVERY,
            Order::STATUS_READY_TO_SHIP,
            Order::STATUS_HANDED_TO_COURIER,
            Order::STATUS_IN_TRANSIT,
            Order::STATUS_SHIPPED,
            Order::STATUS_DELIVERED,
            'completed',
        ];
    }

    /**
     * 1. KPI Row: Combined POS + Online revenue and order volume.
     */
    public function getKpiData(): array
    {
        $range = $this->getDateRange();
        $paidStatuses = $this->getPaidStatuses();

        // 1. Online Sales
        $onlQuery = Order::query()->whereNotIn('status', [Order::STATUS_CANCELLED]);
        $onlPaidQuery = Order::query()->whereIn('status', $paidStatuses);
        if ($range) {
            $onlQuery->whereBetween('created_at', $range);
            $onlPaidQuery->whereBetween('created_at', $range);
        }
        $onlineOrdersCount = (clone $onlQuery)->count();
        $onlineRevenue = (float) (clone $onlPaidQuery)->sum('total_amount');

        // 2. POS Sales
        $posQuery = OfflineSale::where('status', '!=', 'voided');
        if ($range) {
            $posQuery->whereBetween('sold_at', $range);
        }
        $posSalesCount = (clone $posQuery)->count();
        $posRevenue = (float) (clone $posQuery)->sum('total_amount');

        // 3. Combined Total
        $totalRevenue = $onlineRevenue + $posRevenue;
        $totalOrdersCount = $onlineOrdersCount + $posSalesCount;
        $aov = $totalOrdersCount > 0 ? ($totalRevenue / $totalOrdersCount) : 0.0;

        // 4. Customers
        $custQuery = User::where('role', 'customer');
        if ($range) {
            $custQuery->whereBetween('created_at', $range);
        }
        $periodCustomers = (clone $custQuery)->count();
        $lifetimeCustomers = User::where('role', 'customer')->count();

        return [
            'revenue' => [
                'value' => NepaliNumberHelper::formatCurrency($totalRevenue, 'Rs. ', 0),
                'pos_value' => NepaliNumberHelper::formatCurrency($posRevenue, 'Rs. ', 0),
                'online_value' => NepaliNumberHelper::formatCurrency($onlineRevenue, 'Rs. ', 0),
                'subtext' => $range ? 'POS: ' . NepaliNumberHelper::formatCurrency($posRevenue, 'Rs. ', 0) . ' | Online: ' . NepaliNumberHelper::formatCurrency($onlineRevenue, 'Rs. ', 0) : 'Full timeline gross (POS + Online)',
            ],
            'orders' => [
                'value' => NepaliNumberHelper::format($totalOrdersCount),
                'pos_count' => NepaliNumberHelper::format($posSalesCount),
                'online_count' => NepaliNumberHelper::format($onlineOrdersCount),
                'subtext' => "POS: " . NepaliNumberHelper::format($posSalesCount) . " | Online: " . NepaliNumberHelper::format($onlineOrdersCount),
            ],
            'aov' => [
                'value' => NepaliNumberHelper::formatCurrency($aov, 'Rs. ', 0),
                'subtext' => 'Per transaction (blended)',
            ],
            'customers' => [
                'value' => NepaliNumberHelper::format($range ? $periodCustomers : $lifetimeCustomers),
                'subtext' => $range ? NepaliNumberHelper::format($periodCustomers) . ' new (' . NepaliNumberHelper::format($lifetimeCustomers) . ' total)' : 'Registered patrons',
            ],
        ];
    }

    /**
     * 2. Financial and Procurement Summary.
     */
    public function getFinancialSummary(): array
    {
        $range = $this->getDateRange();

        // Purchases
        $poQuery = PurchaseOrder::query();
        if ($range) {
            $poQuery->whereBetween('order_date', $range);
        }
        $purchasesCount = (clone $poQuery)->count();
        $purchasesTotal = (float) (clone $poQuery)->sum('total_amount_npr');

        // Payables: Unpaid Kharid Khata bills
        $payablesTotal = (float) KharidKhataEntry::where('type', 'supplier_bill')
            ->where('payment_status', '!=', 'paid')
            ->sum(DB::raw('total_amount - paid_amount'));

        // Receivables: Unpaid Sales Invoices
        $receivablesTotal = (float) AccountingInvoice::where('type', 'sales_invoice')
            ->where('payment_status', '!=', 'paid')
            ->sum(DB::raw('total_amount - paid_amount'));

        // Output VAT collected
        $vatQuery = BikriKhataEntry::query();
        if ($range) {
            $vatQuery->whereBetween('issue_date', [$range[0]->format('Y-m-d'), $range[1]->format('Y-m-d')]);
        }
        $vatCollected = (float) (clone $vatQuery)->sum('vat_amount');

        // Completed & Cancelled orders
        $orderQuery = Order::query();
        if ($range) {
            $orderQuery->whereBetween('created_at', $range);
        }
        $completedOrders = (clone $orderQuery)->whereIn('status', [Order::STATUS_DELIVERED, 'completed'])->count();
        $cancelledOrders = (clone $orderQuery)->where('status', Order::STATUS_CANCELLED)->count();

        return [
            'purchases_total' => NepaliNumberHelper::formatCurrency($purchasesTotal, 'Rs. ', 0),
            'purchases_count' => NepaliNumberHelper::format($purchasesCount),
            'payables_total' => NepaliNumberHelper::formatCurrency($payablesTotal, 'Rs. ', 0),
            'receivables_total' => NepaliNumberHelper::formatCurrency($receivablesTotal, 'Rs. ', 0),
            'vat_collected' => NepaliNumberHelper::formatCurrency($vatCollected, 'Rs. ', 0),
            'completed_orders' => NepaliNumberHelper::format($completedOrders),
            'cancelled_orders' => NepaliNumberHelper::format($cancelledOrders),
        ];
    }

    /**
     * 3. Sales Overview: Database-driven monthly or daily revenue curve.
     */
    public function getSalesOverview(): array
    {
        $paidStatuses = $this->getPaidStatuses();

        if ($this->period === 'all_time') {
            // Render trajectory from earliest recorded transaction to current month
            $points = [];
            $now = Carbon::now();
            $earliestSale = OfflineSale::min('sold_at') ?? Order::min('created_at') ?? $now->copy()->startOfYear();
            $curr = Carbon::parse($earliestSale)->startOfMonth();
            $latest = $now->copy()->endOfMonth();

            while ($curr->lte($latest)) {
                $start = $curr->copy()->startOfMonth();
                $end = $curr->copy()->endOfMonth();

                $posRev = (float) OfflineSale::where('status', '!=', 'voided')
                    ->whereBetween('sold_at', [$start, $end])
                    ->sum('total_amount');
                $posCount = OfflineSale::where('status', '!=', 'voided')
                    ->whereBetween('sold_at', [$start, $end])
                    ->count();

                $onlRev = (float) Order::whereIn('status', $paidStatuses)
                    ->whereBetween('created_at', [$start, $end])
                    ->sum('total_amount');
                $onlCount = Order::whereNotIn('status', [Order::STATUS_CANCELLED])
                    ->whereBetween('created_at', [$start, $end])
                    ->count();

                $monthRev = $posRev + $onlRev;
                $monthCount = $posCount + $onlCount;

                $points[] = [
                    'label' => $start->format('M'),
                    'full_label' => $start->format('F Y'),
                    'revenue' => $monthRev,
                    'pos_revenue' => $posRev,
                    'online_revenue' => $onlRev,
                    'orders' => $monthCount,
                ];
                $curr->addMonth();
            }
        } else {
            // Render daily points within the selected range
            $now = Carbon::now();
            $range = $this->getDateRange() ?? [$now->copy()->startOfMonth(), $now->copy()->endOfDay()];
            $startDate = $range[0]->copy();
            $endDate = $range[1]->isFuture() ? $now->copy()->endOfDay() : $range[1]->copy();
            $daysDiff = max(1, (int) $startDate->copy()->startOfDay()->diffInDays($endDate->copy()->startOfDay()) + 1);

            $points = [];
            for ($i = 0; $i < $daysDiff; $i++) {
                $currentDay = $startDate->copy()->addDays($i);
                $dayStart = $currentDay->copy()->startOfDay();
                $dayEnd = $currentDay->copy()->endOfDay();

                $posRev = (float) OfflineSale::where('status', '!=', 'voided')
                    ->whereBetween('sold_at', [$dayStart, $dayEnd])
                    ->sum('total_amount');
                $posCount = OfflineSale::where('status', '!=', 'voided')
                    ->whereBetween('sold_at', [$dayStart, $dayEnd])
                    ->count();

                $onlRev = (float) Order::whereIn('status', $paidStatuses)
                    ->whereBetween('created_at', [$dayStart, $dayEnd])
                    ->sum('total_amount');
                $onlCount = Order::whereNotIn('status', [Order::STATUS_CANCELLED])
                    ->whereBetween('created_at', [$dayStart, $dayEnd])
                    ->count();

                $dayRev = $posRev + $onlRev;
                $dayCount = $posCount + $onlCount;

                $points[] = [
                    'label' => $currentDay->format('M d'),
                    'full_label' => $currentDay->format('d M Y'),
                    'revenue' => $dayRev,
                    'pos_revenue' => $posRev,
                    'online_revenue' => $onlRev,
                    'orders' => $dayCount,
                ];
            }
        }

        $periodRevenue = collect($points)->sum('revenue');
        $periodOrdersCount = collect($points)->sum('orders');
        $maxRevenue = max(1.0, (float) collect($points)->max('revenue'));

        return [
            'points' => $points,
            'period_revenue' => NepaliNumberHelper::formatCurrency($periodRevenue, 'Rs. ', 0),
            'period_orders' => NepaliNumberHelper::format($periodOrdersCount),
            'max_revenue' => $maxRevenue,
            'period_title' => $this->getPeriodOptions()[$this->period] ?? 'Selected Period',
        ];
    }

    /**
     * 4. Order Operations: Compact Queue rows.
     */
    public function getOrderOperations(): array
    {
        return [
            [
                'status' => 'Payment Verification Pending',
                'count' => Order::where('payment_status', 'payment_verification_pending')->count(),
                'color' => 'bg-amber-400',
                'url' => route('filament.admin.resources.orders.index'),
            ],
            [
                'status' => 'Contact Required',
                'count' => Order::where('status', 'customer_contact_required')->count(),
                'color' => 'bg-orange-500',
                'url' => route('filament.admin.resources.orders.index'),
            ],
            [
                'status' => 'Processing & Packing',
                'count' => Order::whereIn('status', [Order::STATUS_PROCESSING, Order::STATUS_PACKING])->count(),
                'color' => 'bg-blue-500',
                'url' => route('filament.admin.resources.orders.index'),
            ],
            [
                'status' => 'Courier Dispatch / In Transit',
                'count' => Order::whereIn('status', [Order::STATUS_HANDED_TO_COURIER, Order::STATUS_IN_TRANSIT, Order::STATUS_SHIPPED])->count(),
                'color' => 'bg-emerald-600',
                'url' => route('filament.admin.resources.orders.index'),
            ],
            [
                'status' => 'Delivered',
                'count' => Order::where('status', Order::STATUS_DELIVERED)->count(),
                'color' => 'bg-purple-600',
                'url' => route('filament.admin.resources.orders.index'),
            ],
        ];
    }

    /**
     * 5. Needs Attention: Actionable items.
     */
    public function getAttentionItems(): array
    {
        $items = [];

        $outOfStock = ProductVariant::where('is_active', true)->where('stock_quantity', 0)->count();
        if ($outOfStock > 0) {
            $items[] = [
                'icon' => '⚠',
                'text' => NepaliNumberHelper::format($outOfStock) . " " . ($outOfStock === 1 ? 'product is' : 'products are') . " out of stock",
                'url' => route('filament.admin.resources.stock-levels.index'),
                'color' => 'text-rose-600 dark:text-rose-400',
            ];
        }

        $lowStock = ProductVariant::where('is_active', true)->whereBetween('stock_quantity', [1, 5])->count();
        if ($lowStock > 0) {
            $items[] = [
                'icon' => '⚠',
                'text' => NepaliNumberHelper::format($lowStock) . " " . ($lowStock === 1 ? 'product is' : 'products are') . " low in stock",
                'url' => route('filament.admin.resources.stock-levels.index'),
                'color' => 'text-amber-600 dark:text-amber-400',
            ];
        }

        $readyDispatch = Order::where('status', Order::STATUS_READY_TO_SHIP)->count();
        if ($readyDispatch > 0) {
            $items[] = [
                'icon' => '●',
                'text' => NepaliNumberHelper::format($readyDispatch) . " " . ($readyDispatch === 1 ? 'order' : 'orders') . " ready to dispatch",
                'url' => route('filament.admin.resources.orders.index'),
                'color' => 'text-emerald-600 dark:text-emerald-400',
            ];
        }

        $unreadMessages = ContactMessage::where('status', 'new')->count();
        if ($unreadMessages > 0) {
            $items[] = [
                'icon' => '●',
                'text' => NepaliNumberHelper::format($unreadMessages) . " concierge " . ($unreadMessages === 1 ? 'message' : 'messages'),
                'url' => route('filament.admin.resources.contact-messages.index'),
                'color' => 'text-blue-600 dark:text-blue-400',
            ];
        }

        return $items;
    }

    /**
     * 6. Central Warehouse Inventory Summary.
     */
    public function getInventorySummary(): array
    {
        return [
            'products' => Product::count(),
            'variants' => ProductVariant::count(),
            'total_stock' => (int) ProductVariant::sum('stock_quantity'),
            'low_stock' => ProductVariant::where('is_active', true)->whereBetween('stock_quantity', [1, 5])->count(),
            'out_of_stock' => ProductVariant::where('is_active', true)->where('stock_quantity', 0)->count(),
        ];
    }

    /**
     * 7. Customer Activity.
     */
    public function getCustomerActivity(): array
    {
        $range = $this->getDateRange();

        $newCustomersQuery = User::where('role', 'customer');
        if ($range) {
            $newCustomersQuery->whereBetween('created_at', $range);
        } else {
            $newCustomersQuery->where('created_at', '>=', Carbon::now()->subDays(30));
        }
        $newCustomers = $newCustomersQuery->count();

        $repeatCustomers = User::where('role', 'customer')
            ->has('orders', '>=', 2)
            ->count();

        $repeatOrdersCount = Order::whereIn('user_id', User::where('role', 'customer')->has('orders', '>=', 2)->pluck('id'))->count();

        return [
            'new_customers' => $newCustomers,
            'returning_customers' => $repeatCustomers,
            'repeat_orders' => $repeatOrdersCount,
        ];
    }

    /**
     * 8. Recent Orders (Strictly 5 rows).
     */
    public function getRecentOrders(): \Illuminate\Database\Eloquent\Collection
    {
        return Order::query()
            ->latest('id')
            ->limit(5)
            ->get();
    }
}
