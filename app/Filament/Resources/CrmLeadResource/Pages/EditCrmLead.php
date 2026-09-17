<?php

declare(strict_types=1);

namespace App\Filament\Resources\CrmLeadResource\Pages;

use App\Filament\Resources\CrmLeadResource;
use App\Models\CrmLead;
use App\Services\Crm\CrmService;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCrmLead extends EditRecord
{
    protected static string $resource = CrmLeadResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('whatsapp')
                ->label('Clienteling WhatsApp')
                ->icon('heroicon-m-chat-bubble-left')
                ->color('success')
                ->url(function (): ?string {
                    /** @var CrmLead $lead */
                    $lead = $this->getRecord();
                    app(CrmService::class)->recordWhatsAppOutreach($lead);
                    return $lead->whats_app_url;
                })
                ->openUrlInNewTab()
                ->visible(fn (): bool => !empty($this->getRecord()->phone)),

            Actions\Action::make('convert_to_order')
                ->label('Convert to Order')
                ->icon('heroicon-m-shopping-bag')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Convert Lead to Sales Order')
                ->modalDescription(fn (): string => "Convert inquiry '{$this->getRecord()->title}' for customer '{$this->getRecord()->contact_name}' into an active order?")
                ->form([
                    TextInput::make('total_amount')
                        ->label('Final Agreed Amount (NPR)')
                        ->numeric()
                        ->prefix('Rs.')
                        ->default(fn (): float => (float)($this->getRecord()->estimated_value > 0 ? $this->getRecord()->estimated_value : 0.00))
                        ->required(),
                    Select::make('payment_method')
                        ->label('Payment Arrangement')
                        ->options([
                            'cod' => 'Cash on Delivery (COD)',
                            'manual_bank_transfer' => 'Bank Transfer / QR (eSewa/Fonepay)',
                            'connectips' => 'ConnectIPS',
                        ])
                        ->default('cod'),
                    TextInput::make('shipping_address')
                        ->label('Delivery Address')
                        ->default('Kathmandu Valley, Nepal')
                        ->required(),
                ])
                ->action(function (array $data): void {
                    /** @var CrmLead $lead */
                    $lead = $this->getRecord();
                    $order = app(CrmService::class)->convertLeadToOrder($lead, $data);
                    Notification::make()
                        ->title('Order Created Successfully!')
                        ->body("Order #{$order->order_number} is now in Fulfillment Hub.")
                        ->success()
                        ->send();
                    $this->redirect($this->getResource()::getUrl('edit', ['record' => $lead]));
                })
                ->visible(fn (): bool => empty($this->getRecord()->order_id) && $this->getRecord()->stage !== CrmLead::STAGE_LOST),

            Actions\Action::make('fulfillment_hub')
                ->label('Open Fulfillment Hub')
                ->icon('heroicon-m-truck')
                ->color('success')
                ->url(fn (): string => url('/intadmin/fulfillment-hub'))
                ->openUrlInNewTab()
                ->visible(fn (): bool => !empty($this->getRecord()->order_id)),

            Actions\Action::make('kanban_view')
                ->label('Pipeline & Kanban')
                ->icon('heroicon-m-view-columns')
                ->url(route('filament.admin.pages.crm-kanban'))
                ->color('gray')
                ->outlined(),

            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
