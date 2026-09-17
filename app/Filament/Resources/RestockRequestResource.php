<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RestockRequestResource\Pages;
use App\Mail\RestockNotificationMail;
use App\Models\Product;
use App\Models\RestockRequest;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Mail;

class RestockRequestResource extends Resource
{
    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $model = RestockRequest::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-bell-alert';
    protected static string | \UnitEnum | null $navigationGroup = 'Commerce';
    protected static ?string $navigationLabel = 'Restock Requests';
    protected static ?int $navigationSort = 80;

    public static function form(Schema $form): Schema
    {
        return $form->schema([
            Select::make('product_id')
                ->relationship('product', 'name')
                ->required()
                ->disabled(),
            TextInput::make('email')
                ->email()
                ->disabled(),
            TextInput::make('phone')
                ->disabled(),
            TextInput::make('quantity')
                ->numeric()
                ->default(1),
            TextInput::make('size')
                ->disabled(),
            TextInput::make('color')
                ->disabled(),
            Select::make('status')
                ->options(RestockRequest::getStatuses())
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('product.featured_image')
                    ->label('Photo')
                    ->circular()
                    ->size(40),

                TextColumn::make('product.name')
                    ->label('Product')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn(RestockRequest $record): string => 'SKU: ' . ($record->productVariant?->sku ?? $record->product?->sku ?? 'N/A')),

                TextColumn::make('email')
                    ->label('Customer')
                    ->formatStateUsing(fn($record) => $record->email . ($record->phone ? " ({$record->phone})" : ''))
                    ->searchable(['email', 'phone'])
                    ->copyable()
                    ->sortable(),

                TextColumn::make('size')
                    ->label('Variant / Size')
                    ->state(fn(RestockRequest $record): string => trim(($record->size ?: 'Any') . ($record->color ? ' / ' . $record->color : '')))
                    ->badge()
                    ->color('gray'),

                TextColumn::make('quantity')
                    ->label('Qty')
                    ->sortable()
                    ->badge()
                    ->color('info'),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => RestockRequest::getStatuses()[$state] ?? ucfirst($state))
                    ->color(fn(string $state): string => match ($state) {
                        RestockRequest::STATUS_RESTOCKED, RestockRequest::STATUS_FULFILLED, 'notified' => 'success',
                        RestockRequest::STATUS_CONTACTED => 'info',
                        RestockRequest::STATUS_ORDERED => 'primary',
                        RestockRequest::STATUS_CANCELLED => 'danger',
                        default => 'warning',
                    })
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Request Date')
                    ->dateTime('M d, Y')
                    ->timezone('Asia/Kathmandu')
                    ->sortable(),

                TextColumn::make('notified_at')
                    ->label('Notified On')
                    ->dateTime('M d, Y H:i')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(RestockRequest::getStatuses()),
                SelectFilter::make('product')
                    ->relationship('product', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),

                // WhatsApp Contact
                \Filament\Actions\Action::make('whatsapp_customer')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->visible(fn(RestockRequest $record) => !empty($record->phone))
                    ->url(function (RestockRequest $record): string {
                        $cleanPhone = preg_replace('/[^0-9]/', '', (string) $record->phone);
                        if (strlen($cleanPhone) === 10) {
                            $cleanPhone = '977' . $cleanPhone;
                        }
                        $productName = $record->product?->name ?? 'item';
                        $size = $record->size ? " (Size: {$record->size})" : '';
                        $msg = "Namaste! This is Laijau Store regarding your restock request for {$productName}{$size}. The item is now back in stock!";
                        return "https://wa.me/{$cleanPhone}?text=" . urlencode($msg);
                    })
                    ->openUrlInNewTab(),

                // Single Notify Action
                \Filament\Actions\Action::make('notify')
                    ->label('Send Email')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('info')
                    ->visible(fn(RestockRequest $record) => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->modalHeading('Send Restock Notification Email')
                    ->modalDescription(fn(RestockRequest $record) => "Send immediate restock availability email to {$record->email} for {$record->product?->name}?")
                    ->action(function (RestockRequest $record) {
                        if ($record->product) {
                            try {
                                Mail::to($record->email)->send(new RestockNotificationMail($record->product));
                            } catch (\Throwable $e) {
                                // Log but proceed
                            }
                        }
                        $record->update([
                            'status' => 'notified',
                            'notified_at' => now(),
                        ]);

                        Notification::make()
                            ->title('Customer Notified')
                            ->body("Restock notification sent to {$record->email}.")
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                // Bulk Notify Action
                \Filament\Actions\BulkAction::make('notify_selected')
                    ->label('Notify Selected Waiting Customers')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Notify Selected Customers')
                    ->modalDescription('Send availability emails to all selected pending customers?')
                    ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                        $count = 0;
                        foreach ($records as $record) {
                            if ($record->status === 'pending') {
                                if ($record->product) {
                                    try {
                                        Mail::to($record->email)->send(new RestockNotificationMail($record->product));
                                    } catch (\Throwable $e) {
                                    }
                                }
                                $record->update([
                                    'status' => 'notified',
                                    'notified_at' => now(),
                                ]);
                                $count++;
                            }
                        }

                        Notification::make()
                            ->title('Notifications Dispatched')
                            ->body("Sent restock emails to {$count} waiting customer(s).")
                            ->success()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRestockRequests::route('/'),
        ];
    }
}
