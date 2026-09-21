<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Inventory\Warehouse;
use App\Models\Product;
use App\Services\ProductImportExportService;
use App\Services\StorefrontRevalidationService;
use Filament\Actions;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class ListProducts extends ListRecords
{
    protected static string $resource = ProductResource::class;
    protected Width | string | null $maxWidth = 'full';

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('All Master Products')
                ->icon('heroicon-m-squares-2x2')
                ->badge(Product::count()),

            'published' => Tab::make('Published (Storefront)')
                ->icon('heroicon-m-globe-alt')
                ->badge(Product::where('is_published', true)->where('is_active', true)->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('is_published', true)->where('is_active', true)),

            'drafts' => Tab::make('Unpublished (POS / Internal Only)')
                ->icon('heroicon-m-building-storefront')
                ->badge(Product::where('is_published', false)->where('is_active', true)->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('is_published', false)->where('is_active', true)),

            'in_stock' => Tab::make('In Stock')
                ->icon('heroicon-m-check-circle')
                ->badge(Product::where('quantity', '>', 3)->count())
                ->badgeColor('primary')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('quantity', '>', 3)),

            'low_stock' => Tab::make('Low Stock (1-3)')
                ->icon('heroicon-m-exclamation-triangle')
                ->badge(Product::where('quantity', '>', 0)->where('quantity', '<=', 3)->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('quantity', '>', 0)->where('quantity', '<=', 3)),

            'out_of_stock' => Tab::make('Out of Stock')
                ->icon('heroicon-m-x-circle')
                ->badge(Product::where('quantity', '<=', 0)->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn(Builder $query) => $query->where('quantity', '<=', 0)),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            // 1. QUICK STOCK ENTRY
            Actions\Action::make('quick_stock')
                ->label('Quick Stock Entry [F3]')
                ->icon('heroicon-o-bolt')
                ->color('success')
                ->url(url('/intadmin/quick-stock-entry')),

            // 2. PRINT BARCODES
            Actions\Action::make('print_barcodes')
                ->label('Print Barcodes')
                ->icon('heroicon-o-qr-code')
                ->color('info')
                ->url(url('/intadmin/barcodes-labels')),

            // 3. IMPORT CSV WITH PRE-VALIDATION
            Actions\Action::make('import_csv')
                ->label('Import CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->form([
                    FileUpload::make('csv_file')
                        ->label('Select Products CSV File *')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->maxSize(5120)
                        ->disk('local')
                        ->directory('imports/products')
                        ->required()
                        ->helperText('Upload a standard CSV containing Product Name, SKU, Barcode, Price, Cost, Category, Brand, Stock.'),

                    Select::make('warehouse_id')
                        ->label('Assign Opening Stock To Warehouse *')
                        ->options(Warehouse::where('is_active', true)->pluck('name', 'id'))
                        ->default(fn() => Warehouse::where('is_default', true)->first()?->id ?: Warehouse::first()?->id)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $filePath = Storage::disk('local')->path($data['csv_file']);
                    $uploadedFile = new \Illuminate\Http\UploadedFile($filePath, basename($filePath));

                    $importService = app(ProductImportExportService::class);
                    $validation = $importService->validateImportCsv($uploadedFile);

                    if (!$validation['valid']) {
                        Notification::make()
                            ->title('Import Validation Failed')
                            ->body("Found {$validation['error_count']} errors in CSV. First error: " . ($validation['errors'][0] ?? ''))
                            ->danger()
                            ->persistent()
                            ->send();
                        return;
                    }

                    $results = $importService->commitImport(
                        $validation['rows'],
                        (int) $data['warehouse_id'],
                        auth()->user()
                    );

                    Notification::make()
                        ->title('Import Completed Successfully')
                        ->body("Created {$results['created_products']} products and {$results['created_variants']} variants. Updated {$results['updated_products']} existing records.")
                        ->success()
                        ->send();
                }),

            // 4. EXPORT ALL / FILTERED CSV
            Actions\Action::make('export_csv')
                ->label('Export CSV')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function () {
                    return app(ProductImportExportService::class)->exportCsv(
                        $this->getFilteredTableQuery()
                    );
                }),

            // 5. PURGE STOREFRONT CACHE
            Actions\Action::make('sync_storefront')
                ->label('Sync Storefront')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Purge Storefront Cache')
                ->modalDescription('Notify public storefront to refresh product catalogue, categories, and inventory caches immediately.')
                ->action(function () {
                    $result = app(StorefrontRevalidationService::class)->revalidateAll();
                    if ($result['success']) {
                        Notification::make()
                            ->title('Storefront Cache Purged')
                            ->body('Public storefront has refreshed catalogue and inventory caches.')
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('Storefront Sync Warning')
                            ->body($result['message'] ?? 'Could not reach storefront revalidation endpoint.')
                            ->warning()
                            ->send();
                    }
                }),

            // 6. CREATE PRODUCT
            Actions\CreateAction::make()
                ->label('+ New Product')
                ->icon('heroicon-o-plus'),
        ];
    }
}
