<?php

namespace App\Filament\Pages;

use App\Models\Category;
use App\Models\Inventory\Warehouse;
use App\Models\OfflineSale;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use App\Services\OfflineSaleService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class OfflineSales extends Page
{
    protected string $view = 'filament.pages.offline-sales';
    protected Width | string | null $maxWidth = 'full';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static string | \UnitEnum | null $navigationGroup = 'Sales';
    protected static ?string $navigationLabel = 'POS';
    protected static ?int $navigationSort = 10;
    protected static ?string $title = 'POS & Offline Sales';
    protected static ?string $slug = 'offline-sales';

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

        return $user->hasRole('Cashier', 'admin')
            || $user->hasRole('Cashier', 'web')
            || $user->hasRole('Store Manager', 'admin')
            || $user->hasRole('Store Manager', 'web')
            || $user->hasRole('Operations Manager', 'admin')
            || $user->hasRole('Operations Manager', 'web')
            || $user->hasRole('Sales Representative', 'admin')
            || $user->hasRole('Sales Representative', 'web')
            || $user->hasRole('Executive', 'admin')
            || $user->hasRole('Executive', 'web')
            || $user->hasRole('Accountant', 'admin')
            || $user->hasRole('Accountant', 'web')
            || $user->hasRole('Workspace Admin', 'admin')
            || $user->hasRole('Workspace Admin', 'web')
            || $user->can('page_OfflineSales')
            || in_array($user->role, ['cashier', 'store_manager', 'operations_manager', 'sales_rep', 'sales', 'executive', 'accountant', 'admin'], true);
    }

    // 1. Navigation View Tabs: 'new_sale' | 'history' | 'dashboard' | 'performance'
    public string $activeTab = 'new_sale';

    // 2. Warehouse & Terminal State
    public ?int $selectedWarehouseId = 2; // Default to Showroom POS (STORE-KTM-01)
    public array $warehousesList = [];

    // 3. Product Catalog & Search State
    public string $searchQuery = '';
    public ?int $selectedCategoryId = null;
    public string $currency = 'NPR';

    // 4. Active Sale Line Items (Cart)
    public array $cart = [];
    public float $discountAmount = 0.00;
    public ?int $discountPercent = null;
    public string $discountReason = 'In-Person / Courtesy Discount';
    public float $shippingAmount = 0.00;

    // 5. Held / Suspended Sales
    public array $heldSales = [];

    // 6. Cash Tendered & Change
    public float $cashReceived = 0.00;
    public string $unknownBarcode = '';

    // 7. Customer State
    public string $customerType = 'walkin'; // 'walkin' | 'existing'
    public ?int $selectedUserId = null;
    public string $customerSearchQuery = '';
    public string $customerName = 'Walk-in Customer';
    public string $customerEmail = '';
    public string $customerPhone = '';

    // Quick Customer Creation Inputs
    public string $newCustName = '';
    public string $newCustEmail = '';
    public string $newCustPhone = '';

    // 8. Payment & Sales Channel Metadata
    public string $paymentMethod = 'cash';
    public string $salesChannel = 'physical';
    public string $customerNotes = '';
    public string $internalNotes = '';
    public bool $isSubmitting = false;
    public bool $showMobileCartDrawer = false;

    // 9. Modals & Overlays
    // null | 'variant_select' | 'customer_modal' | 'discount_modal' | 'checkout_modal' | 'held_sales' | 'unknown_barcode' | 'sale_detail' | 'void_modal' | 'receipt_modal' | 'sale_success'
    public ?string $activeModal = null;

    // Variant Selection Modal State
    public ?array $selectedProductForVariant = null;
    public ?int $modalSelectedVariantId = null;
    public ?string $modalSelectedColor = null;
    public ?string $modalSelectedSize = null;
    public int $modalSelectedQty = 1;

    // Sales History Filters & Detail State
    public string $historySearch = '';
    public string $historyDateFilter = 'all_time';
    public string $historyChannelFilter = '';
    public string $historyPaymentFilter = '';
    public string $historyStatusFilter = '';
    public ?array $selectedSaleDetail = null;

    // Voiding Modal State
    public ?int $voidSaleId = null;
    public string $voidSaleNumber = '';
    public string $voidReason = 'Customer cancellation / return';
    public bool $voidRestockInventory = true;

    // Last Completed Sale & Receipt Data
    public ?array $lastCompletedSale = null;
    public ?array $receiptData = null;
    public ?string $whatsappUrl = null;

    public int $catalogLimit = 36;
    public array $categoriesList = [];

    public function mount(): void
    {
        $this->currency = Setting::get('default_currency', 'NPR');
        $this->customerType = 'walkin';
        $this->customerName = 'Walk-in Customer';

        // Preload active categories once to prevent repeated DB queries on re-renders
        $this->categoriesList = Category::where('is_active', true)
            ->withCount(['products' => fn($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();

        // Load active warehouses
        $this->warehousesList = Warehouse::where('is_active', true)
            ->orderBy('id')
            ->get(['id', 'name', 'code'])
            ->toArray();

        // Default to Showroom warehouse (STORE-KTM-01 or showroom_pos), fallback to first available active warehouse
        $curWh = Warehouse::where('code', 'STORE-KTM-01')->where('is_active', true)->first()
            ?? Warehouse::where('type', 'showroom_pos')->where('is_active', true)->first()
            ?? Warehouse::where('is_active', true)->first();
        $this->selectedWarehouseId = $curWh?->id;
    }

    public function updatedSearchQuery(): void
    {
        $this->catalogLimit = 36;
    }

    public function updatedSelectedCategoryId(): void
    {
        $this->catalogLimit = 36;
    }

    public function loadMoreProducts(): void
    {
        $this->catalogLimit += 24;
    }

    public function setWarehouse(int $whId): void
    {
        $wh = Warehouse::find($whId);
        if ($wh) {
            $this->selectedWarehouseId = $wh->id;

            // Refresh available stock for items in cart without modifying or capping user quantities
            foreach ($this->cart as $key => $item) {
                $stock = $this->resolveAvailableStock((int)$item['product_id'], $item['variant_id'] ? (int)$item['variant_id'] : null);
                $this->cart[$key]['stock'] = $stock;
            }
            $this->recalculateTotals();

            Notification::make()
                ->title('Warehouse Selected')
                ->body("Now selling from: {$wh->name} ({$wh->code})")
                ->info()
                ->duration(1500)
                ->send();
        }
    }

    public function resolveAvailableStock(int $productId, ?int $variantId = null): int
    {
        // Check location-specific warehouse stock level
        if ($this->selectedWarehouseId) {
            $stockLevel = \App\Models\Inventory\StockLevel::where('warehouse_id', $this->selectedWarehouseId)
                ->where('product_id', $productId)
                ->when($variantId, fn($q) => $q->where('variant_id', $variantId), fn($q) => $q->whereNull('variant_id'))
                ->first();

            if ($stockLevel) {
                return (int)($stockLevel->quantity_on_hand - $stockLevel->quantity_reserved);
            }
        }

        // Fallback to variant or base product total quantity (preserves signed negative quantity)
        if ($variantId) {
            return (int)(ProductVariant::where('id', $variantId)->value('stock_quantity') ?? 0);
        }
        return (int)(Product::where('id', $productId)->value('quantity') ?? 0);
    }

    public function canViewFinancialMargins(): bool
    {
        $user = auth('admin')->user() ?? auth('web')->user() ?? Auth::user();
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        return $user->hasRole('Store Manager', 'admin')
            || $user->hasRole('Store Manager', 'web')
            || $user->hasRole('Workspace Admin', 'admin')
            || $user->hasRole('Workspace Admin', 'web')
            || in_array($user->role, ['store_manager', 'admin', 'super_admin']);
    }

    // --- Computed Properties ---

    public function getCategoriesProperty(): array
    {
        if (!empty($this->categoriesList)) {
            return $this->categoriesList;
        }

        return Category::where('is_active', true)
            ->withCount(['products' => fn($q) => $q->where('is_active', true)])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->toArray();
    }

    public function getSearchResultsProperty(): array
    {
        $query = Product::with(['variants' => fn($q) => $q->where('is_active', true), 'categories'])
            ->where('is_active', true);

        if ($this->selectedCategoryId) {
            $catId = $this->selectedCategoryId;
            $query->whereHas('categories', fn($q) => $q->where('categories.id', $catId));
        }

        if (strlen(trim($this->searchQuery)) > 0) {
            $query->searchRetail(trim($this->searchQuery));
        }

        return $query->orderBy('name')->limit($this->catalogLimit)->get()->toArray();
    }

    public function getCustomersListProperty(): array
    {
        $q = User::where('role', 'customer');
        $s = trim($this->customerSearchQuery);
        if ($s !== '') {
            $q->where(function ($sub) use ($s) {
                $sub->where('name', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%");
            });
            return $q->orderBy('name')->limit(50)->get(['id', 'name', 'email', 'phone'])->toArray();
        }
        return $q->orderBy('name')->limit(30)->get(['id', 'name', 'email', 'phone'])->toArray();
    }

    public function getSalesHistoryProperty(): array
    {
        $q = OfflineSale::with(['items', 'customer', 'warehouse'])
            ->orderBy('sold_at', 'desc');

        if (strlen(trim($this->historySearch)) > 0) {
            $s = trim($this->historySearch);
            $q->where(function ($sub) use ($s) {
                $sub->where('sale_number', 'like', "%{$s}%")
                    ->orWhere('customer_name', 'like', "%{$s}%")
                    ->orWhere('customer_phone', 'like', "%{$s}%")
                    ->orWhereHas('items', function ($iq) use ($s) {
                        $iq->where('product_name', 'like', "%{$s}%")
                            ->orWhere('sku', 'like', "%{$s}%");
                    });
            });
        }

        if (!empty($this->historyChannelFilter)) {
            $q->where('sales_channel', $this->historyChannelFilter);
        }

        if (!empty($this->historyPaymentFilter)) {
            $q->where('payment_method', $this->historyPaymentFilter);
        }

        if (!empty($this->historyStatusFilter)) {
            $q->where('status', $this->historyStatusFilter);
        }

        if (!empty($this->historyDateFilter) && $this->historyDateFilter !== 'all_time') {
            match ($this->historyDateFilter) {
                'today' => $q->whereDate('sold_at', now()->toDateString()),
                'yesterday' => $q->whereDate('sold_at', now()->subDay()->toDateString()),
                'this_month' => $q->whereBetween('sold_at', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()]),
                'last_month' => $q->whereBetween('sold_at', [now()->subMonth()->startOfMonth()->toDateString(), now()->subMonth()->endOfMonth()->toDateString()]),
                'jan_2026' => $q->whereBetween('sold_at', ['2026-01-01 00:00:00', '2026-01-31 23:59:59']),
                'feb_2026' => $q->whereBetween('sold_at', ['2026-02-01 00:00:00', '2026-02-28 23:59:59']),
                'mar_2026' => $q->whereBetween('sold_at', ['2026-03-01 00:00:00', '2026-03-31 23:59:59']),
                'apr_2026' => $q->whereBetween('sold_at', ['2026-04-01 00:00:00', '2026-04-30 23:59:59']),
                'may_2026' => $q->whereBetween('sold_at', ['2026-05-01 00:00:00', '2026-05-31 23:59:59']),
                'jun_2026' => $q->whereBetween('sold_at', ['2026-06-01 00:00:00', '2026-06-30 23:59:59']),
                'jul_2026' => $q->whereBetween('sold_at', ['2026-07-01 00:00:00', '2026-07-31 23:59:59']),
                'aug_2026' => $q->whereBetween('sold_at', ['2026-08-01 00:00:00', '2026-08-31 23:59:59']),
                'sep_2026' => $q->whereBetween('sold_at', ['2026-09-01 00:00:00', '2026-09-30 23:59:59']),
                default => null,
            };
        }

        return $q->limit(100)->get()->toArray();
    }

    public function getDashboardDataProperty(): array
    {
        return app(OfflineSaleService::class)->getDashboardMetrics();
    }

    public function getProductPerformanceDataProperty(): array
    {
        return app(OfflineSaleService::class)->getProductPerformance();
    }

    public function getCashChangeProperty(): float
    {
        $total = $this->calculateTotals()['total'];
        return max(0.00, round($this->cashReceived - $total, 2));
    }

    // --- Tab Switching ---

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    // --- Modal Controls ---

    public function openModal(string $modal): void
    {
        $this->activeModal = $modal;
    }

    public function closeModal(): void
    {
        $this->activeModal = null;
        $this->selectedProductForVariant = null;
        $this->unknownBarcode = '';
    }

    // --- Product Selection & Multi-Variant Handling ---

    public function handleProductClick(int $productId): void
    {
        $product = Product::with(['variants' => fn($q) => $q->where('is_active', true)])->find($productId);
        if (!$product) return;

        $variants = $product->variants ?? collect();

        if ($variants && $variants->count() > 1) {
            $this->selectedProductForVariant = $product->toArray();

            $firstVariant = $variants->first();
            $this->modalSelectedVariantId = $firstVariant?->id;
            $this->modalSelectedColor = $firstVariant?->color;
            $this->modalSelectedSize = $firstVariant?->size;
            $this->modalSelectedQty = 1;
            $this->activeModal = 'variant_select';
        } else {
            $this->addToCart($productId, $variants && $variants->count() > 0 ? $variants->first()?->id : null);
        }
    }

    public function selectModalVariantColor(string $color): void
    {
        $this->modalSelectedColor = $color;
        $this->syncModalVariantSelection();
    }

    public function selectModalVariantSize(string $size): void
    {
        $this->modalSelectedSize = $size;
        $this->syncModalVariantSelection();
    }

    protected function syncModalVariantSelection(): void
    {
        if (!$this->selectedProductForVariant) return;
        $variants = $this->selectedProductForVariant['variants'] ?? [];

        foreach ($variants as $v) {
            $matchColor = empty($this->modalSelectedColor) || ($v['color'] === $this->modalSelectedColor);
            $matchSize = empty($this->modalSelectedSize) || ($v['size'] === $this->modalSelectedSize);
            if ($matchColor && $matchSize) {
                $this->modalSelectedVariantId = $v['id'];
                return;
            }
        }

        foreach ($variants as $v) {
            if ($v['color'] === $this->modalSelectedColor) {
                $this->modalSelectedVariantId = $v['id'];
                $this->modalSelectedSize = $v['size'];
                return;
            }
        }
    }

    public function addModalVariantToCart(): void
    {
        if (!$this->selectedProductForVariant) return;

        $productId = $this->selectedProductForVariant['id'];
        $variantId = $this->modalSelectedVariantId;
        $qty = max(1, $this->modalSelectedQty);

        $this->addToCart($productId, $variantId, $qty);
        $this->closeModal();
    }

    // --- Line Items & Cart Management ---

    public function addToCart(int $productId, ?int $variantId = null, int $quantity = 1): void
    {
        $product = Product::find($productId);
        if (!$product || !$product->is_active) {
            Notification::make()
                ->title('Product Unavailable')
                ->body('This product is inactive or archived and cannot be sold.')
                ->danger()
                ->send();
            return;
        }

        $variant = $variantId ? ProductVariant::find($variantId) : ($product->variants()->first() ?: null);
        if ($variant && !$variant->is_active) {
            Notification::make()
                ->title('Variant Unavailable')
                ->body('This variant is inactive and cannot be sold.')
                ->danger()
                ->send();
            return;
        }

        $cartKey = $productId . '-' . ($variant ? $variant->id : 'default');

        // Resolve current stock for informational display
        $stock = $this->resolveAvailableStock($productId, $variant?->id);

        $allowOverselling = app(\App\Services\Settings\SettingsService::class)->getBoolean('pos', 'allow_overselling', false);

        if (isset($this->cart[$cartKey])) {
            $currentQty = (int)$this->cart[$cartKey]['quantity'];
            $newQty = $currentQty + max(1, $quantity);

            if (!$allowOverselling && $stock > 0 && $newQty > $stock) {
                $newQty = $stock;
            }

            $this->cart[$cartKey]['quantity'] = $newQty;
            $this->cart[$cartKey]['total'] = round($this->cart[$cartKey]['unit_price'] * $newQty, 2);
            $this->cart[$cartKey]['stock'] = $stock;
        } else {
            $basePrice = $variant && (float)$variant->price > 0
                ? (float)$variant->price
                : (float)($product->price ?? 0);

            $websitePrice = round($basePrice, 2);
            $unitPrice = $websitePrice; // Default actual offline price equals reference price
            $actualQty = max(1, $quantity);

            if (!$allowOverselling && $stock > 0 && $actualQty > $stock) {
                $actualQty = $stock;
            }

            $img = $variant && $variant->image ? $variant->image : $product->featured_image;
            if (!$img && !empty($product->images)) {
                $img = is_array($product->images) ? ($product->images[0] ?? null) : null;
            }

            // Estimate landed cost for live display
            $sku = $variant ? $variant->sku : $product->sku;
            $costInfo = app(OfflineSaleService::class)->lookupLandedCost($sku, $product->id);

            $this->cart[$cartKey] = [
                'key' => $cartKey,
                'product_id' => $product->id,
                'variant_id' => $variant ? $variant->id : null,
                'name' => $product->name,
                'sku' => $sku,
                'color' => $variant ? $variant->color : null,
                'size' => $variant ? $variant->size : null,
                'image' => $img,
                'stock' => $stock,
                'website_price' => $websitePrice,
                'unit_price' => $unitPrice,
                'quantity' => $actualQty,
                'total' => round($unitPrice * $actualQty, 2),
                'unit_cost_npr' => $costInfo['unit_cost_npr'],
                'cost_type' => $costInfo['cost_type'],
            ];
        }

        $this->recalculateTotals();

        $variantNotice = $variant ? " ({$variant->color})" : '';
        Notification::make()
            ->title('Added to Cart')
            ->body($product->name . $variantNotice)
            ->success()
            ->duration(1200)
            ->send();
    }

    public function updateQuantity(string $cartKey, int $qty): void
    {
        if (isset($this->cart[$cartKey])) {
            $newQty = (int)$qty;
            if ($newQty <= 0) {
                unset($this->cart[$cartKey]);
            } else {
                $this->cart[$cartKey]['quantity'] = $newQty;
                $this->cart[$cartKey]['total'] = round($this->cart[$cartKey]['unit_price'] * $newQty, 2);
            }
            $this->recalculateTotals();
        }
    }

    public function incrementQuantity(string $cartKey): void
    {
        if (isset($this->cart[$cartKey])) {
            $current = (int)$this->cart[$cartKey]['quantity'];
            $this->updateQuantity($cartKey, $current + 1);
        }
    }

    public function decrementQuantity(string $cartKey): void
    {
        if (isset($this->cart[$cartKey])) {
            $current = (int)$this->cart[$cartKey]['quantity'];
            $this->updateQuantity($cartKey, $current - 1);
        }
    }

    public function removeItem(string $cartKey): void
    {
        if (isset($this->cart[$cartKey])) {
            unset($this->cart[$cartKey]);
            $this->recalculateTotals();
        }
    }

    public function updatePrice(string $cartKey, float $price): void
    {
        if (isset($this->cart[$cartKey])) {
            $newPrice = max(0.00, round($price, 2));
            $this->cart[$cartKey]['unit_price'] = $newPrice;
            $this->cart[$cartKey]['total'] = round($newPrice * (int)$this->cart[$cartKey]['quantity'], 2);
            $this->recalculateTotals();
        }
    }

    public function toggleMobileCartDrawer(): void
    {
        $this->showMobileCartDrawer = !$this->showMobileCartDrawer;
    }

    public function applyItemQuickDiscount(string $cartKey, int $percent): void
    {
        if (isset($this->cart[$cartKey])) {
            $web = (float)$this->cart[$cartKey]['website_price'];
            $discPrice = max(0.00, round($web * (1 - ($percent / 100)), 2));
            $this->cart[$cartKey]['unit_price'] = $discPrice;
            $this->cart[$cartKey]['total'] = round($discPrice * (int)$this->cart[$cartKey]['quantity'], 2);
            $this->recalculateTotals();

            Notification::make()
                ->title("Applied {$percent}% discount")
                ->success()
                ->duration(1000)
                ->send();
        }
    }

    public function resetItemPrice(string $cartKey): void
    {
        if (isset($this->cart[$cartKey])) {
            $web = (float)$this->cart[$cartKey]['website_price'];
            $this->cart[$cartKey]['unit_price'] = $web;
            $this->cart[$cartKey]['total'] = round($web * (int)$this->cart[$cartKey]['quantity'], 2);
            $this->recalculateTotals();
        }
    }

    // --- Barcode Scanner & Search Handler ---

    public function handleBarcodeScan(?string $code = null): void
    {
        $term = trim($code ?: $this->searchQuery);
        if (empty($term)) return;

        // 1. Variant SKU or Barcode match (check all, including inactive for clear warning)
        $variant = ProductVariant::with('product')
            ->where(function ($q) use ($term) {
                $q->where('sku', $term)
                    ->orWhere('barcode', $term);
            })
            ->first();

        if ($variant) {
            if (!$variant->is_active || ($variant->product && !$variant->product->is_active)) {
                Notification::make()
                    ->title('Item Inactive')
                    ->body("Scanned item (SKU: {$variant->sku}) is inactive or archived and cannot be sold.")
                    ->danger()
                    ->send();
                $this->searchQuery = '';
                return;
            }

            $this->addToCart($variant->product_id, $variant->id);
            $this->searchQuery = '';
            return;
        }

        // 2. Product Barcode or SKU exact match
        $product = Product::with(['variants'])
            ->where(function ($q) use ($term) {
                $q->where('barcode', $term)
                    ->orWhere('sku', $term);
            })
            ->first();

        if ($product) {
            if (!$product->is_active) {
                Notification::make()
                    ->title('Product Inactive')
                    ->body("Scanned product ({$product->name}) is inactive or archived and cannot be sold.")
                    ->danger()
                    ->send();
                $this->searchQuery = '';
                return;
            }

            $variantsCount = $product->variants()->where('is_active', true)->count();
            if ($variantsCount > 1) {
                $this->handleProductClick($product->id);
            } else {
                $singleVar = $product->variants()->where('is_active', true)->first();
                $this->addToCart($product->id, $singleVar?->id);
            }
            $this->searchQuery = '';
            return;
        }

        // 3. Product Name exact match
        $namedProduct = Product::where('name', $term)->first();
        if ($namedProduct) {
            if (!$namedProduct->is_active) {
                Notification::make()
                    ->title('Product Inactive')
                    ->body("Product ({$namedProduct->name}) is inactive or archived and cannot be sold.")
                    ->danger()
                    ->send();
                $this->searchQuery = '';
                return;
            }
            $this->handleProductClick($namedProduct->id);
            $this->searchQuery = '';
            return;
        }

        // 4. Single match from search results
        $results = $this->searchResults;
        if (count($results) === 1) {
            $this->handleProductClick($results[0]['id']);
            $this->searchQuery = '';
            return;
        }

        // 5. Unknown Barcode - Alert Cashier (never silently fail!)
        $this->unknownBarcode = $term;
        $this->activeModal = 'unknown_barcode';
    }

    public function handleBarcodeOrSkuScan(): void
    {
        $this->handleBarcodeScan();
    }

    public function searchManuallyWithBarcode(): void
    {
        $this->searchQuery = $this->unknownBarcode;
        $this->closeModal();
        $this->dispatch('focus-search');
    }

    // --- Clear & Currency ---

    public function confirmClearCart(): void
    {
        if (empty($this->cart)) return;
        $this->activeModal = 'clear_cart_confirm';
    }

    public function clearCart(): void
    {
        $this->cart = [];
        $this->discountAmount = 0.00;
        $this->discountPercent = null;
        $this->shippingAmount = 0.00;
        $this->customerNotes = '';
        $this->internalNotes = '';
        $this->cashReceived = 0.00;
        $this->showMobileCartDrawer = false;
        if ($this->activeModal === 'clear_cart_confirm') {
            $this->closeModal();
        }
    }

    public function setCurrency(string $curr = 'NPR'): void
    {
        $this->currency = 'NPR';
        foreach ($this->cart as $key => $item) {
            $product = Product::find($item['product_id']);
            $variant = $item['variant_id'] ? ProductVariant::find($item['variant_id']) : null;
            if ($product) {
                $refPrice = $variant && (float)$variant->price > 0
                    ? (float)$variant->price
                    : (float)($product->price ?? 0);

                $this->cart[$key]['website_price'] = $refPrice;
                $this->cart[$key]['unit_price'] = $refPrice;
                $this->cart[$key]['total'] = round($refPrice * (int)$item['quantity'], 2);
            }
        }
        $this->recalculateTotals();
    }

    // --- Discounts ---

    public function applyDiscountPercent(int $percent, string $reason = 'Courtesy Discount'): void
    {
        $this->discountPercent = $percent;
        $this->discountReason = $reason;
        $this->recalculateTotals();
        $this->closeModal();
    }

    public function applyFixedDiscount(float $amount, string $reason = 'Courtesy Discount'): void
    {
        $this->discountPercent = null;
        $this->discountAmount = max(0.00, round($amount, 2));
        $this->discountReason = $reason;
        $this->recalculateTotals();
        $this->closeModal();
    }

    public function clearDiscount(): void
    {
        $this->discountPercent = null;
        $this->discountAmount = 0.00;
        $this->recalculateTotals();
    }

    public function recalculateTotals(): void
    {
        $subtotal = 0.00;
        foreach ($this->cart as $item) {
            $subtotal += ((float)$item['unit_price'] * (int)$item['quantity']);
        }

        if ($this->discountPercent !== null && $this->discountPercent > 0) {
            $this->discountAmount = round(($subtotal * ($this->discountPercent / 100)), 2);
        }
    }

    public function calculateTotals(): array
    {
        $subtotal = 0.00;
        $totalUnits = 0;
        $estCost = 0.00;

        foreach ($this->cart as $item) {
            $qty = (int)$item['quantity'];
            $subtotal += ((float)$item['unit_price'] * $qty);
            $totalUnits += $qty;
            $estCost += ((float)($item['unit_cost_npr'] ?? 0) * $qty);
        }

        $discount = max(0.00, (float)$this->discountAmount);
        $shipping = max(0.00, (float)$this->shippingAmount);
        $discountedSubtotal = max(0.00, $subtotal - $discount);

        // Tax / VAT Integration (Nepal Statutory 13% VAT)
        $taxData = app(\App\Services\TaxCalculatorService::class)->calculate('NP', $discountedSubtotal);
        $taxRate = (float)($taxData['rate'] ?? 13.0);
        $taxAmount = (float)($taxData['amount'] ?? 0.00);
        $taxInclusive = (bool)($taxData['inclusive'] ?? true);

        $total = $taxInclusive
            ? ($discountedSubtotal + $shipping)
            : ($discountedSubtotal + $taxAmount + $shipping);

        $totalRev = round($discountedSubtotal, 2);
        $estProfit = round($totalRev - $estCost, 2);
        $estMargin = $totalRev > 0 ? round(($estProfit / $totalRev) * 100, 1) : 0.0;

        return [
            'subtotal' => $subtotal,
            'total_units' => $totalUnits,
            'discount' => $discount,
            'shipping_amount' => $shipping,
            'tax_amount' => $taxAmount,
            'tax_rate' => $taxRate,
            'tax_inclusive' => $taxInclusive,
            'total' => $total,
            'total_rev_npr' => $totalRev,
            'est_cost_npr' => $estCost,
            'est_profit_npr' => $estProfit,
            'est_margin' => $estMargin,
        ];
    }

    // --- Cash Tender & Change Helpers ---

    public function setExactCash(): void
    {
        $this->cashReceived = $this->calculateTotals()['total'];
    }

    public function addCashReceived(float $amount): void
    {
        $this->cashReceived = round($this->cashReceived + $amount, 2);
    }

    public function setCashReceived(float $amount): void
    {
        $this->cashReceived = round($amount, 2);
    }

    // --- Held / Suspended Sales Management ---

    public function holdCurrentSale(): void
    {
        if (empty($this->cart)) {
            Notification::make()->title('Cart is empty')->warning()->send();
            return;
        }

        $totals = $this->calculateTotals();
        $this->heldSales[] = [
            'id' => 'HOLD-' . strtoupper(Str::random(6)),
            'held_at' => now()->format('h:i A'),
            'held_date' => now()->format('d M Y'),
            'customer_name' => $this->customerName,
            'customer_phone' => $this->customerPhone,
            'customer_type' => $this->customerType,
            'selected_user_id' => $this->selectedUserId,
            'cart' => $this->cart,
            'discount_amount' => $this->discountAmount,
            'discount_percent' => $this->discountPercent,
            'discount_reason' => $this->discountReason,
            'customer_notes' => $this->customerNotes,
            'total' => $totals['total'],
            'items_count' => $totals['total_units'],
            'warehouse_id' => $this->selectedWarehouseId,
        ];

        $this->clearCart();
        $this->selectWalkInCustomer();

        Notification::make()
            ->title('Sale Put on Hold (F9)')
            ->body('Held sales count: ' . count($this->heldSales))
            ->success()
            ->duration(2000)
            ->send();
    }

    public function restoreHeldSale(int $index): void
    {
        if (!isset($this->heldSales[$index])) {
            return;
        }

        $held = $this->heldSales[$index];
        $this->cart = $held['cart'];
        $this->discountAmount = $held['discount_amount'] ?? 0.00;
        $this->discountPercent = $held['discount_percent'] ?? null;
        $this->discountReason = $held['discount_reason'] ?? 'In-Person / Courtesy Discount';
        $this->customerName = $held['customer_name'] ?? 'Walk-in Customer';
        $this->customerPhone = $held['customer_phone'] ?? '';
        $this->customerType = $held['customer_type'] ?? 'walkin';
        $this->selectedUserId = $held['selected_user_id'] ?? null;
        $this->customerNotes = $held['customer_notes'] ?? '';
        if (!empty($held['warehouse_id'])) {
            $this->selectedWarehouseId = $held['warehouse_id'];
        }

        array_splice($this->heldSales, $index, 1);
        $this->recalculateTotals();
        $this->closeModal();

        Notification::make()
            ->title('Held Sale Restored')
            ->success()
            ->send();
    }

    public function discardHeldSale(int $index): void
    {
        if (isset($this->heldSales[$index])) {
            array_splice($this->heldSales, $index, 1);
            Notification::make()->title('Held sale discarded')->info()->send();
        }
    }

    // --- Checkout Flow ---

    public function openCheckoutModal(): void
    {
        if (empty($this->cart)) {
            Notification::make()->title('Cart is empty')->warning()->send();
            return;
        }

        $totals = $this->calculateTotals();
        if ($this->cashReceived <= 0 || $this->cashReceived < $totals['total']) {
            $this->cashReceived = $totals['total'];
        }

        $this->activeModal = 'checkout_modal';
    }

    // --- Customer Selection ---

    public function selectWalkInCustomer(): void
    {
        $this->customerType = 'walkin';
        $this->selectedUserId = null;
        $this->customerName = 'Walk-in Customer';
        $this->customerEmail = '';
        $this->customerPhone = '';
        $this->closeModal();
    }

    public function selectCustomer(int $userId): void
    {
        $user = User::find($userId);
        if ($user) {
            $this->customerType = 'existing';
            $this->selectedUserId = $user->id;
            $this->customerName = $user->name;
            $this->customerEmail = $user->email ?? '';
            $this->customerPhone = \App\Services\Customer\CustomerService::normalizePhone($user->phone) ?? ($user->phone ?? '');
        }
        $this->closeModal();
    }

    public function createAndAttachCustomer(): void
    {
        if (empty(trim($this->newCustName))) {
            Notification::make()->title('Customer name is required')->danger()->send();
            return;
        }

        $rawPhone = trim($this->newCustPhone);
        $cleanPhone = \App\Services\Customer\CustomerService::normalizePhone($rawPhone);
        if (!empty($rawPhone) && !$cleanPhone) {
            Notification::make()
                ->title('Invalid Phone Number')
                ->body('Contact number must be a valid 10-digit mobile number (e.g. 98XXXXXXXX).')
                ->danger()
                ->send();
            return;
        }

        try {
            $customerService = app(\App\Services\Customer\CustomerService::class);
            $user = $customerService->resolveOrCreateCustomer(
                trim($this->newCustName),
                $cleanPhone,
                trim($this->newCustEmail) ?: null
            );

            $this->selectCustomer($user->id);
            $this->newCustName = '';
            $this->newCustEmail = '';
            $this->newCustPhone = '';

            Notification::make()->title('Customer Attached')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Failed to attach customer')->body($e->getMessage())->danger()->send();
        }
    }

    // --- Complete Offline Sale (Atomic & Double-Tap Safe) ---

    public function completeSale(): void
    {
        if ($this->isSubmitting) return;

        if (empty($this->cart)) {
            Notification::make()->title('Sale is empty')->warning()->send();
            return;
        }

        $totals = $this->calculateTotals();

        // Cash Tender Validation: cannot complete if cash received is less than total
        if ($this->paymentMethod === 'cash' && $this->cashReceived < $totals['total']) {
            Notification::make()
                ->title('Insufficient Cash Received')
                ->body('Cash received (Rs. ' . number_format($this->cashReceived, 2) . ') is less than Total (Rs. ' . number_format($totals['total'], 2) . ')')
                ->danger()
                ->send();
            return;
        }

        $this->isSubmitting = true;

        try {
            $service = app(OfflineSaleService::class);

            $saleData = [
                'user_id' => $this->selectedUserId,
                'customer_name' => $this->customerName,
                'customer_email' => $this->customerEmail,
                'customer_phone' => $this->customerPhone,
                'currency' => $this->currency,
                'discount_amount' => $totals['discount'],
                'discount_reason' => $totals['discount'] > 0 ? $this->discountReason : null,
                'shipping_amount' => $totals['shipping_amount'],
                'payment_method' => $this->paymentMethod,
                'sales_channel' => $this->salesChannel,
                'customer_notes' => $this->customerNotes,
                'internal_notes' => $this->internalNotes,
                'warehouse_id' => ($this->selectedWarehouseId && Warehouse::where('id', $this->selectedWarehouseId)->exists()) ? $this->selectedWarehouseId : (Warehouse::first()?->id ?? null),
                'cash_received' => $this->paymentMethod === 'cash' ? max($this->cashReceived, $totals['total']) : null,
                'change_given' => $this->paymentMethod === 'cash' ? max(0.00, round($this->cashReceived - $totals['total'], 2)) : 0.00,
            ];

            $itemsData = array_values($this->cart);

            $sale = $service->createSale($saleData, $itemsData, Auth::user());

            // Prepare WhatsApp receipt text
            $currSymbol = 'Rs. ';
            $cleanPhone = preg_replace('/[^0-9]/', '', (string)$sale->customer_phone);

            $msg = "✨ *LAIJAU — SALES RECEIPT* ✨\n\n";
            $msg .= "Dear " . $sale->customer_name . ",\n";
            $msg .= "Thank you for shopping at Laijau Showroom!\n\n";
            $msg .= "🔖 *Sale Reference:* #" . $sale->sale_number . "\n";
            $msg .= "📅 *Date:* " . $sale->sold_at->format('d M Y, h:i A') . "\n";
            $msg .= "💳 *Payment:* " . ucfirst(str_replace('_', ' ', $sale->payment_method)) . "\n";
            $msg .= "📍 *Channel:* " . ucfirst(str_replace('_', ' ', $sale->sales_channel)) . "\n";
            $msg .= "-------------------------------------------\n";

            foreach ($this->cart as $item) {
                $varStr = !empty($item['color']) || !empty($item['size']) ? " (" . trim(($item['color'] ?? '') . ' ' . ($item['size'] ?? '')) . ")" : "";
                $msg .= "• {$item['quantity']}x {$item['name']}{$varStr} — " . $currSymbol . number_format($item['unit_price'] * $item['quantity'], 2) . "\n";
            }

            $msg .= "-------------------------------------------\n";
            if ($totals['discount'] > 0) {
                $msg .= "Discount: -" . $currSymbol . number_format($totals['discount'], 2) . "\n";
            }
            $msg .= "💰 *TOTAL PAID:* *" . $currSymbol . number_format($sale->total_amount, 2) . "*\n";
            if ($sale->payment_method === 'cash') {
                $msg .= "💵 Cash Tendered: " . $currSymbol . number_format($sale->cash_received, 2) . "\n";
                $msg .= "🪙 Change Returned: " . $currSymbol . number_format($sale->change_given, 2) . "\n";
            }
            $msg .= "\nWarm regards,\n*Laijau Kathmandu*";

            $this->whatsappUrl = !empty($cleanPhone)
                ? "https://wa.me/{$cleanPhone}?text=" . urlencode($msg)
                : "https://api.whatsapp.com/send?text=" . urlencode($msg);

            $wh = Warehouse::find($this->selectedWarehouseId);
            $cashier = Auth::user();

            $this->receiptData = [
                'id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'date' => $sale->sold_at ? $sale->sold_at->format('d M Y') : now()->format('d M Y'),
                'time' => $sale->sold_at ? $sale->sold_at->format('h:i A') : now()->format('h:i A'),
                'cashier_name' => $cashier ? $cashier->name : 'Showroom Cashier',
                'warehouse_name' => $wh ? $wh->name : 'Laijau Showroom',
                'warehouse_code' => $wh ? $wh->code : 'STORE-KTM-01',
                'customer_name' => $sale->customer_name,
                'customer_phone' => $sale->customer_phone,
                'payment_method' => ucfirst(str_replace('_', ' ', $sale->payment_method)),
                'sales_channel' => ucfirst(str_replace('_', ' ', $sale->sales_channel)),
                'items' => array_values($this->cart),
                'subtotal' => $totals['subtotal'],
                'discount' => $totals['discount'],
                'total' => $sale->total_amount,
                'cash_received' => $sale->cash_received,
                'change_given' => $sale->change_given,
            ];

            $this->lastCompletedSale = [
                'id' => $sale->id,
                'sale_number' => $sale->sale_number,
                'customer_name' => $sale->customer_name,
                'total_amount' => $currSymbol . number_format($sale->total_amount, 2),
                'total_profit_npr' => 'Rs. ' . number_format($sale->total_profit_npr, 2),
                'margin_percentage' => number_format($sale->margin_percentage, 1) . '%',
                'items_count' => count($this->cart),
                'payment_method' => ucfirst(str_replace('_', ' ', $sale->payment_method)),
                'sales_channel' => ucfirst(str_replace('_', ' ', $sale->sales_channel)),
            ];

            $this->clearCart();
            $this->selectWalkInCustomer();
            $this->activeModal = 'sale_success';

            Notification::make()
                ->title("Sale #{$sale->sale_number} Recorded!")
                ->body("Inventory stock decremented & receipt generated.")
                ->success()
                ->send();
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('POS completeSale error: ' . $e->getMessage());
            Notification::make()
                ->title("Sale Failed")
                ->body($e->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->isSubmitting = false;
        }
    }

    // --- Sale Detail & Voiding ---

    public function openSaleDetail(int $saleId): void
    {
        $sale = OfflineSale::with(['items', 'customer', 'creator', 'voidLogs', 'warehouse'])->find($saleId);
        if ($sale) {
            $this->selectedSaleDetail = $sale->toArray();
            $this->activeModal = 'sale_detail';
        }
    }

    public function reprintReceipt(int $saleId): void
    {
        $sale = OfflineSale::with(['items', 'warehouse', 'creator'])->find($saleId);
        if (!$sale) return;

        $items = [];
        foreach ($sale->items as $it) {
            $items[] = [
                'name' => $it->product_name,
                'color' => $it->color,
                'size' => $it->size,
                'quantity' => $it->quantity,
                'unit_price' => (float)$it->unit_price,
                'total' => (float)$it->total_price,
            ];
        }

        $cleanPhone = preg_replace('/[^0-9]/', '', (string)$sale->customer_phone);
        $currSymbol = 'Rs. ';

        $msg = "✨ *LAIJAU — SALES RECEIPT* ✨\n\n";
        $msg .= "Dear " . $sale->customer_name . ",\n";
        $msg .= "Thank you for shopping at Laijau Showroom!\n\n";
        $msg .= "🔖 *Sale Reference:* #" . $sale->sale_number . "\n";
        $msg .= "📅 *Date:* " . ($sale->sold_at ? $sale->sold_at->format('d M Y, h:i A') : now()->format('d M Y, h:i A')) . "\n";
        $msg .= "💳 *Payment:* " . ucfirst(str_replace('_', ' ', $sale->payment_method)) . "\n";
        $msg .= "📍 *Channel:* " . ucfirst(str_replace('_', ' ', $sale->sales_channel)) . "\n";
        $msg .= "-------------------------------------------\n";

        foreach ($items as $item) {
            $varStr = !empty($item['color']) || !empty($item['size']) ? " (" . trim(($item['color'] ?? '') . ' ' . ($item['size'] ?? '')) . ")" : "";
            $msg .= "• {$item['quantity']}x {$item['name']}{$varStr} — " . $currSymbol . number_format($item['unit_price'] * $item['quantity'], 2) . "\n";
        }

        $msg .= "-------------------------------------------\n";
        if ($sale->discount_amount > 0) {
            $msg .= "Discount: -" . $currSymbol . number_format($sale->discount_amount, 2) . "\n";
        }
        $msg .= "💰 *TOTAL PAID:* *" . $currSymbol . number_format($sale->total_amount, 2) . "*\n";
        if ($sale->payment_method === 'cash' && $sale->cash_received) {
            $msg .= "💵 Cash Tendered: " . $currSymbol . number_format($sale->cash_received, 2) . "\n";
            $msg .= "🪙 Change Returned: " . $currSymbol . number_format($sale->change_given, 2) . "\n";
        }
        $msg .= "\nWarm regards,\n*Laijau Kathmandu*";

        $this->whatsappUrl = !empty($cleanPhone)
            ? "https://wa.me/{$cleanPhone}?text=" . urlencode($msg)
            : "https://api.whatsapp.com/send?text=" . urlencode($msg);

        $this->receiptData = [
            'id' => $sale->id,
            'sale_number' => $sale->sale_number,
            'date' => $sale->sold_at ? $sale->sold_at->format('d M Y') : now()->format('d M Y'),
            'time' => $sale->sold_at ? $sale->sold_at->format('h:i A') : now()->format('h:i A'),
            'cashier_name' => $sale->creator ? $sale->creator->name : ($sale->staff_name ?? 'Showroom Cashier'),
            'warehouse_name' => $sale->warehouse ? $sale->warehouse->name : 'Laijau Showroom',
            'warehouse_code' => $sale->warehouse ? $sale->warehouse->code : 'STORE-KTM-01',
            'customer_name' => $sale->customer_name,
            'customer_phone' => $sale->customer_phone,
            'payment_method' => ucfirst(str_replace('_', ' ', $sale->payment_method)),
            'sales_channel' => ucfirst(str_replace('_', ' ', $sale->sales_channel)),
            'items' => $items,
            'subtotal' => (float)$sale->subtotal,
            'discount' => (float)$sale->discount_amount,
            'total' => (float)$sale->total_amount,
            'cash_received' => $sale->cash_received ? (float)$sale->cash_received : null,
            'change_given' => $sale->change_given ? (float)$sale->change_given : null,
        ];

        $this->activeModal = 'receipt_modal';
    }

    public function openVoidModal(int $saleId): void
    {
        $sale = OfflineSale::find($saleId);
        if ($sale) {
            $this->voidSaleId = $sale->id;
            $this->voidSaleNumber = $sale->sale_number;
            $this->voidReason = 'Customer cancellation / return';
            $this->voidRestockInventory = true;
            $this->activeModal = 'void_modal';
        }
    }

    public function processVoidSale(): void
    {
        if (!$this->voidSaleId) return;

        $sale = OfflineSale::find($this->voidSaleId);
        if (!$sale) return;

        try {
            $service = app(OfflineSaleService::class);
            $service->voidSale($sale, $this->voidReason, Auth::user(), $this->voidRestockInventory);

            $this->closeModal();
            $this->voidSaleId = null;

            Notification::make()
                ->title("Sale #{$sale->sale_number} Voided")
                ->body($this->voidRestockInventory ? "Sale marked as voided & inventory restocked." : "Sale marked as voided.")
                ->success()
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->title("Void Failed")
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}
