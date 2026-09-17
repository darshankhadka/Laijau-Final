<?php

namespace App\Filament\Pages;

use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockCount;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\StockTransfer;
use App\Models\Inventory\Warehouse;
use App\Services\Inventory\InventoryService;
use Filament\Pages\Page;

class InventoryDashboard extends Page
{
    protected string $view = 'filament.pages.inventory-dashboard';

    protected static bool $shouldRegisterNavigation = false;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-building-storefront';
    protected static string | \UnitEnum | null $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Inventory Dashboard';
    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user() ?? auth('web')->user();
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        return $user->hasRole('Warehouse Manager', 'admin')
            || $user->hasRole('Warehouse Manager', 'web')
            || $user->hasRole('Store Manager', 'admin')
            || $user->hasRole('Store Manager', 'web')
            || $user->hasRole('Workspace Admin', 'admin')
            || $user->hasRole('Workspace Admin', 'web')
            || $user->can('page_InventoryDashboard')
            || in_array($user->role, ['warehouse_manager', 'store_manager']);
    }

    public function getViewData(): array
    {
        $inventoryService = app(InventoryService::class);
        $kpis = $inventoryService->getDashboardKpis();
        $reorder = $inventoryService->getReorderIntelligenceReport();
        $reconciliation = $inventoryService->getAccountingReconciliation();

        $recentMovements = StockMovement::with(['warehouse', 'product', 'variant', 'user'])
            ->latest('id')
            ->take(8)
            ->get();

        $lowStockItems = StockLevel::with(['product', 'variant', 'warehouse'])
            ->whereRaw('quantity_on_hand <= reorder_point')
            ->orderBy('quantity_on_hand', 'asc')
            ->take(6)
            ->get();

        $openPOs = PurchaseOrder::with(['supplier', 'warehouse'])
            ->whereIn('status', ['draft', 'submitted', 'approved', 'ordered', 'in_transit', 'partially_received'])
            ->latest('id')
            ->take(5)
            ->get();

        $activeTransfers = StockTransfer::with(['sourceWarehouse', 'destinationWarehouse'])
            ->whereIn('status', ['draft', 'approved', 'in_transit'])
            ->latest('id')
            ->take(5)
            ->get();

        return [
            'kpis' => $kpis,
            'summary' => $inventoryService->getValuationSummary(),
            'warehouses' => $kpis['warehouse_breakdown'],
            'reorder' => $reorder,
            'reconciliation' => $reconciliation,
            'recentMovements' => $recentMovements,
            'lowStockItems' => $lowStockItems,
            'openPOs' => $openPOs,
            'activeTransfers' => $activeTransfers,
        ];
    }
}
