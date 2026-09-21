<?php

namespace App\Filament\Pages;

use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Inventory\InventoryService;
use App\Services\ProductSkuService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\DB;

class QuickStockEntryPage extends Page
{
    protected string $view = 'filament.pages.quick-stock-entry';
    protected Width | string | null $maxWidth = 'full';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-bolt';
    protected static string | \UnitEnum | null $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Quick Stock Entry';
    protected static ?int $navigationSort = 20;
    protected static ?string $title = 'Quick Stock Entry & Receiving';
    protected static ?string $slug = 'quick-stock-entry';

    // Global Settings
    public string $mode = 'scanner'; // 'scanner' | 'matrix'
    public ?int $selectedWarehouseId = null;
    public string $receivingNote = 'Stock Receiving';
    public string $movementReason = 'purchase_receive';
    public ?int $selectedSupplierId = null;
    public string $referenceNumber = '';
    public ?float $unitCost = null;

    // Scanner Mode Properties
    public string $scanInput = '';
    public ?array $scannedItem = null;
    public ?int $quantityToAdd = null;
    public array $recentScans = [];

    // Matrix Mode Properties
    public ?int $matrixProductId = null;
    public string $matrixColor = '';
    public array $matrixAvailableColors = [];
    public array $matrixGrid = [];

    public function mount(): void
    {
        $defaultWarehouse = app(InventoryService::class)->getDefaultWarehouse();
        $this->selectedWarehouseId = $defaultWarehouse?->id ?: Warehouse::first()?->id;
    }

    /**
     * Handles Barcode Scanner input on Enter.
     */
    public function handleScan(?string $code = null): void
    {
        $term = trim($code ?: $this->scanInput);
        if (empty($term)) {
            return;
        }

        $lookup = app(ProductSkuService::class)->lookupBarcode($term);

        if (!$lookup) {
            Notification::make()
                ->title('Item Not Found')
                ->body("No active product or variant found matching '{$term}'.")
                ->warning()
                ->send();
            $this->scanInput = '';
            return;
        }

        $stock = 0;
        if ($lookup['type'] === 'variant' && $lookup['variant']) {
            $stockLevel = StockLevel::where('warehouse_id', $this->selectedWarehouseId)
                ->where('variant_id', $lookup['variant']->id)
                ->first();
            $stock = $stockLevel ? (int) $stockLevel->quantity_on_hand : (int) $lookup['variant']->stock_quantity;
        } elseif ($lookup['product']) {
            $stockLevel = StockLevel::where('warehouse_id', $this->selectedWarehouseId)
                ->where('product_id', $lookup['product']->id)
                ->whereNull('variant_id')
                ->first();
            $stock = $stockLevel ? (int) $stockLevel->quantity_on_hand : (int) $lookup['product']->quantity;
        }

        $lookup['warehouse_stock'] = $stock;
        $lookup['product_id'] = $lookup['type'] === 'variant' ? $lookup['variant']->product_id : $lookup['product']->id;
        $lookup['variant_id'] = $lookup['type'] === 'variant' ? $lookup['variant']->id : null;
        $this->scannedItem = $lookup;
        $this->unitCost = (float)($lookup['variant']?->unit_cost_npr ?? $lookup['product']?->unit_cost_npr ?? 0);
        $this->quantityToAdd = 1; // default suggested delta
        $this->scanInput = '';

        // Dispatch browser event to focus the quantity input
        $this->dispatch('focus-quantity-input');
    }

