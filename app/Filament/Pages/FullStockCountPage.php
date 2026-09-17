<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\Inventory\StockCount;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\Warehouse;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Inventory\InventoryService;
use App\Services\ProductSkuService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class FullStockCountPage extends Page
{
    use WithPagination, WithFileUploads;

    protected string $view = 'filament.pages.full-stock-count';
    protected Width | string | null $maxWidth = 'full';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static string | \UnitEnum | null $navigationGroup = 'Inventory';
    protected static ?string $navigationLabel = 'Full Stock Count';
    protected static ?int $navigationSort = 38;
    protected static ?string $title = 'Full Stock Count & Cycle Reconciliation';
    protected static ?string $slug = 'inventory/full-stock-count';

    // Filters & Navigation
    public int $selectedWarehouseId = 1;
    public string $mode = 'count'; // 'count' | 'cycle'
    public string $cycleView = 'session'; // 'session' | 'diff' | 'catalog'
    public string $scanMode = 'increment'; // 'increment' (+1 per scan) | 'prompt'
    public ?int $selectedCategoryId = null;
    public ?string $selectedBrand = null;
    public string $search = '';
    public string $statusFilter = 'all'; // 'all' | 'diff' | 'matched' | 'short' | 'excess' | 'zero_stock' | 'negative_stock' | 'uncounted'
    public int $perPage = 50;

    // Barcode scanner input & HUD
    public string $barcodeInput = '';
    public ?string $highlightedItemKey = null;
    public ?array $lastScannedItem = null;
    public array $recentScans = [];
    public array $scannedOrder = []; // [item_key] for recent-first sorting
    public bool $soundFeedback = true;

    // In-memory working count session: [item_key => counted_qty]
    public array $countedQuantities = [];
    public array $countedNotes = [];
    public array $itemMetadata = []; // [item_key => [product_id, variant_id, system_qty, unit_cost, product_name, sku]]

    // Modals
    public bool $showImportModal = false;
    public bool $showPasteModal = false;
    public $importFile = null;
    public string $pasteText = '';

    public bool $showReconcileModal = false;
    public string $reconciliationNotes = '';
    public bool $isReconciling = false;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user() ?? auth('web')->user() ?? auth()->user();
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        return $user->hasRole('Warehouse Manager', 'admin');
    }

    public function mount(): void
    {
        $defaultWh = app(InventoryService::class)->getDefaultWarehouse();
        $this->selectedWarehouseId = $defaultWh?->id ?: (Warehouse::first()?->id ?? 1);

        if (request()->query('mode') === 'cycle-count' || request()->query('tab') === 'cycle-count') {
            $this->mode = 'cycle';
        }
        if (request()->has('warehouse_id')) {
            $this->selectedWarehouseId = (int) request()->query('warehouse_id');
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedCategoryId(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedBrand(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSelectedWarehouseId(): void
    {
        $this->itemMetadata = [];
        $this->resetPage();
    }

    /**
     * Resolve item metadata from cache or database on-demand.
     */
    public function resolveItemMetadata(string $itemKey): array
    {
        if (isset($this->itemMetadata[$itemKey])) {
            return $this->itemMetadata[$itemKey];
        }

        if (str_starts_with($itemKey, 'v_')) {
            $vId = (int) substr($itemKey, 2);
            $v = ProductVariant::with('product')->find($vId);
            if ($v) {
                $stock = (int) StockLevel::where('warehouse_id', $this->selectedWarehouseId)
                    ->where('variant_id', $v->id)
                    ->value('quantity_on_hand');
                return $this->itemMetadata[$itemKey] = [
                    'product_id' => $v->product_id,
                    'variant_id' => $v->id,
                    'product_name' => ($v->product?->name ?? 'Product') . ($v->size || $v->color ? " ({$v->color}/{$v->size})" : ""),
                    'sku' => $v->sku,
                    'barcode' => $v->barcode ?: ($v->product?->barcode ?: ''),
                    'size' => $v->size ?? '',
                    'color' => $v->color ?? '',
                    'brand' => $v->product?->brand ?? '',
                    'system_qty' => $stock,
                    'unit_cost' => (float) ($v->cost_price ?: ($v->product?->cost_price ?: 0)),
                ];
            }
        } elseif (str_starts_with($itemKey, 'p_')) {
            $pId = (int) substr($itemKey, 2);
            $p = Product::find($pId);
            if ($p) {
                $stock = (int) StockLevel::where('warehouse_id', $this->selectedWarehouseId)
                    ->where('product_id', $p->id)
                    ->whereNull('variant_id')
                    ->value('quantity_on_hand');
                return $this->itemMetadata[$itemKey] = [
                    'product_id' => $p->id,
                    'variant_id' => null,
                    'product_name' => $p->name,
                    'sku' => $p->sku,
                    'barcode' => $p->barcode ?: '',
                    'size' => '',
                    'color' => '',
                    'brand' => $p->brand ?? '',
                    'system_qty' => $stock,
                    'unit_cost' => (float) ($p->cost_price ?: 0),
                ];
            }
        }

        return $this->itemMetadata[$itemKey] = [
            'product_id' => 0,
            'variant_id' => null,
            'product_name' => $itemKey,
            'sku' => $itemKey,
            'barcode' => '',
            'size' => '',
            'color' => '',
            'brand' => '',
            'system_qty' => 0,
            'unit_cost' => 0.0,
        ];
    }

    public function updatedPerPage(): void
    {
        $this->resetPage();
    }

    public function setMode(string $mode): void
    {
        $this->mode = $mode;
        $this->resetPage();
    }

    public function setCycleView(string $view): void
    {
        $this->cycleView = $view;
        $this->resetPage();
    }

    public function setScanMode(string $mode): void
    {
        $this->scanMode = $mode;
    }

    /**
     * Rapid Barcode / SKU Scan handler.
     */
    public function handleBarcodeScan(): void
    {
        $code = trim($this->barcodeInput);
        if (empty($code)) {
            return;
        }

        // 1. Search in ProductVariant by barcode or SKU
        $variant = ProductVariant::where('barcode', $code)
            ->orWhere('sku', $code)
            ->first();

        $itemKey = null;
        $itemName = '';
        $sku = '';

        if ($variant) {
            $itemKey = "v_{$variant->id}";
            $itemName = ($variant->product?->name ?? 'Product') . ($variant->size || $variant->color ? " ({$variant->color}/{$variant->size})" : "");
            $sku = $variant->sku;

            if (!isset($this->itemMetadata[$itemKey])) {
                $stock = (int) StockLevel::where('warehouse_id', $this->selectedWarehouseId)
                    ->where('variant_id', $variant->id)
                    ->value('quantity_on_hand');
                $this->itemMetadata[$itemKey] = [
                    'product_id' => $variant->product_id,
                    'variant_id' => $variant->id,
                    'product_name' => $itemName,
                    'sku' => $sku,
                    'system_qty' => $stock,
                    'unit_cost' => (float) ($variant->cost_price ?: ($variant->product?->cost_price ?: 0)),
                ];
            }
        } else {
            // 2. Search in Product by barcode or SKU
            $product = Product::where('barcode', $code)
                ->orWhere('sku', $code)
                ->first();

            if ($product) {
                $itemKey = "p_{$product->id}";
                $itemName = $product->name;
                $sku = $product->sku;

                if (!isset($this->itemMetadata[$itemKey])) {
                    $stock = (int) StockLevel::where('warehouse_id', $this->selectedWarehouseId)
                        ->where('product_id', $product->id)
                        ->whereNull('variant_id')
                        ->value('quantity_on_hand');
                    $this->itemMetadata[$itemKey] = [
                        'product_id' => $product->id,
                        'variant_id' => null,
                        'product_name' => $itemName,
                        'sku' => $sku,
                        'system_qty' => $stock,
                        'unit_cost' => (float) ($product->cost_price ?: 0),
                    ];
                }
            }
        }

        if (!$itemKey) {
            Notification::make()
                ->title('Item Not Found')
                ->body("No product or variant found matching barcode/SKU: {$code}")
                ->warning()
                ->send();
            $this->barcodeInput = '';
            return;
        }

        $this->highlightedItemKey = $itemKey;
        $meta = $this->resolveItemMetadata($itemKey);
        $systemQty = (int) $meta['system_qty'];

        // Track in recent scan order
        $this->scannedOrder = array_values(array_diff($this->scannedOrder, [$itemKey]));
        array_unshift($this->scannedOrder, $itemKey);

        if ($this->mode === 'cycle') {
            $current = isset($this->countedQuantities[$itemKey]) && is_numeric($this->countedQuantities[$itemKey])
                ? (int) $this->countedQuantities[$itemKey]
                : 0;
            $newQty = $current + 1;
            $this->countedQuantities[$itemKey] = $newQty;
            $diff = $newQty - $systemQty;
            $unitCost = (float) ($meta['unit_cost'] ?? 0);

            $scanEntry = [
                'item_key' => $itemKey,
                'name' => $meta['product_name'],
                'sku' => $meta['sku'],
                'barcode' => $meta['barcode'] ?? '',
                'size' => $meta['size'] ?? '',
                'color' => $meta['color'] ?? '',
                'brand' => $meta['brand'] ?? '',
                'unit_cost' => $unitCost,
                'diff_value' => $diff * $unitCost,
                'system_qty' => $systemQty,
                'counted_qty' => $newQty,
                'diff' => $diff,
                'time' => now()->format('H:i:s'),
            ];

            $this->lastScannedItem = $scanEntry;
            array_unshift($this->recentScans, $scanEntry);
            $this->recentScans = array_slice($this->recentScans, 0, 8);

            Notification::make()
                ->title('Count Updated')
                ->body("{$itemName} (SKU: {$sku}) count is now {$newQty} (" . ($diff >= 0 ? "+{$diff}" : $diff) . ").")
                ->success()
                ->send();
        } else {
            $diff = isset($this->countedQuantities[$itemKey]) ? ((int)$this->countedQuantities[$itemKey] - $systemQty) : null;
            $unitCost = (float) ($meta['unit_cost'] ?? 0);
            $this->lastScannedItem = [
                'item_key' => $itemKey,
                'name' => $meta['product_name'],
                'sku' => $meta['sku'],
                'barcode' => $meta['barcode'] ?? '',
                'size' => $meta['size'] ?? '',
                'color' => $meta['color'] ?? '',
                'brand' => $meta['brand'] ?? '',
                'unit_cost' => $unitCost,
                'diff_value' => $diff !== null ? ($diff * $unitCost) : 0,
                'system_qty' => $systemQty,
                'counted_qty' => $this->countedQuantities[$itemKey] ?? null,
                'diff' => $diff,
                'time' => now()->format('H:i:s'),
            ];

            Notification::make()
                ->title('Item Located')
                ->body("Focused on {$itemName} (SKU: {$sku}). Enter physical quantity.")
                ->info()
                ->send();
        }

        $this->barcodeInput = '';
        $this->dispatch('item-scanned', [
            'itemKey' => $itemKey,
            'diff' => $diff,
            'isMatch' => ($diff === 0),
            'sound' => $this->soundFeedback,
        ]);
    }

    /**
     * Adjust count of the last scanned item by a delta (+1, -1, +5, etc).
     */
    public function adjustLastScanned(int $delta): void
    {
        if (!$this->lastScannedItem) {
            return;
        }

        $itemKey = $this->lastScannedItem['item_key'];
        $meta = $this->resolveItemMetadata($itemKey);
        $systemQty = (int) $meta['system_qty'];

        $current = isset($this->countedQuantities[$itemKey]) && is_numeric($this->countedQuantities[$itemKey])
            ? (int) $this->countedQuantities[$itemKey]
            : 0;

        $newQty = max(0, $current + $delta);
        $this->countedQuantities[$itemKey] = $newQty;
        $diff = $newQty - $systemQty;
        $unitCost = (float) ($meta['unit_cost'] ?? 0);

        $this->lastScannedItem['counted_qty'] = $newQty;
        $this->lastScannedItem['diff'] = $diff;
        $this->lastScannedItem['diff_value'] = $diff * $unitCost;

        // Keep at top of scanned order
        $this->scannedOrder = array_values(array_diff($this->scannedOrder, [$itemKey]));
        array_unshift($this->scannedOrder, $itemKey);

        foreach ($this->recentScans as &$scan) {
            if ($scan['item_key'] === $itemKey) {
                $scan['counted_qty'] = $newQty;
                $scan['diff'] = $diff;
                $scan['diff_value'] = $diff * $unitCost;
                break;
            }
        }
    }

    /**
     * Revert or decrement the last scan.
     */
    public function undoLastScan(): void
    {
        if (!$this->lastScannedItem) {
            return;
        }

        $itemKey = $this->lastScannedItem['item_key'];
        $current = isset($this->countedQuantities[$itemKey]) && is_numeric($this->countedQuantities[$itemKey])
            ? (int) $this->countedQuantities[$itemKey]
            : 0;

        if ($current <= 1) {
            unset($this->countedQuantities[$itemKey]);
            $this->scannedOrder = array_values(array_diff($this->scannedOrder, [$itemKey]));
            $this->recentScans = array_values(array_filter($this->recentScans, fn($s) => $s['item_key'] !== $itemKey));
            $this->lastScannedItem = !empty($this->recentScans) ? $this->recentScans[0] : null;
        } else {
            $this->adjustLastScanned(-1);
        }
    }

    /**
     * Toggle scanner beep sound feedback.
     */
    public function toggleSoundFeedback(): void
    {
        $this->soundFeedback = !$this->soundFeedback;
    }

    /**
     * Set exact count for the last scanned item.
     */
    public function setLastScannedCount(int $qty): void
    {
        if (!$this->lastScannedItem) {
            return;
        }

        $itemKey = $this->lastScannedItem['item_key'];
        $meta = $this->resolveItemMetadata($itemKey);
        $systemQty = (int) $meta['system_qty'];

        $newQty = max(0, $qty);
        $this->countedQuantities[$itemKey] = $newQty;
        $diff = $newQty - $systemQty;
        $unitCost = (float) ($meta['unit_cost'] ?? 0);

        $this->lastScannedItem['counted_qty'] = $newQty;
        $this->lastScannedItem['diff'] = $diff;
        $this->lastScannedItem['diff_value'] = $diff * $unitCost;

        // Keep at top of scanned order
        $this->scannedOrder = array_values(array_diff($this->scannedOrder, [$itemKey]));
        array_unshift($this->scannedOrder, $itemKey);

        foreach ($this->recentScans as &$scan) {
            if ($scan['item_key'] === $itemKey) {
                $scan['counted_qty'] = $newQty;
                $scan['diff'] = $diff;
                $scan['diff_value'] = $diff * $unitCost;
                break;
            }
        }
    }

    /**
     * Adjust count of any specific item by delta (+1, -1, etc).
     */
    public function adjustItemCount(string $itemKey, int $delta): void
    {
        $meta = $this->resolveItemMetadata($itemKey);
        $systemQty = (int) $meta['system_qty'];

        $current = isset($this->countedQuantities[$itemKey]) && is_numeric($this->countedQuantities[$itemKey])
            ? (int) $this->countedQuantities[$itemKey]
            : 0;

        $newQty = max(0, $current + $delta);
        $this->countedQuantities[$itemKey] = $newQty;
        $diff = $newQty - $systemQty;
        $unitCost = (float) ($meta['unit_cost'] ?? 0);

        $this->scannedOrder = array_values(array_diff($this->scannedOrder, [$itemKey]));
        array_unshift($this->scannedOrder, $itemKey);

        $scanEntry = [
            'item_key' => $itemKey,
            'name' => $meta['product_name'],
            'sku' => $meta['sku'],
            'barcode' => $meta['barcode'] ?? '',
            'size' => $meta['size'] ?? '',
            'color' => $meta['color'] ?? '',
            'brand' => $meta['brand'] ?? '',
            'unit_cost' => $unitCost,
            'diff_value' => $diff * $unitCost,
            'system_qty' => $systemQty,
            'counted_qty' => $newQty,
            'diff' => $diff,
            'time' => now()->format('H:i:s'),
        ];

        $this->lastScannedItem = $scanEntry;

        foreach ($this->recentScans as &$scan) {
            if ($scan['item_key'] === $itemKey) {
                $scan['counted_qty'] = $newQty;
                $scan['diff'] = $diff;
                $scan['diff_value'] = $diff * $unitCost;
                return;
            }
        }
        array_unshift($this->recentScans, $scanEntry);
        $this->recentScans = array_slice($this->recentScans, 0, 8);
    }

    /**
     * Select an item from recent scans or table into the Hero HUD.
     */
    public function selectScannedItem(string $itemKey): void
    {
        $meta = $this->resolveItemMetadata($itemKey);
        $systemQty = (int) $meta['system_qty'];
        $counted = isset($this->countedQuantities[$itemKey]) && is_numeric($this->countedQuantities[$itemKey])
            ? (int) $this->countedQuantities[$itemKey]
            : null;

        $diff = $counted !== null ? ($counted - $systemQty) : null;
        $unitCost = (float) ($meta['unit_cost'] ?? 0);

        $this->lastScannedItem = [
            'item_key' => $itemKey,
            'name' => $meta['product_name'],
            'sku' => $meta['sku'],
            'barcode' => $meta['barcode'] ?? '',
            'size' => $meta['size'] ?? '',
            'color' => $meta['color'] ?? '',
            'brand' => $meta['brand'] ?? '',
            'unit_cost' => $unitCost,
            'diff_value' => $diff !== null ? ($diff * $unitCost) : 0,
            'system_qty' => $systemQty,
            'counted_qty' => $counted,
            'diff' => $diff,
            'time' => now()->format('H:i:s'),
        ];
        $this->highlightedItemKey = $itemKey;
    }

    /**
     * Match System Quantity quick-action.
     */
    public function matchSystemQty(string $itemKey, int $systemQty): void
    {
        $this->countedQuantities[$itemKey] = $systemQty;
        if (!in_array($itemKey, $this->scannedOrder, true)) {
            array_unshift($this->scannedOrder, $itemKey);
        }
    }

    /**
     * Set Zero Stock quick-action.
     */
    public function setZeroStock(string $itemKey): void
    {
        $this->countedQuantities[$itemKey] = 0;
        if (!in_array($itemKey, $this->scannedOrder, true)) {
            array_unshift($this->scannedOrder, $itemKey);
        }
    }

    /**
     * Clear count for a single item.
     */
    public function clearItemCount(string $itemKey): void
    {
        unset($this->countedQuantities[$itemKey]);
        unset($this->countedNotes[$itemKey]);
        $this->scannedOrder = array_values(array_diff($this->scannedOrder, [$itemKey]));
        if ($this->lastScannedItem && ($this->lastScannedItem['item_key'] ?? null) === $itemKey) {
            $this->lastScannedItem = null;
        }
    }

    /**
     * Reset all session counts.
     */
    public function clearAllCounts(): void
    {
        $this->countedQuantities = [];
        $this->countedNotes = [];
        $this->scannedOrder = [];
        $this->lastScannedItem = null;
        $this->recentScans = [];
        $this->highlightedItemKey = null;

        Notification::make()
            ->title('Counts Cleared')
            ->body('Working count entries have been reset.')
            ->info()
            ->send();
    }

    /**
     * Open Import Count Sheet Modal.
     */
    public function openImportModal(): void
    {
        $this->importFile = null;
        $this->pasteText = '';
        $this->showImportModal = true;
        $this->showPasteModal = true;
    }

    /**
     * Open Paste / Import Modal alias for backward compatibility.
     */
    public function openPasteModal(): void
    {
        $this->openImportModal();
    }

    /**
     * Process uploaded Excel (.xlsx) / CSV (.csv) count sheet or fallback pasted text.
     */
    public function processImport(): void
    {
        $parsedBatch = []; // [lookup_code_or_key => counted_qty]
        $itemKeyBatch = []; // [item_key => counted_qty]
        $unrecognizedCodes = [];

        if ($this->importFile) {
            try {
                $path = $this->importFile->getRealPath();
                $ext = $this->importFile->getClientOriginalExtension() ?: pathinfo($this->importFile->getClientOriginalName(), PATHINFO_EXTENSION);
                $rows = $this->parseSpreadsheetRows($path, (string)$ext);

                if (empty($rows)) {
                    $this->showImportModal = false;
                    $this->showPasteModal = false;
                    $this->importFile = null;
                    Notification::make()
                        ->title('Empty or Unreadable File')
                        ->body('No valid rows could be extracted from the uploaded spreadsheet.')
                        ->warning()
                        ->send();
                    return;
                }

                // Analyze header row (Row 0)
                $firstRow = $rows[0] ?? [];
                $headerIndices = $this->detectSpreadsheetHeaders($firstRow);

                $startRowIdx = ($headerIndices['has_header']) ? 1 : 0;
                $itemKeyCol = $headerIndices['item_key'];
                $skuCol = $headerIndices['sku'];
                $barcodeCol = $headerIndices['barcode'];
                $physicalQtyCol = $headerIndices['physical_qty'];

                for ($r = $startRowIdx; $r < count($rows); $r++) {
                    $row = $rows[$r];
                    if (empty($row) || (count($row) === 1 && trim((string)$row[0]) === '')) {
                        continue;
                    }

                    // Extract physical quantity
                    $qtyRaw = null;
                    if ($physicalQtyCol !== null && isset($row[$physicalQtyCol])) {
                        $qtyRaw = trim((string)$row[$physicalQtyCol]);
                    } elseif ($headerIndices['has_header'] === false && count($row) >= 2) {
                        $qtyRaw = trim((string)$row[count($row) - 1]);
                    }

                    // If Physical Qty is empty / blank / whitespace, the auditor did NOT count this item; skip it!
                    if ($qtyRaw === null || $qtyRaw === '' || $qtyRaw === '-' || strtolower($qtyRaw) === 'n/a') {
                        continue;
                    }

                    if (!is_numeric($qtyRaw)) {
                        continue;
                    }

                    $qty = max(0, (int) round((float) $qtyRaw));

                    // Extract Item Key (e.g. v_309 or p_12)
                    $itemKeyRaw = ($itemKeyCol !== null && isset($row[$itemKeyCol])) ? trim((string)$row[$itemKeyCol]) : '';
                    if ($itemKeyRaw !== '' && (str_starts_with($itemKeyRaw, 'v_') || str_starts_with($itemKeyRaw, 'p_'))) {
                        $itemKeyBatch[$itemKeyRaw] = $qty;
                        continue;
                    }

                    // Extract SKU
                    $skuRaw = ($skuCol !== null && isset($row[$skuCol])) ? trim((string)$row[$skuCol]) : '';
                    if ($skuRaw !== '') {
                        $parsedBatch[$skuRaw] = $qty;
                        continue;
                    }

                    // Extract Barcode
                    $barcodeRaw = ($barcodeCol !== null && isset($row[$barcodeCol])) ? trim((string)$row[$barcodeCol]) : '';
                    if ($barcodeRaw !== '') {
                        $parsedBatch[$barcodeRaw] = $qty;
                        continue;
                    }

                    // Fallback to first non-numeric column in row
                    foreach ($row as $idx => $cellVal) {
                        $cellStr = trim((string)$cellVal);
                        if ($cellStr !== '' && !is_numeric($cellStr)) {
                            $parsedBatch[$cellStr] = $qty;
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Notification::make()
                    ->title('File Parsing Error')
                    ->body('Failed to read spreadsheet: ' . $e->getMessage())
                    ->danger()
                    ->send();
                return;
            }
        } elseif (!empty(trim($this->pasteText))) {
            // Textarea paste fallback
            $text = trim($this->pasteText);
            $lines = preg_split('/[\r\n]+/', $text);

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line)) {
                    continue;
                }

                if (preg_match('/^(sku|barcode|code|item|product|qty|quantity|count)/i', $line) && !preg_match('/\d/', $line)) {
                    continue;
                }

                $code = null;
                $qty = 1;
                $isBare = false;

                if (str_contains($line, ',')) {
                    $parts = explode(',', $line, 2);
                    $code = trim($parts[0], " \t\n\r\0\x0B\"'");
                    $qty = (int) trim($parts[1], " \t\n\r\0\x0B\"'");
                } elseif (str_contains($line, "\t")) {
                    $parts = explode("\t", $line);
                    $code = trim($parts[0], " \t\n\r\0\x0B\"'");
                    $lastToken = trim(end($parts), " \t\n\r\0\x0B\"'");
                    $qty = is_numeric($lastToken) ? (int)$lastToken : 1;
                } elseif (str_contains($line, ';')) {
                    $parts = explode(';', $line, 2);
                    $code = trim($parts[0], " \t\n\r\0\x0B\"'");
                    $qty = (int) trim($parts[1], " \t\n\r\0\x0B\"'");
                } elseif (str_contains($line, ':')) {
                    $parts = explode(':', $line, 2);
                    $code = trim($parts[0], " \t\n\r\0\x0B\"'");
                    $qty = (int) trim($parts[1], " \t\n\r\0\x0B\"'");
                } elseif (str_contains($line, '=')) {
                    $parts = explode('=', $line, 2);
                    $code = trim($parts[0], " \t\n\r\0\x0B\"'");
                    $qty = (int) trim($parts[1], " \t\n\r\0\x0B\"'");
                } else {
                    $tokens = preg_split('/\s+/', $line);
                    if (count($tokens) >= 2 && is_numeric(end($tokens))) {
                        $qty = (int) array_pop($tokens);
                        $code = trim(implode(' ', $tokens), " \t\n\r\0\x0B\"'");
                    } else {
                        $code = trim($line, " \t\n\r\0\x0B\"'");
                        $qty = 1;
                        $isBare = true;
                    }
                }

                if (empty($code)) {
                    continue;
                }

                if (str_starts_with($code, 'v_') || str_starts_with($code, 'p_')) {
                    $itemKeyBatch[$code] = max(0, $qty);
                } elseif ($isBare) {
                    $parsedBatch[$code] = ($parsedBatch[$code] ?? 0) + 1;
                } else {
                    $parsedBatch[$code] = max(0, $qty);
                }
            }
        }

        if (empty($parsedBatch) && empty($itemKeyBatch)) {
            $this->showImportModal = false;
            $this->showPasteModal = false;
            $this->importFile = null;
            Notification::make()
                ->title('No Physical Counts Detected')
                ->body('Please check that the Physical Qty column contains numeric count values.')
                ->warning()
                ->send();
            return;
        }

        // 1. Resolve direct Item Keys (e.g. from downloaded stock count sheet)
        $matchedMap = []; // [lookup_code_or_key => meta_array]
        if (!empty($itemKeyBatch)) {
            $variantIds = [];
            $productIds = [];
            foreach (array_keys($itemKeyBatch) as $key) {
                if (str_starts_with($key, 'v_')) {
                    $variantIds[] = (int) substr($key, 2);
                } elseif (str_starts_with($key, 'p_')) {
                    $productIds[] = (int) substr($key, 2);
                }
            }

            if (!empty($variantIds)) {
                $variants = ProductVariant::with('product')->whereIn('id', array_unique($variantIds))->get();
                foreach ($variants as $v) {
                    $key = "v_{$v->id}";
                    $matchedMap[$key] = [
                        'item_key' => $key,
                        'product_id' => $v->product_id,
                        'variant_id' => $v->id,
                        'product_name' => ($v->product?->name ?? 'Product') . ($v->size || $v->color ? " ({$v->color}/{$v->size})" : ""),
                        'sku' => $v->sku ?: ($v->product?->sku ?? ''),
                        'unit_cost' => (float) ($v->cost_price ?: ($v->product?->cost_price ?: 0)),
                    ];
                }
            }

            if (!empty($productIds)) {
                $products = Product::whereIn('id', array_unique($productIds))->get();
                foreach ($products as $p) {
                    $key = "p_{$p->id}";
                    $matchedMap[$key] = [
                        'item_key' => $key,
                        'product_id' => $p->id,
                        'variant_id' => null,
                        'product_name' => $p->name,
                        'sku' => $p->sku ?: '',
                        'unit_cost' => (float) ($p->cost_price ?: 0),
                    ];
                }
            }
        }

        // 2. Resolve SKU / Barcode codes
        if (!empty($parsedBatch)) {
            $allCodes = array_keys($parsedBatch);
            $variants = ProductVariant::with('product')
                ->whereIn('sku', $allCodes)
                ->orWhereIn('barcode', $allCodes)
                ->get();

            $products = Product::whereIn('sku', $allCodes)
                ->orWhereIn('barcode', $allCodes)
                ->get();

            foreach ($variants as $v) {
                $key = "v_{$v->id}";
                $meta = [
                    'item_key' => $key,
                    'product_id' => $v->product_id,
                    'variant_id' => $v->id,
                    'product_name' => ($v->product?->name ?? 'Product') . ($v->size || $v->color ? " ({$v->color}/{$v->size})" : ""),
                    'sku' => $v->sku ?: ($v->product?->sku ?? ''),
                    'unit_cost' => (float) ($v->cost_price ?: ($v->product?->cost_price ?: 0)),
                ];
                if ($v->sku) $matchedMap[$v->sku] = $meta;
                if ($v->barcode) $matchedMap[$v->barcode] = $meta;
            }

            foreach ($products as $p) {
                $key = "p_{$p->id}";
                $meta = [
                    'item_key' => $key,
                    'product_id' => $p->id,
                    'variant_id' => null,
                    'product_name' => $p->name,
                    'sku' => $p->sku ?: '',
                    'unit_cost' => (float) ($p->cost_price ?: 0),
                ];
                if ($p->sku && !isset($matchedMap[$p->sku])) $matchedMap[$p->sku] = $meta;
                if ($p->barcode && !isset($matchedMap[$p->barcode])) $matchedMap[$p->barcode] = $meta;
            }
        }

        // 3. Batch query stock levels in target warehouse
        $vIds = [];
        $pIds = [];
        foreach ($matchedMap as $m) {
            if ($m['variant_id']) {
                $vIds[] = $m['variant_id'];
            } elseif ($m['product_id']) {
                $pIds[] = $m['product_id'];
            }
        }

        $stockLevels = StockLevel::where('warehouse_id', $this->selectedWarehouseId)
            ->where(function ($q) use ($vIds, $pIds) {
                if (!empty($vIds)) {
                    $q->whereIn('variant_id', array_unique($vIds));
                }
                if (!empty($pIds)) {
                    $q->orWhere(function ($sub) use ($pIds) {
                        $sub->whereIn('product_id', array_unique($pIds))->whereNull('variant_id');
                    });
                }
            })
            ->get();

        $stockMap = [];
        foreach ($stockLevels as $sl) {
            if ($sl->variant_id) {
                $stockMap["v_{$sl->variant_id}"] = (int) $sl->quantity_on_hand;
            } elseif ($sl->product_id) {
                $stockMap["p_{$sl->product_id}"] = (int) $sl->quantity_on_hand;
            }
        }

        // 4. Merge counts into active working session
        $importedCount = 0;
        $unrecognizedCount = 0;
        $discrepancyCount = 0;
        $lastMatched = null;

        $allImportEntries = [];
        foreach ($itemKeyBatch as $k => $v) {
            $allImportEntries[(string)$k] = $v;
        }
        foreach ($parsedBatch as $k => $v) {
            $allImportEntries[(string)$k] = $v;
        }
        foreach ($allImportEntries as $lookup => $qty) {
            if (!isset($matchedMap[$lookup])) {
                $unrecognizedCodes[] = $lookup;
                $unrecognizedCount++;
                continue;
            }

            $m = $matchedMap[$lookup];
            $itemKey = $m['item_key'];
            $this->countedQuantities[$itemKey] = $qty;

            $systemQty = $stockMap[$itemKey] ?? 0;

            $this->itemMetadata[$itemKey] = [
                'product_id' => $m['product_id'],
                'variant_id' => $m['variant_id'],
                'product_name' => $m['product_name'],
                'sku' => $m['sku'],
                'system_qty' => $systemQty,
                'unit_cost' => $m['unit_cost'],
            ];

            $this->scannedOrder = array_values(array_diff($this->scannedOrder, [$itemKey]));
            array_unshift($this->scannedOrder, $itemKey);

            $diff = $qty - $systemQty;
            if ($diff !== 0) {
                $discrepancyCount++;
            }

            $lastMatched = [
                'item_key' => $itemKey,
                'name' => $m['product_name'],
                'sku' => $m['sku'],
                'system_qty' => $systemQty,
                'counted_qty' => $qty,
                'diff' => $diff,
                'time' => now()->format('H:i:s'),
            ];

            $importedCount++;
        }

        if ($lastMatched) {
            $this->lastScannedItem = $lastMatched;
            array_unshift($this->recentScans, $lastMatched);
            $this->recentScans = array_slice($this->recentScans, 0, 8);
        }

        if ($this->mode === 'cycle') {
            $this->cycleView = 'session';
        }

        $this->showImportModal = false;
        $this->showPasteModal = false;
        $this->importFile = null;
        $this->pasteText = '';
        $this->dispatch('item-scanned');

        $msg = "Imported {$importedCount} physical count entries into active session.";
        if ($discrepancyCount > 0) {
            $msg .= " ({$discrepancyCount} discrepancies detected).";
        }
        if ($unrecognizedCount > 0) {
            $msg .= " ({$unrecognizedCount} codes were not found in catalog).";
        }

        Notification::make()
            ->title('Count Sheet Imported Successfully')
            ->body($msg)
            ->success()
            ->duration(7000)
            ->send();
    }

    /**
     * Backward compatible wrapper for processPaste.
     */
    public function processPaste(): void
    {
        $this->processImport();
    }

    /**
     * Parse spreadsheet rows from uploaded file (CSV or XLSX).
     */
    protected function parseSpreadsheetRows(string $path, string $extension = ''): array
    {
        $extension = strtolower($extension ?: pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'xlsx' || $extension === 'xlsm') {
            $rows = $this->parseXlsxFile($path);
            if (!empty($rows)) {
                return $rows;
            }
        }

        return $this->parseCsvFile($path);
    }

    /**
     * Parse OpenXML (.xlsx) using PHP ZipArchive and SimpleXML.
     */
    protected function parseXlsxFile(string $path): array
    {
        if (!class_exists('\ZipArchive')) {
            return [];
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            return [];
        }

        // 1. Extract shared strings
        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $xmlObj = @simplexml_load_string($sharedXml);
            if ($xmlObj && isset($xmlObj->si)) {
                foreach ($xmlObj->si as $si) {
                    $str = (string) $si->t;
                    if ($str === '' && isset($si->r)) {
                        foreach ($si->r as $r) {
                            $str .= (string) $r->t;
                        }
                    }
                    $sharedStrings[] = $str;
                }
            }
        }

        // 2. Locate worksheet XML
        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        if ($sheetXml === false) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if (preg_match('/xl\/worksheets\/sheet\d+\.xml/i', $stat['name'])) {
                    $sheetXml = $zip->getFromName($stat['name']);
                    break;
                }
            }
        }

        $zip->close();

        if ($sheetXml === false) {
            return [];
        }

        $xml = @simplexml_load_string($sheetXml);
        if (!$xml || !isset($xml->sheetData->row)) {
            return [];
        }

        $rows = [];
        foreach ($xml->sheetData->row as $rowNode) {
            $rowCells = [];
            foreach ($rowNode->c as $cNode) {
                $cellRef = (string) $cNode['r'];
                $colLetters = preg_replace('/[0-9]/', '', $cellRef);
                $colIndex = 0;
                for ($i = 0; $i < strlen($colLetters); $i++) {
                    $colIndex = $colIndex * 26 + (ord(strtoupper($colLetters[$i])) - ord('A') + 1);
                }
                $colIndex = max(0, $colIndex - 1);

                $cellType = (string) $cNode['t'];
                $val = (string) $cNode->v;

                if ($cellType === 's') {
                    $val = $sharedStrings[(int) $val] ?? '';
                } elseif ($cellType === 'inlineStr' && isset($cNode->is->t)) {
                    $val = (string) $cNode->is->t;
                }

                $rowCells[$colIndex] = trim($val);
            }

            if (!empty($rowCells)) {
                $maxCol = max(array_keys($rowCells));
                $normalizedRow = [];
                for ($i = 0; $i <= $maxCol; $i++) {
                    $normalizedRow[$i] = $rowCells[$i] ?? '';
                }
                $rows[] = $normalizedRow;
            }
        }

        return $rows;
    }

    /**
     * Parse CSV or delimited file with automatic delimiter detection and UTF-8 BOM stripping.
     */
    protected function parseCsvFile(string $path): array
    {
        $rows = [];
        $handle = @fopen($path, 'r');
        if (!$handle) {
            return [];
        }

        // Check BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        $firstLine = fgets($handle);
        if ($firstLine === false) {
            fclose($handle);
            return [];
        }

        $delimiter = ',';
        $candidates = [',', "\t", ';', '|'];
        $bestCount = 0;
        foreach ($candidates as $cand) {
            $cCount = substr_count($firstLine, $cand);
            if ($cCount > $bestCount) {
                $bestCount = $cCount;
                $delimiter = $cand;
            }
        }

        rewind($handle);
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (empty($data) || (count($data) === 1 && trim((string) $data[0]) === '')) {
                continue;
            }
            $rows[] = array_map('trim', $data);
        }

        fclose($handle);
        return $rows;
    }

    /**
     * Auto-detect spreadsheet header columns.
     */
    protected function detectSpreadsheetHeaders(array $firstRow): array
    {
        $res = [
            'has_header' => false,
            'item_key' => null,
            'sku' => null,
            'barcode' => null,
            'physical_qty' => null,
            'system_qty' => null,
        ];

        foreach ($firstRow as $idx => $cell) {
            $c = strtolower(trim((string)$cell));
            if ($c === '') continue;

            if (in_array($c, ['item key', 'item_key', 'key', 'itemkey', 'item_id'], true)) {
                $res['item_key'] = $idx;
                $res['has_header'] = true;
            } elseif (in_array($c, ['sku', 'item code', 'item_code', 'product sku', 'variant sku', 'code'], true)) {
                $res['sku'] = $idx;
                $res['has_header'] = true;
            } elseif (in_array($c, ['barcode', 'bar code', 'ean', 'upc'], true)) {
                $res['barcode'] = $idx;
                $res['has_header'] = true;
            } elseif (in_array($c, ['physical qty', 'physical quantity', 'physical count', 'counted qty', 'counted quantity', 'counted', 'physical', 'count', 'qty', 'quantity'], true)) {
                $res['physical_qty'] = $idx;
                $res['has_header'] = true;
            } elseif (in_array($c, ['system qty', 'system quantity', 'sys qty', 'sys stock', 'system stock', 'expected qty'], true)) {
                $res['system_qty'] = $idx;
                $res['has_header'] = true;
            }
        }

        return $res;
    }

    /**
     * Open Review & Reconcile Modal.
     */
    public function openReconcileModal(): void
    {
        if (empty($this->countedQuantities)) {
            Notification::make()
                ->title('No Counts Entered')
                ->body('Please enter at least one physical quantity before reconciling.')
                ->warning()
                ->send();
            return;
        }

        $wh = Warehouse::find($this->selectedWarehouseId);
        $this->reconciliationNotes = "Cycle count reconciliation for " . ($wh?->name ?? 'Warehouse') . " on " . now()->format('Y-m-d');
        $this->showReconcileModal = true;
    }

    /**
     * Authoritative Reconciliation Execution.
     */
    public function confirmReconciliation(): void
    {
        if (empty($this->countedQuantities)) {
            $this->showReconcileModal = false;
            return;
        }

        $this->isReconciling = true;

        try {
            $reconcileItems = [];

            foreach ($this->countedQuantities as $itemKey => $qty) {
                if ($qty === '' || $qty === null) {
                    continue;
                }

                $countedQty = (int) $qty;
                $meta = $this->resolveItemMetadata($itemKey);

                if (empty($meta['product_id'])) {
                    continue;
                }

                $reconcileItems[] = [
                    'product_id' => $meta['product_id'],
                    'variant_id' => $meta['variant_id'],
                    'expected_quantity' => (int) $meta['system_qty'],
                    'counted_quantity' => $countedQty,
                    'unit_cost' => (float) $meta['unit_cost'],
                    'notes' => $this->countedNotes[$itemKey] ?? null,
                ];
            }

            if (empty($reconcileItems)) {
                throw new \RuntimeException("No valid items to reconcile.");
            }

            $countType = ($this->mode === 'cycle') ? 'cycle' : 'full';
            $stockCount = app(InventoryService::class)->executeStockCountReconciliation(
                $this->selectedWarehouseId,
                $reconcileItems,
                $countType,
                $this->reconciliationNotes,
                auth()->user()
            );

            $this->showReconcileModal = false;
            $this->countedQuantities = [];
            $this->countedNotes = [];
            $this->highlightedItemKey = null;

            Notification::make()
                ->title('Stock Reconciled Successfully')
                ->body("Reconciled " . count($reconcileItems) . " items under audit #{$stockCount->count_number}. Stock levels and ledgers are fully synchronized.")
                ->success()
                ->duration(8000)
                ->send();

        } catch (\Throwable $e) {
            Notification::make()
                ->title('Reconciliation Failed')
                ->body($e->getMessage())
                ->danger()
                ->duration(10000)
                ->send();
        } finally {
            $this->isReconciling = false;
        }
    }

    /**
     * Compute real-time summary statistics of the current count session.
     */
    public function getSummaryStatsProperty(): array
    {
        $totalCounted = 0;
        $totalUnits = 0;
        $matching = 0;
        $shortage = 0;
        $excess = 0;
        $totalDiff = 0;
        $totalDiffValue = 0.00;

        foreach ($this->countedQuantities as $itemKey => $qty) {
            if ($qty === '' || $qty === null) {
                continue;
            }

            $counted = (int) $qty;
            $totalCounted++;
            $totalUnits += $counted;

            $meta = $this->resolveItemMetadata($itemKey);
            $expected = (int) $meta['system_qty'];
            $cost = (float) $meta['unit_cost'];

            $diff = $counted - $expected;
            $totalDiff += $diff;
            $totalDiffValue += ($diff * $cost);

            if ($diff === 0) {
                $matching++;
            } elseif ($diff < 0) {
                $shortage++;
            } else {
                $excess++;
            }
        }

        return [
            'total_counted' => $totalCounted,
            'total_units' => $totalUnits,
            'matching' => $matching,
            'shortage' => $shortage,
            'excess' => $excess,
            'total_diff' => $totalDiff,
            'total_diff_value' => $totalDiffValue,
        ];
    }

    /**
     * Generate downloadable Excel/CSV count sheet URL with current filters.
     */
    public function getExportUrlProperty(): string
    {
        $params = [
            'warehouse_id' => $this->selectedWarehouseId,
        ];
        if (!empty($this->selectedCategoryId)) {
            $params['category_id'] = $this->selectedCategoryId;
        }
        if (!empty($this->selectedBrand)) {
            $params['brand'] = $this->selectedBrand;
        }
        if (!empty($this->search)) {
            $params['search'] = $this->search;
        }
        if ($this->statusFilter !== 'all') {
            $params['status'] = $this->statusFilter;
        }

        return route('admin.full-stock-count.export', $params);
    }

    /**
     * Generate printable count sheet URL with current filters.
     */
    public function getPrintUrlProperty(): string
    {
        $params = [
            'warehouse_id' => $this->selectedWarehouseId,
        ];
        if (!empty($this->selectedCategoryId)) {
            $params['category_id'] = $this->selectedCategoryId;
        }
        if (!empty($this->selectedBrand)) {
            $params['brand'] = $this->selectedBrand;
        }
        if (!empty($this->search)) {
            $params['search'] = $this->search;
        }
        if ($this->statusFilter !== 'all') {
            $params['status'] = $this->statusFilter;
        }

        return route('admin.full-stock-count.print', $params);
    }

    /**
     * Build unified paginated items query.
     */
    public function getItemsProperty(): LengthAwarePaginator
    {
        $whId = $this->selectedWarehouseId;

        // Counted parent product keys in working session
        $countedParentIds = [];
        foreach ($this->countedQuantities as $k => $qty) {
            if (str_starts_with($k, 'p_') && $qty !== '' && $qty !== null) {
                $countedParentIds[] = (int) substr($k, 2);
            }
        }

        // 1. Non-variant products subquery (and any parent product explicitly counted in the working session)
        $q1 = DB::table('products as p')
            ->where('p.is_active', true)
            ->where(function ($sub) use ($countedParentIds) {
                $sub->whereNotExists(function ($q) {
                    $q->select(DB::raw(1))
                        ->from('product_variants as pv')
                        ->whereColumn('pv.product_id', 'p.id')
                        ->where('pv.is_active', true);
                });
                if (!empty($countedParentIds)) {
                    $sub->orWhereIn('p.id', $countedParentIds);
                }
            })
            ->leftJoin('inventory_stock_levels as sl', function ($join) use ($whId) {
                $join->on('sl.product_id', '=', 'p.id')
                    ->whereNull('sl.variant_id')
                    ->where('sl.warehouse_id', '=', $whId);
            })
            ->select([
                DB::raw("'product' as item_type"),
                DB::raw("CONCAT('p_', p.id) as item_key"),
                'p.id as product_id',
                DB::raw('NULL as variant_id'),
                'p.name as product_name',
                'p.sku as sku',
                'p.barcode as barcode',
                DB::raw("'' as size"),
                DB::raw("'' as color"),
                'p.brand as brand',
                'p.cost_price as unit_cost',
                DB::raw('COALESCE(sl.quantity_on_hand, 0) as system_qty'),
            ]);

        // 2. Variant products subquery
        $q2 = DB::table('product_variants as pv')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('pv.is_active', true)
            ->where('p.is_active', true)
            ->leftJoin('inventory_stock_levels as sl', function ($join) use ($whId) {
                $join->on('sl.product_id', '=', 'p.id')
                    ->on('sl.variant_id', '=', 'pv.id')
                    ->where('sl.warehouse_id', '=', $whId);
            })
            ->select([
                DB::raw("'variant' as item_type"),
                DB::raw("CONCAT('v_', pv.id) as item_key"),
                'p.id as product_id',
                'pv.id as variant_id',
                'p.name as product_name',
                DB::raw('COALESCE(pv.sku, p.sku) as sku'),
                DB::raw('COALESCE(pv.barcode, p.barcode) as barcode'),
                DB::raw("COALESCE(pv.size, '') as size"),
                DB::raw("COALESCE(pv.color, '') as color"),
                'p.brand as brand',
                DB::raw('COALESCE(pv.cost_price, p.cost_price, 0) as unit_cost'),
                DB::raw('COALESCE(sl.quantity_on_hand, 0) as system_qty'),
            ]);

        // Filter Category
        if (!empty($this->selectedCategoryId)) {
            $catId = (int) $this->selectedCategoryId;
            $q1->whereExists(function ($q) use ($catId) {
                $q->select(DB::raw(1))
                    ->from('category_product')
                    ->whereColumn('category_product.product_id', 'p.id')
                    ->where('category_product.category_id', $catId);
            });
            $q2->whereExists(function ($q) use ($catId) {
                $q->select(DB::raw(1))
                    ->from('category_product')
                    ->whereColumn('category_product.product_id', 'p.id')
                    ->where('category_product.category_id', $catId);
            });
        }

        // Filter Brand
        if (!empty($this->selectedBrand)) {
            $q1->where('p.brand', $this->selectedBrand);
            $q2->where('p.brand', $this->selectedBrand);
        }

        $union = $q1->unionAll($q2);
        $outer = DB::table($union, 'items');

        // Cycle Count Mode View Filtering
        if ($this->mode === 'cycle') {
            if ($this->cycleView === 'session') {
                $countedKeys = array_keys(array_filter($this->countedQuantities, fn($q) => $q !== '' && $q !== null));
                if (empty($countedKeys)) {
                    $outer->whereRaw('1 = 0');
                } else {
                    $outer->whereIn('item_key', $countedKeys);
                }
            } elseif ($this->cycleView === 'diff') {
                $diffKeys = [];
                foreach ($this->countedQuantities as $k => $q) {
                    if ($q === '' || $q === null) continue;
                    $expected = $this->itemMetadata[$k]['system_qty'] ?? null;
                    if ($expected === null) {
                        $meta = $this->resolveItemMetadata($k);
                        $expected = $meta['system_qty'];
                    }
                    if ((int)$q !== (int)$expected) {
                        $diffKeys[] = $k;
                    }
                }
                if (empty($diffKeys)) {
                    $outer->whereRaw('1 = 0');
                } else {
                    $outer->whereIn('item_key', $diffKeys);
                }
            }
            // If $this->cycleView === 'catalog', show full catalog
        }

        // Filter Search
        if (!empty($this->search)) {
            $tokens = array_filter(explode(' ', trim($this->search)));
            foreach ($tokens as $token) {
                $outer->where(function ($sub) use ($token) {
                    $sub->where('product_name', 'like', "%{$token}%")
                        ->orWhere('sku', 'like', "%{$token}%")
                        ->orWhere('barcode', 'like', "%{$token}%")
                        ->orWhere('size', 'like', "%{$token}%")
                        ->orWhere('color', 'like', "%{$token}%");
                });
            }
        }

        // Filter Status (when in Full Count mode or Catalog mode)
        if ($this->mode === 'count' || $this->cycleView === 'catalog') {
            if ($this->statusFilter === 'zero_stock') {
                $outer->where('system_qty', 0);
            } elseif ($this->statusFilter === 'negative_stock') {
                $outer->where('system_qty', '<', 0);
            } elseif ($this->statusFilter === 'uncounted') {
                $countedKeys = array_keys(array_filter($this->countedQuantities, fn($q) => $q !== '' && $q !== null));
                if (!empty($countedKeys)) {
                    $outer->whereNotIn('item_key', $countedKeys);
                }
            } elseif ($this->statusFilter === 'diff') {
                $diffKeys = [];
                foreach ($this->countedQuantities as $k => $q) {
                    if ($q === '' || $q === null) continue;
                    $expected = $this->itemMetadata[$k]['system_qty'] ?? null;
                    if ($expected !== null && (int)$q !== (int)$expected) {
                        $diffKeys[] = $k;
                    }
                }
                $outer->whereIn('item_key', !empty($diffKeys) ? $diffKeys : ['__NONE__']);
            } elseif ($this->statusFilter === 'matched') {
                $matchedKeys = [];
                foreach ($this->countedQuantities as $k => $q) {
                    if ($q === '' || $q === null) continue;
                    $expected = $this->itemMetadata[$k]['system_qty'] ?? null;
                    if ($expected !== null && (int)$q === (int)$expected) {
                        $matchedKeys[] = $k;
                    }
                }
                $outer->whereIn('item_key', !empty($matchedKeys) ? $matchedKeys : ['__NONE__']);
            } elseif ($this->statusFilter === 'short') {
                $shortKeys = [];
                foreach ($this->countedQuantities as $k => $q) {
                    if ($q === '' || $q === null) continue;
                    $expected = $this->itemMetadata[$k]['system_qty'] ?? null;
                    if ($expected !== null && (int)$q < (int)$expected) {
                        $shortKeys[] = $k;
                    }
                }
                $outer->whereIn('item_key', !empty($shortKeys) ? $shortKeys : ['__NONE__']);
            } elseif ($this->statusFilter === 'excess') {
                $excessKeys = [];
                foreach ($this->countedQuantities as $k => $q) {
                    if ($q === '' || $q === null) continue;
                    $expected = $this->itemMetadata[$k]['system_qty'] ?? null;
                    if ($expected !== null && (int)$q > (int)$expected) {
                        $excessKeys[] = $k;
                    }
                }
                $outer->whereIn('item_key', !empty($excessKeys) ? $excessKeys : ['__NONE__']);
            }
        }

        // Order by recent scans if in session or diff view in cycle mode
        if ($this->mode === 'cycle' && in_array($this->cycleView, ['session', 'diff']) && !empty($this->scannedOrder)) {
            $cleanKeys = array_map(fn($k) => preg_replace('/[^a-zA-Z0-9_]/', '', $k), array_slice($this->scannedOrder, 0, 100));
            $cases = [];
            foreach ($cleanKeys as $idx => $k) {
                $cases[] = "WHEN '{$k}' THEN " . ($idx + 1);
            }
            $caseSql = "CASE item_key " . implode(' ', $cases) . " ELSE 99999 END ASC";
            $outer->orderByRaw($caseSql);
        } else {
            $outer->orderBy('product_name', 'asc')
                ->orderBy('sku', 'asc');
        }

        $paginated = $outer->paginate($this->perPage);

        // Cache metadata for all visible items on this page
        foreach ($paginated->items() as $item) {
            $this->itemMetadata[$item->item_key] = [
                'product_id' => $item->product_id,
                'variant_id' => $item->variant_id,
                'product_name' => $item->product_name,
                'sku' => $item->sku,
                'system_qty' => (int) $item->system_qty,
                'unit_cost' => (float) ($item->unit_cost ?: 0),
            ];
        }

        return $paginated;
    }

    public function getWarehousesProperty()
    {
        return Warehouse::where('is_active', true)->orderBy('name')->get();
    }

    public function getCategoriesProperty()
    {
        return Category::orderBy('name')->get(['id', 'name']);
    }

    public function getBrandsProperty()
    {
        return Product::whereNotNull('brand')
            ->where('brand', '!=', '')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand');
    }
}
