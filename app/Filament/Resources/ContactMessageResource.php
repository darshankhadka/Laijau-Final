<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\ContactMessageResource\Pages;
use App\Models\ContactMessage;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Crm\CrmService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ContactMessageResource extends Resource
{
    protected static ?string $model = ContactMessage::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static string | \UnitEnum | null $navigationGroup = 'CRM';
    protected static ?string $navigationLabel = 'Customer Inquiries';
    protected static ?int $navigationSort = 30;

    public static function getNavigationBadge(): ?string
    {
        $count = ContactMessage::where('status', ContactMessage::STATUS_NEW)->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Inquiry Classification & Context')
                    ->columns(3)
                    ->schema([
                        Select::make('inquiry_type')
                            ->label('Inquiry Classification')
                            ->options(ContactMessage::getInquiryTypes())
                            ->required(),

                        Select::make('priority')
                            ->label('Priority')
                            ->options(ContactMessage::getPriorities())
                            ->required(),

                        Select::make('status')
                            ->label('Inquiry Status')
                            ->options(ContactMessage::getStatuses())
                            ->required(),
                    ]),

                Section::make('Patron Contact & Message Details')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Patron Name')
                            ->disabled(),

                        TextInput::make('email')
                            ->label('Email Address')
                            ->disabled(),

                        TextInput::make('phone')
                            ->label('Phone / WhatsApp')
                            ->placeholder('None provided'),

                        Select::make('customer_id')
                            ->label('Linked Customer Profile')
                            ->relationship('customer', 'name', fn(Builder $query) => $query->where('role', 'customer'))
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        TextInput::make('subject')
                            ->label('Subject')
                            ->disabled()
                            ->columnSpanFull(),

                        Textarea::make('message')
                            ->label('Customer Message')
                            ->rows(5)
                            ->disabled()
                            ->columnSpanFull(),
                    ]),

                Section::make('Order & Product Association')
                    ->columns(2)
                    ->schema([
                        Select::make('order_id')
                            ->label('Associated Order')
                            ->relationship('order', 'order_number')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('Connect this question or delivery complaint directly to an order.'),

                        Select::make('product_id')
                            ->label('Referenced Garment')
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                    ]),

                Section::make('Staff Assignment & Concierge Notes')
                    ->schema([
                        Select::make('assigned_staff_id')
                            ->label('Assigned Support Agent / Manager')
                            ->relationship('assignedStaff', 'name', fn(Builder $query) => $query->whereIn('role', [
                                'admin',
                                'super_admin',
                                'workspace_admin',
                                'store_manager',
                                'support_agent',
                                'sales_representative'
                            ]))
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Textarea::make('reply_notes')
                            ->label('Internal Timeline & Response Record')
                            ->placeholder('Document actions taken, WhatsApp communication timestamp, or resolution details...')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('Patron')
                    ->searchable()
                    ->weight('bold')
                    ->description(fn(ContactMessage $record) => $record->phone ?: $record->email),

                TextColumn::make('inquiry_type')
                    ->label('Category')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => ContactMessage::getInquiryTypes()[$state] ?? ucfirst(str_replace('_', ' ', $state)))
                    ->color(fn(string $state): string => match ($state) {
                        ContactMessage::INQUIRY_COMPLAINT => 'danger',
                        ContactMessage::INQUIRY_ORDER => 'warning',
                        ContactMessage::INQUIRY_DELIVERY => 'info',
                        ContactMessage::INQUIRY_PRODUCT => 'primary',
                        ContactMessage::INQUIRY_BESPOKE => 'purple',
                        default => 'gray',
                    }),

                TextColumn::make('subject')
                    ->label('Subject')
                    ->formatStateUsing(fn(?string $state): string => $state ?: '(General Inquiry)')
                    ->searchable()
                    ->limit(30)
                    ->description(function (ContactMessage $record) {
                        $parts = [];
                        if ($record->order) $parts[] = "📦 Order #{$record->order->order_number}";
                        if ($record->product) $parts[] = "👗 {$record->product->name}";
                        return !empty($parts) ? implode(' | ', $parts) : null;
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        ContactMessage::STATUS_NEW => 'New / Unread',
                        ContactMessage::STATUS_IN_PROGRESS => 'In Progress',
                        ContactMessage::STATUS_RESOLVED => 'Resolved',
                        default => ucfirst($state),
                    })
                    ->color(fn(string $state): string => match ($state) {
                        ContactMessage::STATUS_NEW => 'danger',
                        ContactMessage::STATUS_IN_PROGRESS => 'warning',
                        ContactMessage::STATUS_RESOLVED => 'success',
                        default => 'gray',
                    }),

                TextColumn::make('assignedStaff.name')
                    ->label('Assigned')
                    ->placeholder('Unassigned')
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Received At')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(ContactMessage::getStatuses()),

                SelectFilter::make('inquiry_type')
                    ->options(ContactMessage::getInquiryTypes()),

                SelectFilter::make('priority')
                    ->options(ContactMessage::getPriorities()),
            ])
            ->recordActions([
                Action::make('to_lead')
                    ->label('To Lead')
                    ->icon('heroicon-o-user-plus')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Convert Inquiry to CRM Sales Lead')
                    ->modalDescription(fn(ContactMessage $record) => "Create a sales lead for '{$record->name}' in the CRM Pipeline?")
                    ->action(function (ContactMessage $record) {
                        $lead = app(CrmService::class)->createLeadFromInquiry($record);

                        Notification::make()
                            ->title('Converted to CRM Lead # ' . $lead->id)
                            ->body("Inquiry for {$record->name} is now available on the CRM Pipeline.")
                            ->success()
                            ->send();
                    }),

                Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-m-chat-bubble-left')
                    ->color('success')
                    ->url(function (ContactMessage $record): ?string {
                        if (empty($record->phone)) return null;
                        $clean = preg_replace('/[^0-9]/', '', (string)$record->phone);
                        if (strlen($clean) === 10) $clean = '977' . $clean;
                        $text = "Namaste {$record->name},\nRegarding your inquiry at Laijau: \"{$record->subject}\"...";
                        return "https://wa.me/{$clean}?text=" . urlencode($text);
                    })
                    ->openUrlInNewTab()
                    ->visible(fn(ContactMessage $record): bool => !empty($record->phone)),

                Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-m-check-circle')
                    ->color('success')
                    ->action(function (ContactMessage $record) {
                        $record->update([
                            'status' => ContactMessage::STATUS_RESOLVED,
                            'resolved_at' => now(),
                        ]);
                        Notification::make()->title('Inquiry marked as resolved')->success()->send();
                    })
                    ->visible(fn(ContactMessage $record): bool => $record->status !== ContactMessage::STATUS_RESOLVED),

                EditAction::make()
                    ->label('Review & Notes')
                    ->modalHeading('Review Contact Message'),

                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('mark_resolved')
                        ->label('Mark as Resolved')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn(\Illuminate\Database\Eloquent\Collection $records) => $records->each->update([
                            'status' => ContactMessage::STATUS_RESOLVED,
                            'resolved_at' => now(),
                        ]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageContactMessages::route('/'),
        ];
    }
}
