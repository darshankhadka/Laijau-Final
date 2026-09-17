<?php

declare(strict_types=1);

namespace App\Filament\Resources;

use App\Filament\Resources\CrmLeadResource\Pages;
use App\Models\CrmLead;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Crm\CrmService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class CrmLeadResource extends Resource
{
    protected static ?string $model = CrmLead::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-group';
    protected static string | \UnitEnum | null $navigationGroup = 'CRM';
    protected static ?string $navigationLabel = 'Inquiries & Leads';
    protected static ?int $navigationSort = 10;

    public static function getNavigationBadge(): ?string
    {
        $overdue = CrmLead::overdueFollowUps()->count();
        return $overdue > 0 ? "{$overdue} Overdue" : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Inquiry & Lead Overview')
                    ->description('Categorization, priority, and sales potential.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Inquiry / Lead Title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. Winter Footwear Inquiry'),

                        Select::make('channel')
                            ->label('Lead Source')
                            ->options(CrmLead::getChannels())
                            ->default(CrmLead::CHANNEL_WHATSAPP)
                            ->required(),

                        Select::make('stage')
                            ->label('Pipeline Stage')
                            ->options(CrmLead::getStages())
                            ->default(CrmLead::STAGE_NEW)
                            ->required(),

                        Select::make('priority')
                            ->label('Priority')
                            ->options(CrmLead::getPriorities())
                            ->default(CrmLead::PRIORITY_MEDIUM)
                            ->required(),

                        TextInput::make('estimated_value')
                            ->label('Estimated Value (NPR)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->default(0.00),

                        Select::make('currency')
                            ->label('Currency')
                            ->options([
                                'NPR' => 'NPR (Rs.)',
                            ])
                            ->default('NPR')
                            ->required(),
                    ]),

                Section::make('Customer & Contact Details')
                    ->description('Patron profile and communication identifiers.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('contact_name')
                            ->label('Patron / Contact Name')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('e.g. Priya Sharma'),

                        TextInput::make('phone')
                            ->label('WhatsApp / Phone')
                            ->tel()
                            ->placeholder('9841234567 or +977 9841234567')
                            ->helperText('Used for 1-click WhatsApp clienteling dispatch.'),

                        TextInput::make('email')
                            ->email()
                            ->placeholder('priya@example.com'),

                        Select::make('customer_id')
                            ->label('Linked Customer Profile')
                            ->relationship('customer', 'name', fn(Builder $query) => $query->where('role', 'customer'))
                            ->searchable(['name', 'email', 'phone'])
                            ->preload()
                            ->nullable()
                            ->helperText(function (?CrmLead $record) {
                                if (!$record || !$record->customer) {
                                    return 'Leave empty to auto-resolve upon order conversion.';
                                }
                                return "Lifetime Spend: Rs. " . number_format($record->customer->lifetime_spend, 2) . " ({$record->customer->total_orders_count} orders)";
                            }),
                    ]),

                Section::make('Product & Order Linkage')
                    ->description('Direct integration with catalog items and showroom/concierge orders.')
                    ->columns(2)
                    ->schema([
                        Select::make('product_id')
                            ->label('Inquired Product / Garment')
                            ->relationship('product', 'name')
                            ->searchable(['name', 'sku'])
                            ->preload()
                            ->nullable()
                            ->helperText('Links this inquiry to a specific catalog product.'),

                        Select::make('order_id')
                            ->label('Converted Order')
                            ->relationship('order', 'order_number')
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->disabled(fn(?CrmLead $record) => empty($record?->order_id)),

                        Placeholder::make('fulfillment_handoff')
                            ->label('Fulfillment Status & Hand-off')
                            ->visible(fn(?CrmLead $record) => !empty($record?->order_id))
                            ->content(function (?CrmLead $record) {
                                if (!$record || !$record->order) {
                                    return null;
                                }
                                $order = $record->order;
                                $carrier = $order->carrier ?: $order->courier_name ?: 'Courier';
                                $tracking = $order->tracking_number ?: 'Pending assignment';
                                $status = ucfirst(str_replace('_', ' ', $order->status));
                                $courierStatus = ucfirst(str_replace('_', ' ', $order->courier_status ?: 'Pending'));
                                $hubUrl = url('/intadmin/fulfillment-hub');
                                $orderUrl = url("/intadmin/orders/{$order->id}");

                                return new \Illuminate\Support\HtmlString("
                                    <div style='background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 0.5rem; padding: 0.875rem; font-size: 0.8125rem; color: #166534;'>
                                        <div style='display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.5rem;'>
                                            <strong>📦 Converted to Order #{$order->order_number}</strong>
                                            <span style='background: #dcfce7; padding: 0.15rem 0.5rem; border-radius: 9999px; font-weight: 700; font-size: 0.75rem;'>{$status}</span>
                                        </div>
                                        <div style='font-size: 0.75rem; color: #374151; margin-bottom: 0.75rem;'>
                                            Courier: <strong>{$carrier}</strong> | Tracking: <strong>{$tracking}</strong> | Courier Status: <strong>{$courierStatus}</strong>
                                        </div>
                                        <div style='display: flex; gap: 0.5rem;'>
                                            <a href='{$hubUrl}' target='_blank' style='display: inline-block; background: #059669; color: white; padding: 0.35rem 0.75rem; border-radius: 0.375rem; font-weight: 600; text-decoration: none; font-size: 0.75rem;'>
                                                Open Fulfillment Hub &rarr;
                                            </a>
                                            <a href='{$orderUrl}' target='_blank' style='display: inline-block; background: #ffffff; border: 1px solid #d1d5db; color: #374151; padding: 0.35rem 0.75rem; border-radius: 0.375rem; font-weight: 600; text-decoration: none; font-size: 0.75rem;'>
                                                View Order Dossier
                                            </a>
                                        </div>
                                    </div>
                                ");
                            })
                            ->columnSpanFull(),
                    ]),

                Section::make('Follow-up & Staff Ownership')
                    ->description('SLA follow-up deadlines and representative accountability.')
                    ->columns(2)
                    ->schema([
                        Select::make('assigned_staff_id')
                            ->label('Assigned Staff')
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
                            ->nullable()
                            ->placeholder('Select staff member'),

                        DateTimePicker::make('follow_up_date')
                            ->label('Next Follow-up Due')
                            ->helperText('Sets follow-up notification reminder for staff.'),

                        DatePicker::make('event_date')
                            ->label('Occasion / Event Date')
                            ->helperText('Date of client wedding, festival, or reception.'),

                        TextInput::make('lost_reason')
                            ->label('Loss Reason (if lost)')
                            ->placeholder('e.g. Budget mismatch, purchased competitor piece')
                            ->visible(fn($get) => $get('stage') === CrmLead::STAGE_LOST),

                        Textarea::make('follow_up_notes')
                            ->label('Follow-up Directives')
                            ->placeholder('e.g. Send WhatsApp swatch video on Monday morning.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Inquiry Notes & Internal Log')
                    ->schema([
                        Textarea::make('bespoke_notes')
                            ->label('Customer Order & Request Notes')
                            ->rows(3)
                            ->columnSpanFull(),

                        Textarea::make('internal_notes')
                            ->label('Internal Timeline & Directives')
                            ->rows(2)
                            ->columnSpanFull(),

                        Placeholder::make('activity_timeline')
                            ->label('Clienteling Activity Stream')
                            ->visible(fn(?CrmLead $record): bool => !empty($record?->id))
                            ->content(function (?CrmLead $record): ?\Illuminate\Support\HtmlString {
                                if (!$record) return null;
                                $activities = $record->activities()->with(['user', 'staff'])->latest()->get();
                                if ($activities->isEmpty()) {
                                    return new \Illuminate\Support\HtmlString('<div style="font-size: 0.75rem; color: #94a3b8; font-style: italic;">No activities recorded yet.</div>');
                                }

                                $html = '<div style="border-left: 2px solid #e2e8f0; margin-left: 0.25rem; padding-left: 0.75rem; display: flex; flex-direction: column; gap: 0.75rem; font-size: 0.75rem;">';
                                foreach ($activities as $act) {
                                    $time = $act->created_at->format('d M Y, h:i A');
                                    $staffName = e($act->staff?->name ?: ($act->user?->name ?: 'Staff'));
                                    $desc = nl2br(e((string)$act->description));
                                    $typeLabel = e((string)$act->type_label);
                                    $html .= "
                                        <div>
                                            <div style='display: flex; align-items: center; gap: 0.35rem; font-size: 0.6875rem; color: #64748b;'>
                                                <strong style='color: #0f172a;'>{$typeLabel}</strong>
                                                <span>•</span>
                                                <span>{$time}</span>
                                                <span>by <strong>{$staffName}</strong></span>
                                            </div>
                                            <p style='margin: 0.15rem 0 0 0; color: #334155; line-height: 1.4;'>{$desc}</p>
                                        </div>
                                    ";
                                }
                                $html .= '</div>';

                                return new \Illuminate\Support\HtmlString($html);
                            })
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn(Builder $query) => $query->with(['product', 'customer', 'assignedStaff']))
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->weight('bold')
                    ->limit(28)
                    ->description(fn(CrmLead $record) => $record->product ? "👗 {$record->product->name}" : null),

                TextColumn::make('contact_name')
                    ->label('Patron')
                    ->searchable()
                    ->description(fn(CrmLead $record) => $record->phone ?: $record->email),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->badge()
                    ->color('success')
                    ->placeholder('Unregistered')
                    ->formatStateUsing(fn($state, CrmLead $record) => $record->customer
                        ? "★ {$state} (Rs. " . number_format($record->customer->lifetime_spend) . ")"
                        : "Guest / Lead")
                    ->toggleable(),

                TextColumn::make('channel')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => CrmLead::getChannels()[$state] ?? ucfirst(str_replace('_', ' ', $state)))
                    ->color('gray'),

                TextColumn::make('stage')
                    ->badge()
                    ->formatStateUsing(fn(string $state): string => CrmLead::getStages()[$state] ?? ucfirst(str_replace('_', ' ', $state)))
                    ->color(fn(string $state): string => match ($state) {
                        'new' => 'info',
                        'contacted' => 'gray',
                        'qualified' => 'warning',
                        'quotation' => 'primary',
                        'negotiation' => 'warning',
                        'won' => 'success',
                        'lost' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('priority')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'urgent' => 'danger',
                        'high' => 'warning',
                        'medium' => 'info',
                        'low' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('estimated_value')
                    ->label('Value')
                    ->formatStateUsing(fn($state) => 'Rs. ' . number_format((float)$state, 2))
                    ->sortable(),

                TextColumn::make('follow_up_date')
                    ->label('Follow-up')
                    ->dateTime('d M, h:i A')
                    ->sortable()
                    ->badge()
                    ->color(fn(CrmLead $record) => $record->isOverdue() ? 'danger' : 'gray')
                    ->icon(fn(CrmLead $record) => $record->isOverdue() ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-calendar')
                    ->placeholder('None set'),

                TextColumn::make('assignedStaff.name')
                    ->label('Staff')
                    ->placeholder('Unassigned')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime('d M Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('stage')
                    ->options(CrmLead::getStages()),

                SelectFilter::make('priority')
                    ->options(CrmLead::getPriorities()),

                SelectFilter::make('channel')
                    ->options(CrmLead::getChannels()),

                Filter::make('overdue')
                    ->label('Overdue Follow-ups Only')
                    ->query(fn(Builder $query): Builder => $query->overdueFollowUps()),

                Filter::make('due_today')
                    ->label('Follow-ups Due Today')
                    ->query(fn(Builder $query): Builder => $query->dueTodayFollowUps()),
            ])
            ->recordActions([
                Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-m-chat-bubble-left')
                    ->color('success')
                    ->url(function (CrmLead $record): ?string {
                        app(CrmService::class)->recordWhatsAppOutreach($record);
                        return $record->whats_app_url;
                    })
                    ->openUrlInNewTab()
                    ->visible(fn(CrmLead $record): bool => !empty($record->phone)),

                Action::make('schedule_followup')
                    ->label('Follow-up')
                    ->icon('heroicon-m-calendar')
                    ->color('warning')
                    ->form([
                        DateTimePicker::make('follow_up_date')
                            ->label('Follow-up Date & Time')
                            ->required()
                            ->default(now()->addDay()),
                        Textarea::make('notes')
                            ->label('Follow-up Notes / Directive')
                            ->placeholder('e.g. Call client regarding footwear sizing.'),
                    ])
                    ->action(function (CrmLead $record, array $data) {
                        app(CrmService::class)->scheduleFollowUp(
                            $record,
                            Carbon::parse($data['follow_up_date']),
                            $data['notes'] ?? null
                        );
                        Notification::make()->title('Follow-up scheduled')->success()->send();
                    }),

                Action::make('convert_to_order')
                    ->label('To Order')
                    ->icon('heroicon-m-shopping-bag')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Convert Lead to Sales Order')
                    ->modalDescription(fn(CrmLead $record) => "Convert inquiry '{$record->title}' for customer '{$record->contact_name}' into an active order?")
                    ->form([
                        TextInput::make('total_amount')
                            ->label('Final Agreed Amount (NPR)')
                            ->numeric()
                            ->prefix('Rs.')
                            ->default(fn(CrmLead $record) => $record->estimated_value > 0 ? $record->estimated_value : 0.00)
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
                    ->action(function (CrmLead $record, array $data) {
                        $order = app(CrmService::class)->convertLeadToOrder($record, $data);
                        Notification::make()
                            ->title('Order Created Successfully!')
                            ->body("Order #{$order->order_number} is now in Fulfillment Hub.")
                            ->success()
                            ->send();
                    })
                    ->visible(fn(CrmLead $record): bool => empty($record->order_id) && $record->stage !== CrmLead::STAGE_LOST),

                Action::make('fulfillment_hub')
                    ->label('Fulfillment')
                    ->icon('heroicon-m-truck')
                    ->color('success')
                    ->url(fn(CrmLead $record) => url('/intadmin/fulfillment-hub'))
                    ->openUrlInNewTab()
                    ->visible(fn(CrmLead $record): bool => !empty($record->order_id)),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                CreateAction::make(),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCrmLeads::route('/'),
            'create' => Pages\CreateCrmLead::route('/create'),
            'edit' => Pages\EditCrmLead::route('/{record}/edit'),
        ];
    }
}
