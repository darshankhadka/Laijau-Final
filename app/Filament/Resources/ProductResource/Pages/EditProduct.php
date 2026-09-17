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
