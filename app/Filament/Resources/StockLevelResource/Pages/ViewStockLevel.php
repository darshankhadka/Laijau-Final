<?php

namespace App\Filament\Resources\StockLevelResource\Pages;

use App\Filament\Resources\StockLevelResource;
use App\Models\Inventory\StockLevel;
use App\Services\Inventory\InventoryService;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewStockLevel extends ViewRecord
{
    protected static string $resource = StockLevelResource::class;

    protected string $view = 'filament.resources.stock-levels.view-stock-level';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('quick_adjust')
                ->label('Adjust Stock')
                ->icon('heroicon-m-arrows-up-down')
                ->color('warning')
                ->modalHeading("Audited Stock Adjustment: {$this->record->product?->name}")
                ->modalDescription("Current physical balance: {$this->record->quantity_on_hand} pcs at {$this->record->warehouse?->name}")
                ->form([
                    Select::make('movement_type')
                        ->label('Adjustment Type')
                        ->options([
                            'correction' => 'Correction (Physical Count Discrepancy)',
                            'damage' => 'Damage (Defective / Damaged Item)',
                            'loss' => 'Loss / Shrinkage',
                            'adjustment_gain' => 'Found Stock (Physical Surplus)',
                        ])
                        ->required()
                        ->default('correction'),

                    TextInput::make('delta_quantity')
                        ->label('Quantity Delta (+ / -)')
                        ->numeric()
                        ->required()
                        ->helperText('Use positive number to increase, negative to decrease.'),

                    TextInput::make('reason')
                        ->label('Reason for adjustment')
                        ->required()
                        ->placeholder('e.g. Physical count reconciliation'),
                ])
                ->action(function (array $data): void {
                    $delta = (int)$data['delta_quantity'];
                    if ($delta === 0) {
                        return;
                    }

                    app(InventoryService::class)->recordStockMovement([
                        'warehouse_id' => $this->record->warehouse_id,
                        'product_id' => $this->record->product_id,
                        'variant_id' => $this->record->variant_id,
                        'movement_type' => $data['movement_type'],
                        'quantity' => $delta,
                        'unit_cost_npr' => (float)$this->record->unit_cost_npr,
                        'reference_type' => 'manual_adjustment',
                        'reason' => $data['reason'],
                    ], auth()->user());

                    $this->record->refresh();

                    Notification::make()
                        ->title('Stock Adjusted')
                        ->body("Updated physical balance: {$this->record->quantity_on_hand} pcs.")
                        ->success()
                        ->send();
                }),

            Actions\Action::make('transfer')
                ->label('Transfer Stock')
                ->icon('heroicon-m-truck')
                ->color('primary')
                ->url(route('filament.admin.resources.stock-transfers.create')),

            Actions\Action::make('barcode')
                ->label('Print Label')
                ->icon('heroicon-m-qr-code')
                ->color('gray')
                ->url(route('filament.admin.pages.barcodes-labels')),

            Actions\EditAction::make(),
        ];
    }
}
