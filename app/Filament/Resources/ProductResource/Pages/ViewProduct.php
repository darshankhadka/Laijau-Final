<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Inventory\StockLevel;
use App\Models\Inventory\StockMovement;
use App\Models\Inventory\Warehouse;
use App\Models\OfflineSaleItem;
use App\Models\OrderItem;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Services\Inventory\InventoryService;
use App\Services\ProductService;
use App\Services\ProductSkuService;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Enums\Width;

class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;
    protected string $view = 'filament.resources.products.pages.view-product';
    protected Width | string | null $maxWidth = 'full';

    public string $activeTab = 'overview';

    public function getTitle(): string
    {
        $brand = $this->getRecord()->brand ? " • {$this->getRecord()->brand}" : '';
        return $this->getRecord()->name . $brand;
    }

    public function getSubheading(): ?string
    {
        $sku = $this->getRecord()->sku ?? 'No SKU';
        $barcode = $this->getRecord()->barcode ?? 'No Barcode';
        $category = $this->getRecord()->categories->first()?->name ?? 'Uncategorized';
        return "Category: {$category} | Master SKU: {$sku} | Barcode: {$barcode}";
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
    }

    public function getBarcodeSvgProperty(): string
    {
        $barcode = $this->getRecord()->barcode;
        if (empty($barcode)) {
            return '';
        }
        return app(ProductSkuService::class)->generateBarcodeSvg($barcode, 45, 2);
    }

    public function getWarehouseStockProperty(): array
    {
        $product = $this->getRecord();
        $warehouses = Warehouse::where('is_active', true)->get();
        $stockData = [];

        foreach ($warehouses as $wh) {
            $levels = StockLevel::where('warehouse_id', $wh->id)
                ->where('product_id', $product->id)
                ->get();

            $onHand = (int) $levels->sum('quantity_on_hand');
            $reserved = (int) $levels->sum('quantity_reserved');
            $available = max(0, $onHand - $reserved);

            $stockData[] = [
                'warehouse_id' => $wh->id,
                'name' => $wh->name,
                'code' => $wh->code,
                'is_default' => (bool) $wh->is_default,
                'on_hand' => $onHand,
                'reserved' => $reserved,
                'available' => $available,
            ];
        }

        return $stockData;
    }

    public function getRecentMovementsProperty(): array
    {
        return StockMovement::with(['warehouse', 'variant', 'user'])
            ->where('product_id', $this->getRecord()->id)
            ->latest('id')
            ->take(20)
            ->get()
            ->toArray();
    }

    public function getSalesAnalyticsProperty(): array
    {
        $productId = $this->getRecord()->id;

        // 1. Online storefront sales
        $onlinNpnits = (int) OrderItem::where('product_id', $productId)->sum('quantity');
        $onlineRevenue = (float) (OrderItem::where('product_id', $productId)->selectRaw('SUM(quantity * unit_price) as rev')->value('rev') ?? 0.0);

        // 2. Offline showroom / POS sales
        $posUnits = 0;
        $posRevenue = 0.0;
        if (class_exists(OfflineSaleItem::class)) {
            try {
                $posUnits = (int) OfflineSaleItem::where('product_id', $productId)->sum('quantity');
                $posRevenue = (float) OfflineSaleItem::where('product_id', $productId)->sum('total_price');
            } catch (\Throwable $e) {
            }
        }

        $totalUnits = $onlinNpnits + $posUnits;
        $totalRevenue = $onlineRevenue + $posRevenue;
        $avgPrice = $totalUnits > 0 ? round($totalRevenue / $totalUnits, 2) : (float) ($this->getRecord()->price ?? 0);

        $lastOrderItem = OrderItem::where('product_id', $productId)->latest('id')->first();
        $lastSoldAt = $lastOrderItem ? $lastOrderItem->created_at?->diffForHumans() : 'No sales yet';

        return [
            'total_units' => $totalUnits,
            'total_revenue' => $totalRevenue,
            'avg_selling_price' => $avgPrice,
            'online_units' => $onlinNpnits,
            'online_revenue' => $onlineRevenue,
            'pos_units' => $posUnits,
            'pos_revenue' => $posRevenue,
            'last_sold' => $lastSoldAt,
        ];
    }

    protected function getHeaderActions(): array
    {
        $product = $this->getRecord();

        return [
            // 1. EDIT PRODUCT
            Actions\EditAction::make()
                ->label('Edit Product')
                ->icon('heroicon-o-pencil-square'),

            // 2. QUICK ADD STOCK
            Actions\Action::make('add_stock')
                ->label('Quick Add Stock')
                ->icon('heroicon-o-plus-circle')
                ->color('success')
                ->form([
                    Select::make('warehouse_id')
                        ->label('Target Warehouse *')
                        ->options(Warehouse::where('is_active', true)->pluck('name', 'id'))
                        ->default(fn() => Warehouse::where('is_default', true)->first()?->id ?: Warehouse::first()?->id)
                        ->required(),

                    Select::make('variant_id')
                        ->label('Variant (Optional)')
                        ->options(fn() => $product->variants->pluck('size', 'id')->map(fn($sz, $vid) => ($product->variants->firstWhere('id', $vid)->color ?? '') . ' / ' . $sz))
                        ->visible(fn() => $product->variants->isNotEmpty()),

                    TextInput::make('quantity')
                        ->label('Quantity to Add *')
                        ->numeric()
                        ->default(5)
                        ->minValue(1)
                        ->required(),

                    TextInput::make('notes')
                        ->label('Receiving Reference / Reason')
                        ->default('Stock Inward from Product Overview Page'),
                ])
                ->action(function (array $data) use ($product) {
                    $inventoryService = app(InventoryService::class);
                    $inventoryService->recordStockMovement([
                        'warehouse_id' => (int) $data['warehouse_id'],
                        'product_id' => $product->id,
                        'variant_id' => !empty($data['variant_id']) ? (int) $data['variant_id'] : null,
                        'quantity' => (int) $data['quantity'],
                        'movement_type' => 'purchase_receive',
                        'notes' => $data['notes'],
                    ], auth()->user());

                    Notification::make()
                        ->title('Stock Received')
                        ->body("+{$data['quantity']} units added to inventory atomically.")
                        ->success()
                        ->send();
                }),

            // 3. PRINT BARCODE LABEL
            Actions\Action::make('print_labels')
                ->label('Print Barcode')
                ->icon('heroicon-o-qr-code')
                ->color('info')
                ->url(fn() => url('/intadmin/barcodes-labels?product_id=' . $product->id)),

            // 4. DUPLICATE PRODUCT
            Actions\Action::make('duplicate')
                ->label('Duplicate')
                ->icon('heroicon-o-document-duplicate')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Duplicate Product')
                ->modalDescription('Creates a safe draft clone of this piece with fresh SKUs and barcodes.')
                ->action(function () use ($product) {
                    $newProduct = app(ProductService::class)->duplicate($product);

                    Notification::make()
                        ->title('Product Duplicated')
                        ->body("{$newProduct->name} created as draft.")
                        ->success()
                        ->send();

                    return redirect()->to(ProductResource::getUrl('view', ['record' => $newProduct->id]));
                }),

            // 5. ARCHIVE / RESTORE
            Actions\Action::make('toggle_archive')
                ->label(fn() => $product->is_active ? 'Archive' : 'Restore')
                ->icon(fn() => $product->is_active ? 'heroicon-o-archive-box-arrow-down' : 'heroicon-o-arrow-path')
                ->color('warning')
                ->action(function () use ($product) {
                    if ($product->is_active) {
                        app(ProductService::class)->archive($product);
                        Notification::make()->title('Product Archived')->info()->send();
                    } else {
                        app(ProductService::class)->unarchive($product);
                        Notification::make()->title('Product Restored')->success()->send();
                    }
                }),

            // 6. PUBLISH TO STOREFRONT
            Actions\Action::make('publish_storefront')
                ->label('Publish to Storefront')
                ->icon('heroicon-o-globe-alt')
                ->color('success')
                ->visible(fn() => !$product->is_published && $product->is_active)
                ->requiresConfirmation()
                ->modalHeading('Publish Product to Storefront')
                ->modalDescription('Validates requirements (physical image on disk, category, valid price) and publishes to the public online store.')
                ->action(function () use ($product) {
                    $syncService = app(\App\Services\Operational\CatalogSyncService::class);
                    $val = $syncService->validatePublicationEligibility($product);
                    if (!$val['eligible']) {
                        Notification::make()
                            ->title('Cannot publish product')
                            ->danger()
                            ->body("Missing required details:\n• " . implode("\n• ", $val['missing']))
                            ->persistent()
                            ->send();
                        return;
                    }

                    $product->update(['is_published' => true]);
                    Notification::make()
                        ->title('Product Published to Storefront')
                        ->success()
                        ->body("{$product->name} is now live on the public storefront.")
                        ->send();
                }),

            // 7. UNPUBLISH FROM STOREFRONT
            Actions\Action::make('unpublish_storefront')
                ->label('Unpublish from Storefront')
                ->icon('heroicon-o-eye-slash')
                ->color('warning')
                ->visible(fn() => $product->is_published)
                ->requiresConfirmation()
                ->modalHeading('Unpublish from Storefront')
                ->modalDescription('Hides this product from the online webshop while preserving full access in POS, inventory, and accounting.')
                ->action(function () use ($product) {
                    $product->update(['is_published' => false]);
                    Notification::make()
                        ->title('Product Unpublished')
                        ->warning()
                        ->body("{$product->name} hidden from webshop. Remains active in POS & Inventory.")
                        ->send();
                }),

            // 8. VIEW ON STOREFRONT
            Actions\Action::make('view_on_store')
                ->label('Storefront')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn() => url('/products/' . $product->id))
                ->openUrlInNewTab(),
        ];
    }
}
