<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\Accounting\AccountingInvoice;
use App\Models\Accounting\BikriKhataEntry;
use App\Models\Accounting\KharidKhataEntry;
use App\Models\Inventory\PurchaseOrder;
use App\Models\OfflineSale;
use App\Models\Order;
use App\Services\Accounting\AccountingService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;

class SalesPurchaseReconciliationPage extends Page
{
    protected string $view = 'filament.pages.sales-purchase-reconciliation';
    protected Width | string | null $maxWidth = 'full';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static string | \UnitEnum | null $navigationGroup = 'Finance';
    protected static ?string $navigationLabel = 'Bikri & Kharid Reconciliation';
    protected static ?int $navigationSort = 35;
    protected static ?string $title = 'Commerce vs. General Ledger Reconciliation (Bikri & Kharid)';
    protected static ?string $slug = 'sales-purchase-reconciliation';

    public function getHeading(): string | Htmlable
    {
        return '';
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user() ?? auth('web')->user();
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        return $user->hasRole('Accountant', 'admin')
            || $user->hasRole('Accountant', 'web')
            || $user->hasRole('Workspace Admin', 'admin')
            || $user->hasRole('Workspace Admin', 'web')
            || $user->role === 'accountant';
    }

    public string $activeTab = 'bikri_gl'; // 'bikri_gl' | 'kharid_gl' | 'inventory_gl' | 'pos_gl' | 'cod_bank' | 'bank_reconciliation'

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function getReconciliationData(): array
    {
        $service = app(AccountingService::class);
        $metrics = $service->reconcileCommerceVsAccounting();

        $unpostedOrders = Order::whereNotIn('status', ['cancelled', 'failed_delivery'])
            ->whereDoesntHave('invoice')
            ->latest()
            ->take(10)
            ->get();

        $unpostedPos = OfflineSale::where('status', '!=', 'voided')
            ->whereDoesntHave('invoice')
            ->latest()
            ->take(10)
            ->get();

        $unpostedPoBills = PurchaseOrder::whereIn('status', ['received', 'completed'])
            ->whereDoesntHave('invoice')
            ->latest()
            ->take(10)
            ->get();

        return [
            'metrics' => $metrics,
            'unposted_orders' => $unpostedOrders,
            'unposted_pos' => $unpostedPos,
            'unposted_purchase_orders' => $unpostedPoBills,
        ];
    }

    public function syncAllUnposted(): void
    {
        $service = app(AccountingService::class);

        $orders = Order::whereNotIn('status', ['cancelled', 'failed_delivery'])
            ->whereDoesntHave('invoice')
            ->get();

        $syncedOrders = 0;
        foreach ($orders as $order) {
            $service->recordOrderSale($order);
            $syncedOrders++;
        }

        $posSales = OfflineSale::where('status', '!=', 'voided')
            ->whereDoesntHave('invoice')
            ->get();

        $syncedPos = 0;
        foreach ($posSales as $pos) {
            $service->recordOfflineSale($pos);
            $syncedPos++;
        }

        Notification::make()
            ->title('Reconciliation Synchronized')
            ->body("Posted {$syncedOrders} web orders and {$syncedPos} POS sales to Bikri Khata and Double-Entry GL.")
            ->success()
            ->send();
    }
}
