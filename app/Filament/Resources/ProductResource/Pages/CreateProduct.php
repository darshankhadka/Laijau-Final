<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Width;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;
    protected Width | string | null $maxWidth = 'full';

    protected static ?string $title = 'New Product';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreateFormAction(): Actions\Action
    {
        return parent::getCreateFormAction()
            ->label('Publish Product')
            ->icon('heroicon-o-check');
    }

    protected function getCreateAnotherFormAction(): Actions\Action
    {
        return parent::getCreateAnotherFormAction()
            ->label('Publish & Create Another');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (empty($data['sku'])) {
            $category = null;
            if (!empty($data['categories'])) {
                $catId = is_array($data['categories']) ? reset($data['categories']) : $data['categories'];
                $category = \App\Models\Category::find($catId);
            }
            $data['sku'] = app(\App\Services\ProductSkuService::class)->generate(
                category: $category,
                name: $data['name'] ?? null
            );
        }

        if (empty($data['slug']) && !empty($data['name'])) {
            $data['slug'] = \Illuminate\Support\Str::slug($data['name']);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        $result = null;
        if ($record) {
            $result = app(\App\Services\StorefrontRevalidationService::class)->revalidateProduct($record, async: false);
        }

        if ($result && !($result['success'] ?? false)) {
            \Filament\Notifications\Notification::make()
                ->title('Product Saved (Storefront Sync Notice)')
                ->body('Product saved in database, but storefront cache revalidation warning: ' . ($result['message'] ?? 'Could not reach endpoint.'))
                ->warning()
                ->persistent()
                ->send();
        } else {
            \Filament\Notifications\Notification::make()
                ->title('Storefront Synchronized')
                ->body('Product published and storefront cache refreshed successfully.')
                ->success()
                ->send();
        }
    }
}
