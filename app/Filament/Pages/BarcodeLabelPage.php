<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductSkuService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\WithPagination;

class BarcodeLabelPage extends Page
{
    use WithPagination;

    protected string $view = 'filament.pages.barcode-label-page';
    protected Width | string | null $maxWidth = 'full';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-qr-code';
    protected static string | \UnitEnum | null $navigationGroup = 'Products';
    protected static ?string $navigationLabel = 'Barcodes & Labels';
    protected static ?int $navigationSort = 40;
    protected static ?string $title = 'Barcodes & Labels';
    protected static ?string $slug = 'barcodes-labels';

    /**
     * Search & Filter state
     */
    public string $searchQuery = '';
    public ?int $selectedCategoryId = null;
    public string $stockFilter = 'all'; // 'all', 'in_stock', 'low_stock', 'out_of_stock'
    public bool $missingBarcodeOnly = false;
    public int $perPage = 15;

    /**
     * Table selection & per-row quantities
     */
    public array $selectedRows = [];
    public array $rowCopies = [];

    /**
     * Label Configuration state
     */
    public string $labelFormat = 'thermal'; // 'thermal' | 'sheet'
    public string $thermalSize = '50x25'; // '50x25', '50x30', 'custom'
    public int $thermalWidth = 50;
    public int $thermalHeight = 25;

    public string $sheetLayout = '3x8'; // '3x8' (24-up), '4x10' (40-up), 'custom'
    public int $sheetCols = 3;
    public int $sheetRows = 8;

    /**
     * Label Content toggles
     */
    public bool $includeProductName = true;
    public bool $includeVariant = true;
    public bool $includeSku = true;
    public bool $includeBarcode = true;
    public bool $includePrice = true;
    public bool $includeBrand = false;
    public bool $includeInternalRef = false;
    public string $barcodeType = 'EAN-13';
    public int $defaultCopies = 1;

    /**
     * UI Modal / Slide-over state
     */
    public bool $showConfigModal = false;
    public bool $showQueueDrawer = false;
    public ?string $previewItemKey = null;

    /**
     * Print Queue items:
     * [
     *   'item_key' => string ('v_1' or 'p_1'),
     *   'variant_id' => int|null,
     *   'product_id' => int,
     *   'product_name' => string,
     *   'brand' => string|null,
     *   'variant_label' => string,
     *   'sku' => string,
     *   'barcode' => string,
     *   'price' => float,
     *   'copies' => int,
     *   'status' => 'ready'|'missing_barcode',
     *   'svg' => string,
     *   'internal_reference' => string|null,
     * ]
     */
    public array $printQueue = [];

    public function mount(?int $product_id = null, ?int $variant_id = null, ?string $product_ids = null): void
    {
        // 1. Check for single product
        $productId = $product_id ?? request()->query('product_id');
        if ($productId) {
            $this->addProductToQueue((int) $productId);
        }

        // 2. Check for single variant
        $variantId = $variant_id ?? request()->query('variant_id');
        if ($variantId) {
            $this->addVariantToQueue((int) $variantId);
        }

        // 3. Check for multiple products (e.g. from bulk actions in Products table)
        $productIds = $product_ids ?? request()->query('product_ids');
        if ($productIds) {
            $ids = array_filter(array_map('intval', explode(',', $productIds)));
            $count = 0;
            foreach ($ids as $id) {
                $this->addProductToQueue($id, silent: true);
                $count++;
            }

            if ($count > 0) {
                Notification::make()
                    ->title('Products Added to Queue')
                    ->body("Loaded {$count} products and their variants into the print Queue.")
                    ->success()
                    ->send();
            }
        }
    }

    public function updatingSearchQuery(): void
    {
        $this->resetPage();
    }

    public function updatingSelectedCategoryId(): void
    {
        $this->resetPage();
    }

    public function updatingStockFilter(): void
    {
        $this->resetPage();
    }

    public function updatingMissingBarcodeOnly(): void
    {
        $this->resetPage();
    }

