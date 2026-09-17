<?php

namespace App\Filament\Resources\StockCountResource\Pages;

use App\Filament\Resources\StockCountResource;
use App\Models\Inventory\StockCount;
use App\Models\Inventory\StockLevel;
use App\Services\Inventory\InventoryService;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewStockCount extends ViewRecord
{
    protected static string $resource = StockCountResource::class;

    protected string $view = 'filament.resources.stock-counts.view-stock-count';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('populate_items')
                ->label('Populate Stock')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('gray')
                ->visible(fn() => in_array($this->record->status, ['draft', 'in_progress']) && $this->record->items()->count() === 0)
                ->requiresConfirmation()
                ->modalHeading("Snapshot {$this->record->warehouse?->name} Stock")
                ->modalDescription('Pre-fill this audit with all current on-hand inventory balances from this warehouse.')
                ->action(function (): void {
                    $stockLevels = StockLevel::where('warehouse_id', $this->record->warehouse_id)
                        ->where('quantity_on_hand', '>', 0)
                        ->with(['product', 'variant'])
                        ->get();

                    foreach ($stockLevels as $level) {
                        $this->record->items()->create([
                            'product_id' => $level->product_id,
                            'variant_id' => $level->variant_id,
                            'expected_quantity' => $level->quantity_on_hand,
                            'counted_quantity' => $level->quantity_on_hand,
                            'variance_quantity' => 0,
                            'unit_cost_npr' => (float)$level->unit_cost_npr,
                            'variance_value_npr' => 0.00,
                            'is_reconciled' => false,
                        ]);
                    }
                    $this->record->recalculateTotals();
                    $this->record->refresh();

                    Notification::make()
                        ->title('Warehouse Stock Populated')
                        ->body("Loaded {$stockLevels->count()} stock lines into count #{$this->record->count_number}.")
                        ->success()
                        ->send();
                }),

            Actions\Action::make('reconcile')
                ->label('Reconcile Count')
                ->icon('heroicon-m-check-badge')
                ->color('success')
                ->visible(fn() => in_array($this->record->status, ['completed', 'draft', 'in_progress']))
                ->requiresConfirmation()
                ->modalHeading("Reconcile Count: #{$this->record->count_number}")
                ->modalDescription("This will adjust physical stock balances to match counted quantities and auto-post variance journal entries to accounting.")
                ->action(function (): void {
                    try {
                        app(InventoryService::class)->reconcileStockCount($this->record, auth()->user());
                        $this->record->refresh();

                        Notification::make()
                            ->title('Count Reconciled')
                            ->body("Count #{$this->record->count_number} variances reconciled and stock balances updated.")
                            ->success()
                            ->send();
                    } catch (\Throwable $e) {
                        Notification::make()
                            ->title('Reconciliation Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Actions\EditAction::make()
                ->visible(fn() => $this->record->status !== 'reconciled'),
        ];
    }
}
