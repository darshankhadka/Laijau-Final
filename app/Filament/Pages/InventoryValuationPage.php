<?php

namespace App\Filament\Pages;

use App\Models\Inventory\StockLevel;
use App\Models\Inventory\Warehouse;
use App\Services\Inventory\InventoryService;
use Filament\Pages\Page;

class InventoryValuationPage extends Page
{
    protected string $view = 'filament.pages.inventory-valuation-page';

    protected static bool $shouldRegisterNavigation = false;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-presentation-chart-line';
    protected static string | \UnitEnum | null $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Inventory Valuation';
    protected static ?int $navigationSort = 110;

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
            || $user->hasRole('Accountant', 'admin')
            || $user->hasRole('Accountant', 'web')
            || $user->hasRole('Workspace Admin', 'admin')
            || $user->hasRole('Workspace Admin', 'web')
            || $user->can('page_InventoryValuationPage')
            || in_array($user->role, ['warehouse_manager', 'store_manager', 'accountant']);
    }

    public function getViewData(): array
    {
        $inventoryService = app(InventoryService::class);
        $summary = $inventoryService->getValuationSummary();
        $warehouseBreakdown = $inventoryService->getWarehouseBreakdown();
        $reconciliation = $inventoryService->getAccountingReconciliation();

        $stockValuations = StockLevel::with(['product', 'variant', 'warehouse'])
            ->where('quantity_on_hand', '>', 0)
            ->get()
            ->sortByDesc(fn($item) => $item->quantity_on_hand * (float)$item->unit_cost_npr);

        return [
            'summary' => $summary,
            'warehouseBreakdown' => $warehouseBreakdown,
            'reconciliation' => $reconciliation,
            'stockValuations' => $stockValuations,
        ];
    }
}
