<?php

namespace App\Filament\Pages;

use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockCount;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Services\Inventory\InventoryService;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryReportsPage extends Page
{
    protected string $view = 'filament.pages.inventory-reports-page';

    protected static bool $shouldRegisterNavigation = false;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-chart-bar';
    protected static string | \UnitEnum | null $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Inventory Reports';
    protected static ?int $navigationSort = 120;

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
            || $user->can('page_InventoryReportsPage')
            || in_array($user->role, ['warehouse_manager', 'store_manager', 'accountant']);
    }

    public function downloadStockOnHandCsv(): StreamedResponse
    {
        $levels = StockLevel::with(['product', 'variant', 'warehouse'])->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laijau-stock-on-hand-' . date('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($levels) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF"); // UTF-8 BOM
            fputcsv($handle, ['Warehouse Code', 'Warehouse Name', 'Product Name', 'SKU', 'Variant', 'On Hand', 'Reserved', 'Available', 'Incoming', 'Damaged', 'Quarantined', 'Unit Cost (Rs.)', 'Total Value (Rs.)', 'Reorder Point']);

            foreach ($levels as $level) {
                fputcsv($handle, [
                    $level->warehouse?->code,
                    $level->warehouse?->name,
                    $level->product?->name,
                    $level->variant?->sku ?? $level->product?->sku,
                    $level->variant?->name ?? '-',
                    $level->quantity_on_hand,
                    $level->quantity_reserved,
                    $level->quantity_available,
                    $level->quantity_incoming,
                    $level->damaged_quantity,
                    $level->quarantined_quantity,
                    number_format((float)$level->unit_cost_npr, 2, '.', ''),
                    number_format($level->quantity_on_hand * (float)$level->unit_cost_npr, 2, '.', ''),
                    $level->reorder_point,
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    public function downloadMovementsCsv(): StreamedResponse
    {
        $movements = StockMovement::with(['product', 'variant', 'warehouse', 'user'])->orderBy('id', 'desc')->limit(5000)->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laijau-stock-movements-' . date('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($movements) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Movement #', 'Date & Time', 'Warehouse', 'Product', 'SKU', 'Movement Type', 'Qty Delta', 'Qty Before', 'Qty After', 'Unit Cost (Rs.)', 'Total Cost (Rs.)', 'Reference Type', 'Reference #', 'User', 'Reason']);

            foreach ($movements as $m) {
                fputcsv($handle, [
                    $m->movement_number,
                    $m->created_at?->toDateTimeString(),
                    $m->warehouse?->name,
                    $m->product?->name,
                    $m->variant?->sku ?? $m->product?->sku,
                    $m->movement_type,
                    $m->quantity,
                    $m->quantity_before,
                    $m->quantity_after,
                    number_format((float)$m->unit_cost_npr, 2, '.', ''),
                    number_format((float)$m->total_cost_npr, 2, '.', ''),
                    $m->reference_type,
                    $m->reference_number,
                    $m->user?->name ?? 'System',
                    $m->reason,
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    public function downloadReorderCsv(): StreamedResponse
    {
        $report = app(InventoryService::class)->getReorderIntelligenceReport();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="laijau-reorder-intelligence-' . date('Y-m-d') . '.csv"',
        ];

        return response()->stream(function () use ($report) {
            $handle = fopen('php://output', 'w');
            fputs($handle, "\xEF\xBB\xBF");
            fputcsv($handle, ['Warehouse', 'Product', 'SKU', 'Variant', 'On Hand', 'Available', 'Reorder Point', 'Recommended Order Qty', 'Unit Cost (Rs.)', 'Estimated Total (Rs.)', 'Preferred Supplier']);

            foreach ($report['recommendations'] as $rec) {
                fputcsv($handle, [
                    $rec['warehouse_name'],
                    $rec['product_name'],
                    $rec['sku'],
                    '-',
                    $rec['on_hand'],
                    $rec['available'],
                    $rec['reorder_point'],
                    $rec['recommended_qty'],
                    number_format((float)$rec['unit_cost_npr'], 2, '.', ''),
                    number_format((float)$rec['estimated_cost_npr'], 2, '.', ''),
                    $rec['supplier_name'],
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    public function getViewData(): array
    {
        $inventoryService = app(InventoryService::class);

        return [
            'kpis' => $inventoryService->getDashboardKpis(),
            'reorder' => $inventoryService->getReorderIntelligenceReport(),
            'reconciliation' => $inventoryService->getAccountingReconciliation(),
            'warehouses' => Warehouse::where('is_active', true)->get(),
        ];
    }
}
