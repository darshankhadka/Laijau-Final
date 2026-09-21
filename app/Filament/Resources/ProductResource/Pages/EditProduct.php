<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;
    protected Width | string | null $maxWidth = 'full';

    public function getTitle(): string
    {
        return 'Edit Product: ' . ($this->getRecord()?->name ?? 'Piece');
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('publish_storefront')
                ->label('Publish to Storefront')
                ->icon('heroicon-o-globe-alt')
                ->color('success')
                ->visible(fn () => !$this->getRecord()->is_published && $this->getRecord()->is_active)
                ->requiresConfirmation()
                ->modalHeading('Publish Product to Storefront')
                ->modalDescription('Validates requirements (physical image on disk, category, valid price) and publishes to the public online store.')
                ->action(function () {
                    $record = $this->getRecord();
                    $syncService = app(\App\Services\Operational\CatalogSyncService::class);
                    $val = $syncService->validatePublicationEligibility($record);
                    if (!$val['eligible']) {
                        Notification::make()
                            ->title('Cannot publish product')
                            ->danger()
                            ->body("Missing required details:\n• " . implode("\n• ", $val['missing']))
                            ->persistent()
                            ->send();
                        return;
                    }

                    $record->update(['is_published' => true]);
                    Notification::make()
                        ->title('Product Published to Storefront')
                        ->success()
                        ->body("{$record->name} is now live on the public storefront.")
                        ->send();
                }),

            Actions\Action::make('unpublish_storefront')
                ->label('Unpublish from Storefront')
                ->icon('heroicon-o-eye-slash')
                ->color('warning')
                ->visible(fn () => $this->getRecord()->is_published)
                ->requiresConfirmation()
                ->modalHeading('Unpublish from Storefront')
                ->modalDescription('Hides this product from the online webshop while preserving full access in POS, inventory, and accounting.')
                ->action(function () {
                    $record = $this->getRecord();
                    $record->update(['is_published' => false]);
                    Notification::make()
                        ->title('Product Unpublished')
                        ->warning()
                        ->body("{$record->name} hidden from webshop. Remains active in POS & Inventory.")
                        ->send();
                }),

            Actions\Action::make('preview_store')
                ->label('Preview on Store')
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->color('gray')
                ->url(fn () => url('/products/' . $this->getRecord()->id))
                ->openUrlInNewTab(),

            Actions\Action::make('duplicate')
                ->label('Duplicate')
                ->icon('heroicon-o-document-duplicate')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Duplicate Product')
                ->modalDescription('Creates a safe draft clone of this piece with fresh SKUs and barcodes.')
                ->action(function () {
                    $newProduct = app(\App\Services\ProductService::class)->duplicate($this->getRecord());

                    Notification::make()
                        ->title('Product Duplicated')
                        ->body("{$newProduct->name} created as draft.")
                        ->success()
                        ->send();

                    return redirect()->to(ProductResource::getUrl('view', ['record' => $newProduct->id]));
                }),

            Actions\Action::make('toggle_archive')
                ->label(fn () => $this->getRecord()->is_active ? 'Archive Product' : 'Restore Product')
                ->icon(fn () => $this->getRecord()->is_active ? 'heroicon-o-archive-box-arrow-down' : 'heroicon-o-arrow-path')
                ->color('warning')
                ->action(function () {
                    $record = $this->getRecord();
                    if ($record->is_active) {
                        app(\App\Services\ProductService::class)->archive($record);
                        Notification::make()->title('Product Archived')->info()->send();
                    } else {
                        app(\App\Services\ProductService::class)->unarchive($record);
                        Notification::make()->title('Product Restored')->success()->send();
                    }
                }),

            Actions\DeleteAction::make()
                ->label('Delete')
                ->modalHeading('Delete Product Confirmation')
                ->modalDescription('Are you sure? For products with historical order data, we recommend Archiving to preserve sales reports.'),
        ];
    }

    protected function getSaveFormAction(): Actions\Action
    {
        return parent::getSaveFormAction()
            ->label('Save Changes')
            ->icon('heroicon-o-check');
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();
        $result = null;
        if ($record) {
            $result = app(\App\Services\StorefrontRevalidationService::class)->revalidateProduct($record, async: false);
        }

        if ($result && !($result['success'] ?? false)) {
            \Filament\Notifications\Notification::make()
                ->title('Product Saved (Storefront Sync Notice)')
                ->body('Changes saved in database, but storefront cache revalidation warning: ' . ($result['message'] ?? 'Could not reach endpoint.'))
                ->warning()
                ->persistent()
                ->send();
        } else {
            \Filament\Notifications\Notification::make()
                ->title('Storefront Synchronized')
                ->body('Changes saved and storefront cache refreshed successfully.')
                ->success()
                ->send();
        }
    }
}