    /**
     * Get paginated products query matching all filters and search terms.
     */
    public function getProductsProperty(): LengthAwarePaginator
    {
        $term = trim($this->searchQuery);
        $skuService = app(ProductSkuService::class);

        $query = Product::query()
            ->with([
                'variants' => fn($q) => $q->where('is_active', true),
                'categories',
                'stockLevels',
            ])
            ->where('is_active', true);

        if ($this->selectedCategoryId) {
            $query->whereHas('categories', fn($q) => $q->where('categories.id', $this->selectedCategoryId));
        }

        if ($this->missingBarcodeOnly) {
            $query->where(function ($q) {
                $q->whereNull('barcode')
                    ->orWhere('barcode', '')
                    ->orWhereHas('variants', fn($vq) => $vq->whereNull('barcode')->orWhere('barcode', ''));
            });
        }

        if (!empty($term)) {
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('sku', 'like', "%{$term}%")
                    ->orWhere('barcode', 'like', "%{$term}%")
                    ->orWhere('brand', 'like', "%{$term}%")
                    ->orWhere('model', 'like', "%{$term}%")
                    ->orWhereHas('variants', function ($vq) use ($term) {
                        $vq->where('sku', 'like', "%{$term}%")
                            ->orWhere('barcode', 'like', "%{$term}%")
                            ->orWhere('color', 'like', "%{$term}%")
                            ->orWhere('size', 'like', "%{$term}%");
                    });
            });
        }

        return $query->latest('updated_at')->paginate($this->perPage);
    }

    /**
     * Categories list for filter toolbar.
     */
    public function getCategoriesProperty()
    {
        return Category::orderBy('name')->pluck('name', 'id')->toArray();
    }

    /**
     * Toggle selection of all items visible on the current page.
     */
    public function toggleSelectAllOnPage(): void
    {
        $pageItemKeys = [];
        foreach ($this->products as $product) {
            if ($product->variants->isNotEmpty()) {
                foreach ($product->variants as $variant) {
                    $pageItemKeys[] = 'v_' . $variant->id;
                }
            } else {
                $pageItemKeys[] = 'p_' . $product->id;
            }
        }

        $allSelected = count(array_intersect($pageItemKeys, $this->selectedRows)) === count($pageItemKeys);

        if ($allSelected) {
            $this->selectedRows = array_diff($this->selectedRows, $pageItemKeys);
        } else {
            $this->selectedRows = array_unique(array_merge($this->selectedRows, $pageItemKeys));
        }
    }

    public function clearSelection(): void
    {
        $this->selectedRows = [];
    }

    public function incrementRowCopies(string $key): void
    {
        $current = $this->rowCopies[$key] ?? $this->defaultCopies;
        $this->rowCopies[$key] = $current + 1;
    }

    public function decrementRowCopies(string $key): void
    {
        $current = $this->rowCopies[$key] ?? $this->defaultCopies;
        $this->rowCopies[$key] = max(1, $current - 1);
    }

    public function setRowCopies(string $key, $qty): void
    {
        $this->rowCopies[$key] = max(1, (int) $qty);
    }

    /**
     * Add a single item (variant or simple product) to the print Queue.
     */
    public function addItemToQueue(string $type, int $id, ?int $copies = null): void
    {
        $copies = $copies ?? ($this->rowCopies[$type . '_' . $id] ?? $this->defaultCopies);

        if ($type === 'v') {
            $this->addVariantToQueue($id, $copies);
        } elseif ($type === 'p') {
            $this->addSimpleProductToQueue($id, $copies);
        }
    }

    /**
     * Add all currently selected rows to the print Queue.
     */
    public function addSelectedToQueue(): void
    {
        if (empty($this->selectedRows)) {
            Notification::make()
                ->title('No Items Selected')
                ->warning()
                ->body('Please select one or more items from the table.')
                ->send();
            return;
        }

        $count = 0;
        foreach ($this->selectedRows as $key) {
            $parts = explode('_', $key, 2);
            if (count($parts) !== 2) {
                continue;
            }
            [$type, $id] = $parts;
            $copies = $this->rowCopies[$key] ?? $this->defaultCopies;

            if ($type === 'v') {
                $this->addVariantToQueue((int) $id, $copies, silent: true);
                $count++;
            } elseif ($type === 'p') {
                $this->addSimpleProductToQueue((int) $id, $copies, silent: true);
                $count++;
            }
        }

        $this->selectedRows = [];

        Notification::make()
            ->title('Items Added to Queue')
            ->success()
            ->body("Added {$count} items to the print Queue.")
            ->send();
    }

    /**
     * Add a simple product (without variants) to the print Queue.
     */
    public function addSimpleProductToQueue(int $productId, int $copies = 1, bool $silent = false): void
    {
        $product = Product::find($productId);
        if (!$product) {
            return;
        }

        $skuService = app(ProductSkuService::class);
        $barcode = $product->barcode ?: '';
        $isValidBarcode = !empty($barcode) && $skuService->validateEan13($barcode);
        $svg = $isValidBarcode ? $skuService->generateBarcodeSvg($barcode, 42, 2) : '';

        $this->appendQueueItem(
            itemKey: 'p_' . $product->id,
            variantId: null,
            productId: $product->id,
            productName: $product->name,
            brand: $product->brand ?: 'LAIJAU',
            variantLabel: 'Standard',
            sku: $product->sku ?: 'LJ-PRD-00000',
            barcode: $barcode,
            price: (float) ($product->price ?: 0),
            copies: max(1, $copies),
            status: $isValidBarcode ? 'ready' : 'missing_barcode',
            svg: $svg,
            internalReference: $product->internal_reference
        );

        if (!$silent) {
            Notification::make()
                ->title('Product Added')
                ->body("Added '{$product->name}' ({$copies} copies) to print Queue.")
                ->success()
                ->send();
        }
    }

    /**
     * Add a variant to the print Queue.
     */
    public function addVariantToQueue(int $variantId, int $copies = 1, bool $silent = false): void
    {
        $variant = ProductVariant::with('product')->find($variantId);
        if (!$variant || !$variant->product) {
            return;
        }

        $product = $variant->product;
        $skuService = app(ProductSkuService::class);
        $barcode = $variant->barcode ?: '';
        $isValidBarcode = !empty($barcode) && $skuService->validateEan13($barcode);
        $svg = $isValidBarcode ? $skuService->generateBarcodeSvg($barcode, 42, 2) : '';

        $variantLabel = trim(($variant->color ?? '') . ($variant->color && $variant->size ? ' / ' : '') . ($variant->size ?? ''));
        if (empty($variantLabel)) {
            $variantLabel = 'Standard';
        }

        $this->appendQueueItem(
            itemKey: 'v_' . $variant->id,
            variantId: $variant->id,
            productId: $product->id,
            productName: $product->name,
            brand: $product->brand ?: 'LAIJAU',
            variantLabel: $variantLabel,
            sku: $variant->sku ?: ($product->sku ?: 'LJ-VAR-00000'),
            barcode: $barcode,
            price: (float) ($variant->price ?: $product->price ?: 0),
            copies: max(1, $copies),
            status: $isValidBarcode ? 'ready' : 'missing_barcode',
            svg: $svg,
            internalReference: $product->internal_reference
        );

        if (!$silent) {
            Notification::make()
                ->title('Variant Added')
                ->body("Added {$product->name} ({$variantLabel}) to print Queue.")
                ->success()
                ->send();
        }
    }

    /**
     * Add all variants of a product (or the product itself) to the print Queue.
     * Preserves backwards compatibility for existing tests and action triggers.
     */
    public function addProductToQueue(int $productId, bool $silent = false): void
    {
        $product = Product::with(['variants' => fn($q) => $q->where('is_active', true)])->find($productId);
        if (!$product) {
            return;
        }

        if ($product->variants->isNotEmpty()) {
            foreach ($product->variants as $variant) {
                $copies = $this->rowCopies['v_' . $variant->id] ?? $this->defaultCopies;
                $this->addVariantToQueue($variant->id, $copies, silent: true);
            }

            if (!$silent) {
                Notification::make()
                    ->title('Product Variants Added')
                    ->body("Added {$product->variants->count()} variants of '{$product->name}' to print Queue.")
                    ->success()
                    ->send();
            }
        } else {
            $copies = $this->rowCopies['p_' . $product->id] ?? $this->defaultCopies;
            $this->addSimpleProductToQueue($product->id, $copies, silent: $silent);
        }
    }

    /**
     * Internal Queue append / update helper.
     */
    protected function appendQueueItem(
        string $itemKey,
        ?int $variantId,
        int $productId,
        string $productName,
        ?string $brand,
        string $variantLabel,
        string $sku,
        string $barcode,
        float $price,
        int $copies,
        string $status,
        string $svg,
        ?string $internalReference = null
    ): void {
        foreach ($this->printQueue as $key => $item) {
            if ($item['item_key'] === $itemKey) {
                $this->printQueue[$key]['copies'] += $copies;
                return;
            }
        }

        $this->printQueue[] = [
            'item_key' => $itemKey,
            'variant_id' => $variantId,
            'product_id' => $productId,
            'product_name' => $productName,
            'brand' => $brand,
            'variant_label' => $variantLabel,
            'sku' => $sku,
            'barcode' => $barcode,
            'price' => $price,
            'copies' => max(1, $copies),
            'status' => $status,
            'svg' => $svg,
            'internal_reference' => $internalReference,
        ];
    }

    /**
     * Update copies count for an item in the print Queue.
     */
    public function updateCopies(int $index, $copies): void
    {
        if (isset($this->printQueue[$index])) {
            $this->printQueue[$index]['copies'] = max(1, (int) $copies);
        }
    }

    /**
     * Remove an item from the print Queue.
     */
    public function removeQueueItem(int $index): void
    {
        if (isset($this->printQueue[$index])) {
            unset($this->printQueue[$index]);
            $this->printQueue = array_values($this->printQueue);
        }
    }

    /**
     * Clear the entire print Queue.
     */
    public function clearQueue(): void
    {
        $this->printQueue = [];
        Notification::make()
            ->title('Queue Cleared')
            ->info()
            ->send();
    }

    /**
     * Safe 1-click generation of missing EAN-13 barcode.
     * Existing barcodes are NEVER overwritten.
     */
    public function generateBarcodeForMissingItem(string $type, int $id): void
    {
        $skuService = app(ProductSkuService::class);

        if ($type === 'v') {
            $variant = ProductVariant::find($id);
            if (!$variant) {
                return;
            }

            if (empty($variant->barcode) || !$skuService->validateEan13($variant->barcode)) {
                $variant->barcode = $skuService->generateBarcodeForVariant($variant);
                $variant->save();

                // Update in Queue if present
                foreach ($this->printQueue as $k => $item) {
                    if ($item['item_key'] === 'v_' . $id) {
                        $this->printQueue[$k]['barcode'] = $variant->barcode;
                        $this->printQueue[$k]['status'] = 'ready';
                        $this->printQueue[$k]['svg'] = $skuService->generateBarcodeSvg($variant->barcode, 42, 2);
                    }
                }

                Notification::make()
                    ->title('EAN-13 Barcode Generated')
                    ->body("Allocated {$variant->barcode} for variant.")
                    ->success()
                    ->send();
            }
        } elseif ($type === 'p') {
            $product = Product::find($id);
            if (!$product) {
                return;
            }

            if (empty($product->barcode) || !$skuService->validateEan13($product->barcode)) {
                $product->barcode = $skuService->generateBarcodeForProduct($product);
                $product->save();

                // Update in Queue if present
                foreach ($this->printQueue as $k => $item) {
                    if ($item['item_key'] === 'p_' . $id) {
                        $this->printQueue[$k]['barcode'] = $product->barcode;
                        $this->printQueue[$k]['status'] = 'ready';
                        $this->printQueue[$k]['svg'] = $skuService->generateBarcodeSvg($product->barcode, 42, 2);
                    }
                }

                Notification::make()
                    ->title('EAN-13 Barcode Generated')
                    ->body("Allocated {$product->barcode} for product.")
                    ->success()
                    ->send();
            }
        }
    }

    /**
     * Bulk generate missing EAN-13 barcodes for selected table items.
     * Safely ignores any item that already possesses a valid barcode.
     */
    public function bulkGenerateMissingBarcodesForSelection(): void
    {
        if (empty($this->selectedRows)) {
            Notification::make()
                ->title('No Items Selected')
                ->warning()
                ->body('Select items with missing barcodes first.')
                ->send();
            return;
        }

        $skuService = app(ProductSkuService::class);
        $generatedCount = 0;

        foreach ($this->selectedRows as $key) {
            [$type, $id] = explode('_', $key, 2);
            $id = (int) $id;

            if ($type === 'v') {
                $variant = ProductVariant::find($id);
                if ($variant && (empty($variant->barcode) || !$skuService->validateEan13($variant->barcode))) {
                    $variant->barcode = $skuService->generateBarcodeForVariant($variant);
                    $variant->save();
                    $generatedCount++;

                    // Sync in Queue
                    foreach ($this->printQueue as $k => $item) {
                        if ($item['item_key'] === 'v_' . $id) {
                            $this->printQueue[$k]['barcode'] = $variant->barcode;
                            $this->printQueue[$k]['status'] = 'ready';
                            $this->printQueue[$k]['svg'] = $skuService->generateBarcodeSvg($variant->barcode, 42, 2);
                        }
                    }
                }
            } elseif ($type === 'p') {
                $product = Product::find($id);
                if ($product && (empty($product->barcode) || !$skuService->validateEan13($product->barcode))) {
                    $product->barcode = $skuService->generateBarcodeForProduct($product);
                    $product->save();
                    $generatedCount++;

                    // Sync in Queue
                    foreach ($this->printQueue as $k => $item) {
                        if ($item['item_key'] === 'p_' . $id) {
                            $this->printQueue[$k]['barcode'] = $product->barcode;
                            $this->printQueue[$k]['status'] = 'ready';
                            $this->printQueue[$k]['svg'] = $skuService->generateBarcodeSvg($product->barcode, 42, 2);
                        }
                    }
                }
            }
        }

        Notification::make()
            ->title('Missing Barcodes Generated')
            ->body("Generated {$generatedCount} new EAN-13 barcodes. Existing barcodes were preserved.")
            ->success()
            ->send();
    }

    /**
     * Backward-compatible alias for existing tests or callers.
     * Safely updates SVG or generates if barcode was missing.
     */
    public function regenerateBarcode(int $index): void
    {
        if (!isset($this->printQueue[$index])) {
            return;
        }

        $item = $this->printQueue[$index];
        $skuService = app(ProductSkuService::class);

        if (!empty($item['variant_id'])) {
            $this->generateBarcodeForMissingItem('v', $item['variant_id']);
        } elseif (!empty($item['product_id'])) {
            $this->generateBarcodeForMissingItem('p', $item['product_id']);
        }

        // Refresh SVG for Queue item
        $barcode = $this->printQueue[$index]['barcode'] ?? '';
        if (!empty($barcode) && $skuService->validateEan13($barcode)) {
            $this->printQueue[$index]['svg'] = $skuService->generateBarcodeSvg($barcode, 42, 2);
            $this->printQueue[$index]['status'] = 'ready';
        }
    }

    /**
     * Total labels count across all items in print Queue.
     */
    public function getTotalLabelsCountProperty(): int
    {
        return (int) array_sum(array_column($this->printQueue, 'copies'));
    }

    /**
     * Preview sample item for the dimensionally representative live preview.
     */
    public function getLivePreviewItemProperty(): array
    {
        $skuService = app(ProductSkuService::class);

        // 1. If Queue has items, use the first item in Queue
        if (!empty($this->printQueue)) {
            $first = $this->printQueue[0];
            return [
                'product_name' => $first['product_name'],
                'brand' => $first['brand'] ?: 'LAIJAU',
                'variant_label' => $first['variant_label'],
                'sku' => $first['sku'],
                'barcode' => $first['barcode'] ?: '2001234567891',
                'price' => $first['price'],
                'internal_reference' => $first['internal_reference'],
                'svg' => !empty($first['svg']) ? $first['svg'] : $skuService->generateBarcodeSvg('2001234567891', 42, 2),
            ];
        }

        // 2. Fallback representative retail sample
        $sampleBarcode = '2001234567891';
        return [
            'product_name' => 'Premium Leather Shoes',
            'brand' => 'LAIJAU',
            'variant_label' => 'Black / 42',
            'sku' => 'LJ-SHO-00001',
            'barcode' => $sampleBarcode,
            'price' => 3499.00,
            'internal_reference' => 'BIN-A-12',
            'svg' => $skuService->generateBarcodeSvg($sampleBarcode, 42, 2),
        ];
    }
}