    /**
     * Commits the scanned quantity update atomically.
     */
    public function commitScan(): void
    {
        if (!$this->scannedItem || $this->quantityToAdd === null || $this->quantityToAdd === 0) {
            Notification::make()
                ->title('Invalid Quantity')
                ->body('Please enter a valid non-zero quantity to adjust.')
                ->warning()
                ->send();
            return;
        }

        $delta = (int) $this->quantityToAdd;
        $inventoryService = app(InventoryService::class);
        $user = auth()->user();

        try {
            DB::transaction(function () use ($delta, $inventoryService, $user) {
                $productId = $this->scannedItem['product_id'] ?? (
                    $this->scannedItem['type'] === 'variant'
                    ? (is_array($this->scannedItem['variant']) ? $this->scannedItem['variant']['product_id'] : $this->scannedItem['variant']->product_id)
                    : (is_array($this->scannedItem['product']) ? $this->scannedItem['product']['id'] : $this->scannedItem['product']->id)
                );
                $variantId = $this->scannedItem['variant_id'] ?? (
                    $this->scannedItem['type'] === 'variant'
                    ? (is_array($this->scannedItem['variant']) ? $this->scannedItem['variant']['id'] : $this->scannedItem['variant']->id)
                    : null
                );

                $movementType = $delta > 0 ? $this->movementReason : 'adjustment_loss';

                $movement = $inventoryService->recordStockMovement([
                    'warehouse_id' => $this->selectedWarehouseId,
                    'product_id' => $productId,
                    'variant_id' => $variantId,
                    'quantity' => $delta,
                    'unit_cost_npr' => $this->unitCost,
                    'movement_type' => $movementType,
                    'reference_type' => 'quick_entry',
                    'reference_number' => $this->referenceNumber ?: null,
                    'reason' => 'Quick Stock Entry: ' . ($this->receivingNote ?: 'Inbound Scan'),
                    'notes' => ($this->referenceNumber ? "Ref: {$this->referenceNumber}. " : '') . ($this->receivingNote ?: ''),
                ], $user);

                // Prepend to recent scans log
                array_unshift($this->recentScans, [
                    'id' => $movement->id,
                    'name' => $this->scannedItem['label'],
                    'sku' => $this->scannedItem['sku'],
                    'barcode' => $this->scannedItem['barcode'],
                    'delta' => $delta,
                    'previous_stock' => $this->scannedItem['warehouse_stock'],
                    'new_stock' => $this->scannedItem['warehouse_stock'] + $delta,
                    'time' => now()->format('H:i:s'),
                ]);
            });

            Notification::make()
                ->title('Stock Updated Successfully')
                ->body("{$this->scannedItem['label']}: {$delta} units recorded.")
                ->success()
                ->send();

            $this->scannedItem = null;
            $this->quantityToAdd = null;
            $this->dispatch('focus-scanner-input');
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Stock Update Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function cancelScan(): void
    {
        $this->scannedItem = null;
        $this->quantityToAdd = null;
        $this->dispatch('focus-scanner-input');
    }

    public function setQuantityPreset(int $amount): void
    {
        $this->quantityToAdd = $amount;
    }

    public function adjustQuantity(int $delta): void
    {
        $current = (int) ($this->quantityToAdd ?? 0);
        $new = $current + $delta;
        $this->quantityToAdd = $new === 0 ? 1 : $new;
    }

    /**
     * Matrix Mode: When Product is selected, populate available colors.
     */
    public function updatedMatrixProductId($value): void
    {
        $this->matrixColor = '';
        $this->matrixGrid = [];
        $this->matrixAvailableColors = [];

        if (!$value) {
            return;
        }

        $product = Product::with('variants')->find($value);
        if (!$product) {
            return;
        }

        $colors = $product->variants->pluck('color')->unique()->filter()->values()->toArray();
        if (empty($colors)) {
            $colors = ['Standard'];
        }

        $this->matrixAvailableColors = $colors;
        $this->matrixColor = $colors[0] ?? '';
        $this->loadMatrixGrid();
    }

    /**
     * Matrix Mode: When Color is changed, populate sizes matrix.
     */
    public function updatedMatrixColor(): void
    {
        $this->loadMatrixGrid();
    }

    protected function loadMatrixGrid(): void
    {
        $this->matrixGrid = [];
        if (!$this->matrixProductId) {
            return;
        }

        $product = Product::with('variants')->find($this->matrixProductId);
        if (!$product) {
            return;
        }

        $variants = $product->variants;
        if (!empty($this->matrixColor)) {
            $variants = $variants->where('color', $this->matrixColor);
        }

        foreach ($variants as $variant) {
            $stockLevel = StockLevel::where('warehouse_id', $this->selectedWarehouseId)
                ->where('variant_id', $variant->id)
                ->first();
            $stock = $stockLevel ? (int) $stockLevel->quantity_on_hand : (int) $variant->stock_quantity;

            $this->matrixGrid[] = [
                'variant_id' => $variant->id,
                'size' => $variant->size ?: 'Standard',
                'sku' => $variant->sku,
                'barcode' => $variant->barcode,
                'current_stock' => $stock,
                'add_qty' => 0,
            ];
        }
    }

    /**
     * Commits batch receiving across all sizes in the matrix.
     */
    public function commitMatrixReceiving(): void
    {
        $itemsToUpdate = array_filter($this->matrixGrid, fn($row) => (int)($row['add_qty'] ?? 0) > 0);

        if (empty($itemsToUpdate)) {
            Notification::make()
                ->title('No Quantities Entered')
                ->body('Please enter receiving quantities for at least one size in the grid.')
                ->warning()
                ->send();
            return;
        }

        $inventoryService = app(InventoryService::class);
        $user = auth()->user();
        $totalReceived = 0;
        $sizesCount = 0;

        try {
            DB::transaction(function () use ($itemsToUpdate, $inventoryService, $user, &$totalReceived, &$sizesCount) {
                foreach ($itemsToUpdate as $row) {
                    $qty = (int) $row['add_qty'];
                    $variant = ProductVariant::find($row['variant_id']);
                    if (!$variant) continue;

                    $inventoryService->recordStockMovement([
                        'warehouse_id' => $this->selectedWarehouseId,
                        'product_id' => $variant->product_id,
                        'variant_id' => $variant->id,
                        'quantity' => $qty,
                        'movement_type' => 'purchase_receive',
                        'notes' => 'Matrix Batch Receiving: ' . ($this->receivingNote ?: 'Quick Stock Entry'),
                    ], $user);

                    $totalReceived += $qty;
                    $sizesCount++;
                }
            });

            Notification::make()
                ->title('Batch Stock Received')
                ->body("Received {$totalReceived} units across {$sizesCount} sizes atomically.")
                ->success()
                ->send();

            // Reload matrix with updated stock levels
            $this->loadMatrixGrid();
        } catch (\Throwable $e) {
            Notification::make()
                ->title('Matrix Receiving Failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function getWarehousesProperty()
    {
        return Warehouse::where('is_active', true)->get();
    }

    public function getProductsProperty()
    {
        return Product::where('is_active', true)->orderBy('name')->get();
    }

    public function getSuppliersProperty()
    {
        return \App\Models\Inventory\Supplier::where('is_active', true)->orderBy('name')->get();
    }
}
