<?php

namespace App\Filament\Resources\PurchaseOrderResource\Pages;

use App\Filament\Resources\PurchaseOrderResource;
use App\Models\Inventory\PurchaseOrder;
use App\Services\Inventory\InventoryService;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseOrder extends ViewRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected string $view = 'filament.resources.purchase-orders.view-purchase-order';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('print_po')
                ->label('Print PO')
                ->icon('heroicon-m-printer')
                ->color('gray')
                ->url(fn () => route('admin.purchase-orders.print', ['purchaseOrder' => $this->record->id]), shouldOpenInNewTab: true),

            Actions\Action::make('receive_goods')
                ->label('Receive Goods')
                ->icon('heroicon-m-inbox-arrow-down')
                ->color('success')
                ->visible(fn () => in_array($this->record->status, ['approved', 'ordered', 'in_transit', 'partially_received']))
                ->modalHeading("Receive Goods: PO #{$this->record->po_number}")
                ->modalDescription("Enter quantities physically verified at {$this->record->warehouse?->name}. This will update stock on hand and ledger movements.")
                ->form(function (): array {
                    $fields = [];
                    $items = $this->record->items()->with(['product', 'variant'])->get();
                    foreach ($items as $item) {
                        $name = $item->product?->name ?? 'Product';
                        $sku = $item->variant?->sku ?? $item->product?->sku ?? 'SKU';
                        $remaining = max(0, (int)$item->quantity_ordered - (int)$item->quantity_received);
                        $fields[] = TextInput::make("received_qty_{$item->id}")
                            ->label("{$name} ({$sku})")
                            ->helperText("Ordered: {$item->quantity_ordered} | Received so far: {$item->quantity_received} | Remaining: {$remaining}")
                            ->numeric()
                            ->minValue(0)
                            ->default($remaining)
                            ->required();
                    }
                    return $fields;
                })
                ->action(function (array $data): void {
                    try {
                        $receivedMap = [];
                        foreach ($data as $key => $val) {
                            if (str_starts_with($key, 'received_qty_')) {
                                $itemId = (int)str_replace('received_qty_', '', $key);
                                $receivedMap[$itemId] = (int)$val;
                            }
                        }

                        app(InventoryService::class)->receivePurchaseOrder($this->record, $receivedMap, auth()->user());
                        $this->record->refresh();

                        Notification::make()
                            ->title('Goods Received')
                            ->body("PO #{$this->record->po_number} receiving processed. Status is now " . ucfirst(str_replace('_', ' ', $this->record->status)) . ".")
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Goods Receiving Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\Action::make('cancel_po')
                ->label('Cancel PO')
                ->icon('heroicon-m-x-circle')
                ->color('danger')
                ->visible(fn () => !in_array($this->record->status, ['received', 'cancelled']))
                ->requiresConfirmation()
                ->action(function (): void {
                    try {
                        app(InventoryService::class)->cancelPurchaseOrder($this->record, 'Cancelled from PO workspace', auth()->user());
                        $this->record->refresh();

                        Notification::make()
                            ->title('Purchase Order Cancelled')
                            ->body("PO #{$this->record->po_number} was cancelled.")
                            ->warning()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Cancellation Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\EditAction::make()
                ->visible(fn () => in_array($this->record->status, ['draft', 'submitted'])),
        ];
    }
}
