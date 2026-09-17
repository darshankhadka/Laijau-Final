<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Inventory\StockReservation;
use App\Models\Order;
use App\Models\PaymentAuditLog;
use App\Services\Inventory\InventoryService;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';
    protected static string | \UnitEnum | null $navigationGroup = 'Sales';
    protected static ?string $navigationLabel = 'Orders';
    protected static ?int $navigationSort = 15;

    public static function getGloballySearchableAttributes(): array
    {
        return ['order_number', 'first_name', 'last_name', 'phone', 'alt_phone', 'email', 'tracking_number'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return 'Order #' . $record->order_number . ' - ' . $record->customer_full_name;
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Customer' => $record->customer_full_name,
            'Phone' => $record->phone,
            'Total' => 'Rs. ' . number_format((float)$record->total_amount, 2),
            'Status' => ucfirst($record->status ?? 'pending'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['user', 'items'])
            ->withCount('items');
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Customer & Nepal Delivery Destination')
                    ->schema([
                        TextInput::make('order_number')->disabled()->label('Order Reference'),
                        Select::make('channel')
                            ->label('Sales Channel')
                            ->options(Order::getChannels())
                            ->default(Order::CHANNEL_ONLINE)
                            ->required(),
                        TextInput::make('first_name')->label('First Name')->required(),
                        TextInput::make('last_name')->label('Last Name'),
                        TextInput::make('phone')->label('Mobile Phone (+977)')->required(),
                        TextInput::make('alt_phone')->label('Alternate Phone'),
                        TextInput::make('email')->label('Email Address')->email(),
                        TextInput::make('province')->label('Province'),
                        TextInput::make('district')->label('District'),
                        TextInput::make('municipality')->label('Municipality / Nagarpalika'),
                        TextInput::make('ward')->label('Ward No.'),
                        TextInput::make('tole')->label('Tole / Street Address'),
                        TextInput::make('landmark')->label('Nearby Landmark'),
                        Toggle::make('is_inside_valley')->label('Kathmandu Valley Zone'),
                        Textarea::make('customer_notes')->label('Customer Special Instructions')->columnSpanFull(),
                    ])->columns(3),

                Section::make('Payment & Financials (Nepal)')
                    ->schema([
                        TextInput::make('subtotal')->disabled()->numeric()->prefix('Rs. '),
                        TextInput::make('shipping_fee')->disabled()->numeric()->prefix('Rs. '),
                        TextInput::make('coupon_discount')->disabled()->numeric()->prefix('Rs. '),
                        TextInput::make('vat_amount')->disabled()->label('13% VAT')->numeric()->prefix('Rs. '),
                        TextInput::make('total_amount')->disabled()->numeric()->prefix('Rs. '),
                        TextInput::make('currency')->disabled()->default('NPR'),
                        TextInput::make('payment_method')->disabled()->label('Payment Method'),
                        Select::make('payment_status')
                            ->options([
                                'unpaid' => 'Unpaid (COD / Pending)',
                                'payment_verification_pending' => 'Verification Pending',
                                'paid' => 'Paid (Verified)',
                                'failed' => 'Failed',
                                'refunded' => 'Refunded',
                            ])
                            ->required(),
                        TextInput::make('payment_reference')->label('Payment / Transaction Code')->disabled(),
                        TextInput::make('connectips_txnid')->label('ConnectIPS TXN ID')->disabled(),
                        Textarea::make('payment_notes')
                            ->label('Payment Verification Notes')
                            ->columnSpanFull(),
                    ])->columns(3),

                Section::make('Fulfillment & Courier Routing')
                    ->schema([
                        Select::make('status')
                            ->options(Order::getStatuses())
                            ->required(),
                        Select::make('carrier')
                            ->label('Courier Partner')
                            ->options([
                                'Laijau Express' => 'Laijau Express (Kathmandu Valley)',
                                'Nepal Can Move (NCM)' => 'Nepal Can Move (NCM)',
                                'Pathao Parcel' => 'Pathao Parcel',
                                'Sundar Courier' => 'Sundar Courier',
                                'Other Courier' => 'Other Courier',
                            ]),
                        TextInput::make('tracking_number')
                            ->label('Tracking / AWB #')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function ($state, $set, $get) {
                                if ($state && empty($get('tracking_url'))) {
                                    $url = Order::resolveTrackingUrl($get('carrier'), $state);
                                    if ($url) {
                                        $set('tracking_url', $url);
                                    }
                                }
                            }),
                        TextInput::make('tracking_url')
                            ->label('Courier Tracking URL')
                            ->url()
                            ->columnSpanFull(),
                        Textarea::make('internal_notes')
                            ->label('Internal Dispatch & Quality Notes')
                            ->columnSpanFull(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // 1. Order Number
                Tables\Columns\TextColumn::make('order_number')
                    ->label('Order #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->fontFamily('mono')
                    ->color('primary')
                    ->url(fn(Order $record) => Pages\ViewOrder::getUrl(['record' => $record->id])),

                // 2. Customer Identity (Name + Phone chip)
                Tables\Columns\TextColumn::make('customer')
                    ->label('Customer')
                    ->getStateUsing(fn(Order $record) => $record->customer_full_name ?: 'Retail Customer')
                    ->description(fn(Order $record) => $record->phone ? $record->phone : ($record->email ?: '—'))
                    ->searchable(['first_name', 'last_name', 'phone', 'email'])
                    ->url(function (Order $record) {
                        return $record->user_id
                            ? route('filament.admin.resources.customers.view', ['record' => $record->user_id])
                            : null;
                    })
                    ->color(fn(Order $record) => $record->user_id ? 'primary' : 'gray'),

                // 3. Channel
                Tables\Columns\TextColumn::make('channel')
                    ->label('Channel')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'pos' => 'warning',
                        'manual' => 'info',
                        default => 'primary',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'pos' => 'POS',
                        'manual' => 'Manual',
                        default => 'Online',
                    }),

                // 4. Items Count
                Tables\Columns\TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),

                // 5. Total NPR
                Tables\Columns\TextColumn::make('total_amount')
                    ->label('Total')
                    ->formatStateUsing(fn($state) => \App\Helpers\NepaliNumberHelper::formatCurrency((float)$state, 'Rs. ', 0))
                    ->weight('bold')
                    ->sortable()
                    ->alignEnd(),

                // 6. Payment Status
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('Payment')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'paid' => 'success',
                        'payment_verification_pending' => 'warning',
                        'failed', 'refunded' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(function (string $state, Order $record): string {
                        if ($state === 'unpaid' && $record->payment_method === 'cod') {
                            return 'COD (Pending)';
                        }
                        return match ($state) {
                            'paid' => 'Paid',
                            'payment_verification_pending' => 'Verifying',
                            'failed' => 'Failed',
                            'refunded' => 'Refunded',
                            default => ucfirst($state),
                        };
                    }),

                // 7. Fulfillment Status
                Tables\Columns\TextColumn::make('status')
                    ->label('Fulfillment')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        Order::STATUS_PENDING, 'payment_pending' => 'warning',
                        Order::STATUS_PAYMENT_VERIFIED, Order::STATUS_CUSTOMER_CONFIRMED => 'info',
                        Order::STATUS_PROCESSING => 'primary',
                        Order::STATUS_PACKING, Order::STATUS_READY_FOR_DELIVERY => 'indigo',
                        Order::STATUS_HANDED_TO_COURIER, Order::STATUS_IN_TRANSIT => 'sky',
                        Order::STATUS_DELIVERED => 'success',
                        Order::STATUS_CANCELLED, 'failed_delivery' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(function (string $state): string {
                        return match ($state) {
                            Order::STATUS_PENDING => 'New',
                            Order::STATUS_PROCESSING => 'Processing',
                            Order::STATUS_PACKING => 'Packed',
                            Order::STATUS_READY_FOR_DELIVERY => 'Ready',
                            Order::STATUS_HANDED_TO_COURIER => 'Dispatched',
                            Order::STATUS_IN_TRANSIT => 'In Transit',
                            Order::STATUS_DELIVERED => 'Completed',
                            Order::STATUS_CANCELLED => 'Cancelled',
                            default => ucfirst(str_replace('_', ' ', $state)),
                        };
                    }),

                // 8. Delivery Location / Courier
                Tables\Columns\TextColumn::make('district')
                    ->label('Delivery')
                    ->badge()
                    ->color(fn(Order $record) => $record->is_inside_valley ? 'success' : 'info')
                    ->formatStateUsing(fn(Order $record) => $record->is_inside_valley ? 'Valley' : ($record->district ?: 'Outside')),

                // 9. Order Date
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('M d, H:i')
                    ->timezone('Asia/Kathmandu')
                    ->sortable()
                    ->color('gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                // Status Filter
                Tables\Filters\SelectFilter::make('status')
                    ->label('Fulfillment Status')
                    ->options([
                        Order::STATUS_PENDING => 'New / Pending Review',
                        Order::STATUS_PROCESSING => 'Processing',
                        Order::STATUS_PACKING => 'Packed & Boxed',
                        Order::STATUS_READY_FOR_DELIVERY => 'Ready for Delivery',
                        Order::STATUS_HANDED_TO_COURIER => 'Handed to Courier',
                        Order::STATUS_IN_TRANSIT => 'In Transit (Nepal)',
                        Order::STATUS_DELIVERED => 'Completed / Delivered',
                        Order::STATUS_CANCELLED => 'Cancelled',
                    ]),

                // Payment Status Filter
                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('Payment Status')
                    ->options([
                        'paid' => 'Paid (Verified)',
                        'payment_verification_pending' => 'Verification Pending',
                        'unpaid' => 'Unpaid (COD / Pending)',
                        'failed' => 'Failed',
                        'refunded' => 'Refunded',
                    ]),

                // Sales Channel Filter
                Tables\Filters\SelectFilter::make('channel')
                    ->label('Sales Channel')
                    ->options(Order::getChannels()),

                // Delivery Zone Filter
                Tables\Filters\TernaryFilter::make('is_inside_valley')
                    ->label('Delivery Zone')
                    ->trueLabel('Kathmandu Valley Only')
                    ->falseLabel('Outside Valley (Nationwide)'),

                // Order Amount Range Filter
                Tables\Filters\Filter::make('amount_range')
                    ->label('Order Amount')
                    ->form([
                        Select::make('tier')
                            ->label('Amount Range')
                            ->options([
                                'under_5k' => 'Under NPR 5,000',
                                '5k_10k' => 'NPR 5,000 – 10,000',
                                '10k_25k' => 'NPR 10,000 – 25,000',
                                'above_25k' => 'NPR 25,000+',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['tier'] ?? null) {
                            'under_5k' => $query->where('total_amount', '<', 5000),
                            '5k_10k' => $query->whereBetween('total_amount', [5000, 10000]),
                            '10k_25k' => $query->whereBetween('total_amount', [10000, 25000]),
                            'above_25k' => $query->where('total_amount', '>=', 25000),
                            default => $query,
                        };
                    }),

                // Date Filter
                Tables\Filters\Filter::make('order_date')
                    ->label('Order Date')
                    ->form([
                        Select::make('period')
                            ->label('Time Period')
                            ->options([
                                'all_time' => 'All Time',
                                'today' => 'Today',
                                'yesterday' => 'Yesterday',
                                'this_week' => 'This Week',
                                'this_month' => 'This Month',
                                'last_month' => 'Last Month',
                                'jan_2026' => 'January 2026',
                                'feb_2026' => 'February 2026',
                                'mar_2026' => 'March 2026',
                                'apr_2026' => 'April 2026',
                                'may_2026' => 'May 2026',
                                'jun_2026' => 'June 2026',
                                'jul_2026' => 'July 2026',
                                'aug_2026' => 'August 2026',
                                'sep_2026' => 'September 2026',
                                'custom' => 'Custom Range',
                            ])
                            ->default('all_time')
                            ->live(),
                        Forms\Components\DatePicker::make('from_date')
                            ->label('From Date')
                            ->visible(fn($get) => $get('period') === 'custom'),
                        Forms\Components\DatePicker::make('to_date')
                            ->label('To Date')
                            ->visible(fn($get) => $get('period') === 'custom'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['period'] ?? null) {
                            'today' => $query->whereDate('created_at', Carbon::today()),
                            'yesterday' => $query->whereDate('created_at', Carbon::yesterday()),
                            'this_week' => $query->whereBetween('created_at', [Carbon::now()->startOfWeek(), Carbon::now()->endOfWeek()]),
                            'this_month' => $query->whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()]),
                            'last_month' => $query->whereBetween('created_at', [Carbon::now()->subMonth()->startOfMonth(), Carbon::now()->subMonth()->endOfMonth()]),
                            'jan_2026' => $query->whereBetween('created_at', ['2026-01-01 00:00:00', '2026-01-31 23:59:59']),
                            'feb_2026' => $query->whereBetween('created_at', ['2026-02-01 00:00:00', '2026-02-28 23:59:59']),
                            'mar_2026' => $query->whereBetween('created_at', ['2026-03-01 00:00:00', '2026-03-31 23:59:59']),
                            'apr_2026' => $query->whereBetween('created_at', ['2026-04-01 00:00:00', '2026-04-30 23:59:59']),
                            'may_2026' => $query->whereBetween('created_at', ['2026-05-01 00:00:00', '2026-05-31 23:59:59']),
                            'jun_2026' => $query->whereBetween('created_at', ['2026-06-01 00:00:00', '2026-06-30 23:59:59']),
                            'jul_2026' => $query->whereBetween('created_at', ['2026-07-01 00:00:00', '2026-07-31 23:59:59']),
                            'aug_2026' => $query->whereBetween('created_at', ['2026-08-01 00:00:00', '2026-08-31 23:59:59']),
                            'sep_2026' => $query->whereBetween('created_at', ['2026-09-01 00:00:00', '2026-09-30 23:59:59']),
                            'custom' => $query
                                ->when($data['from_date'] ?? null, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
                                ->when($data['to_date'] ?? null, fn($q, $d) => $q->whereDate('created_at', '<=', $d)),
                            default => $query,
                        };
                    }),
            ])
            ->emptyStateHeading('No Orders Found')
            ->emptyStateDescription('No retail orders matched the selected filters or search criteria.')
            ->emptyStateIcon('heroicon-o-shopping-bag')
            ->emptyStateActions([
                \Filament\Actions\Action::make('clear_filters')
                    ->label('Reset Filters')
                    ->url(fn() => Pages\ListOrders::getUrl()),
            ])
            ->recordActions([
                \Filament\Actions\ViewAction::make()->label('View'),

                \Filament\Actions\Action::make('print_receipt')
                    ->label('Receipt')
                    ->icon('heroicon-m-printer')
                    ->color('primary')
                    ->url(fn(Order $record) => route('order.pos_receipt', ['order' => $record->id]))
                    ->openUrlInNewTab(),

                \Filament\Actions\ActionGroup::make([
                    \Filament\Actions\EditAction::make(),

                    // Push to Fulfillment Queue
                    \Filament\Actions\Action::make('push_to_fulfillment')
                        ->label('Push to Fulfillment')
                        ->icon('heroicon-m-arrow-right-circle')
                        ->color('warning')
                        ->visible(fn(Order $record) => in_array($record->status, [Order::STATUS_PENDING, Order::STATUS_CUSTOMER_CONFIRMED, Order::STATUS_PAYMENT_VERIFIED]))
                        ->requiresConfirmation()
                        ->action(function (Order $record): void {
                            $record->update(['status' => Order::STATUS_PROCESSING]);
                            \App\Models\LogisticsEvent::create([
                                'order_id' => $record->id,
                                'provider' => 'internal',
                                'external_order_id' => (string)$record->order_number,
                                'event' => 'pushed_to_fulfillment',
                                'status' => 'Processing',
                                'payload' => ['pushed_by' => auth()->user()?->name ?? 'Staff', 'timestamp' => now()->toIso8601String()],
                                'processing_status' => 'processed',
                                'idempotency_key' => 'push_fulfill_' . $record->id . '_' . time(),
                                'received_at' => now(),
                                'processed_at' => now(),
                            ]);
                            Notification::make()->title("Order #{$record->order_number} pushed to Fulfillment Queue.")->success()->send();
                        }),

                    // Fast Status Advancement: Mark Processing
                    \Filament\Actions\Action::make('mark_processing')
                        ->label('Mark Processing')
                        ->icon('heroicon-m-arrow-path')
                        ->color('primary')
                        ->visible(fn(Order $record) => in_array($record->status, [Order::STATUS_PENDING, Order::STATUS_CUSTOMER_CONFIRMED, Order::STATUS_PAYMENT_VERIFIED]))
                        ->action(function (Order $record): void {
                            $record->update(['status' => Order::STATUS_PROCESSING]);
                            Notification::make()->title('Order # ' . $record->order_number . ' is now Processing.')->info()->send();
                        }),

                    // Fast Status Advancement: Mark Packed
                    \Filament\Actions\Action::make('mark_packed')
                        ->label('Mark Packed')
                        ->icon('heroicon-m-cube')
                        ->color('indigo')
                        ->visible(fn(Order $record) => in_array($record->status, [Order::STATUS_PROCESSING]))
                        ->action(function (Order $record): void {
                            $record->update(['status' => Order::STATUS_PACKING]);
                            Notification::make()->title('Order # ' . $record->order_number . ' boxed and Packed.')->info()->send();
                        }),

                    // Fast Status Advancement: Mark Completed / Delivered
                    \Filament\Actions\Action::make('mark_completed')
                        ->label('Mark Completed')
                        ->icon('heroicon-m-check-circle')
                        ->color('success')
                        ->visible(fn(Order $record) => in_array($record->status, [Order::STATUS_HANDED_TO_COURIER, Order::STATUS_IN_TRANSIT, Order::STATUS_READY_FOR_DELIVERY]))
                        ->action(function (Order $record): void {
                            $record->update([
                                'status' => Order::STATUS_DELIVERED,
                                'delivered_at' => now(),
                                'payment_status' => $record->payment_method === 'cod' ? 'paid' : $record->payment_status,
                            ]);
                            Notification::make()->title('Order # ' . $record->order_number . ' marked as Completed / Delivered.')->success()->send();
                        }),

                    // Print Tax Invoice
                    \Filament\Actions\Action::make('print_invoice')
                        ->label('Print Tax Invoice')
                        ->icon('heroicon-o-document-text')
                        ->color('gray')
                        ->url(fn(Order $record) => route('admin.orders.invoice', $record))
                        ->openUrlInNewTab(),

                    // Print Packing Slip
                    \Filament\Actions\Action::make('print_packing_slip')
                        ->label('Print Packing Slip')
                        ->icon('heroicon-o-clipboard-document-check')
                        ->color('gray')
                        ->url(fn(Order $record) => route('admin.orders.packing_slip', $record))
                        ->openUrlInNewTab(),

                    // WhatsApp Customer
                    \Filament\Actions\Action::make('whatsapp_customer')
                        ->label('Chat on WhatsApp')
                        ->icon('heroicon-o-chat-bubble-left-ellipsis')
                        ->color('success')
                        ->visible(fn(Order $record) => !empty($record->phone))
                        ->url(function (Order $record): string {
                            $cleanPhone = preg_replace('/[^0-9]/', '', (string)$record->phone);
                            if (strlen($cleanPhone) === 10) $cleanPhone = '977' . $cleanPhone;
                            $name = $record->first_name ?: 'Customer';
                            $msg = "Namaste {$name}! Laijau Support here regarding your Order #{$record->order_number}.";
                            return "https://wa.me/{$cleanPhone}?text=" . urlencode($msg);
                        })
                        ->openUrlInNewTab(),

                    // View Customer Profile Link
                    \Filament\Actions\Action::make('view_customer')
                        ->label('View Customer Profile')
                        ->icon('heroicon-o-user')
                        ->color('primary')
                        ->visible(fn(Order $record) => !empty($record->user_id))
                        ->url(fn(Order $record) => route('filament.admin.resources.customers.view', ['record' => $record->user_id])),

                    // Safe Cancellation Action (Strict confirmation + reason)
                    \Filament\Actions\Action::make('cancel_order')
                        ->label('Cancel Order')
                        ->icon('heroicon-m-x-circle')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Cancel Retail Order')
                        ->modalDescription('Specify the reason for order cancellation. Any active inventory reservations will be released safely.')
                        ->form([
                            Textarea::make('cancellation_reason')
                                ->label('Cancellation Reason')
                                ->placeholder('Customer request, out of stock, duplicate order...')
                                ->required(),
                        ])
                        ->visible(fn(Order $record) => !in_array($record->status, [Order::STATUS_DELIVERED, Order::STATUS_CANCELLED]))
                        ->action(function (array $data, Order $record): void {
                            $record->update([
                                'status' => Order::STATUS_CANCELLED,
                                'cancelled_at' => now(),
                                'cancellation_reason' => $data['cancellation_reason'],
                            ]);

                            $reservations = StockReservation::where('reference_type', 'online_order')
                                ->where('reference_id', $record->id)
                                ->where('status', 'active')
                                ->get();

                            $invService = app(InventoryService::class);
                            foreach ($reservations as $r) {
                                $invService->releaseReservation($r, 'cancelled');
                            }

                            Notification::make()->title('Order cancelled and reserved stock released.')->danger()->send();
                        }),
                ]),
            ])
            ->headerActions([
                // Safe CSV Export Action (Non-destructive)
                \Filament\Actions\Action::make('export_csv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function () {
                        return response()->streamDownload(function () {
                            $handle = fopen('php://output', 'w');
                            fputcsv($handle, [
                                'Order #',
                                'Date',
                                'Customer Name',
                                'Phone',
                                'Email',
                                'Channel',
                                'Payment Method',
                                'Payment Status',
                                'Fulfillment Status',
                                'Subtotal',
                                'Discount',
                                'Shipping',
                                'Total Amount (NPR)',
                            ]);

                            Order::with(['items'])->orderBy('id', 'desc')->chunk(100, function ($orders) use ($handle) {
                                foreach ($orders as $order) {
                                    fputcsv($handle, [
                                        $order->order_number,
                                        $order->created_at->format('Y-m-d H:i:s'),
                                        $order->customer_full_name,
                                        $order->phone,
                                        $order->email,
                                        $order->channel,
                                        $order->payment_method,
                                        $order->payment_status,
                                        $order->status,
                                        $order->subtotal,
                                        $order->coupon_discount,
                                        $order->shipping_fee,
                                        $order->total_amount,
                                    ]);
                                }
                            });

                            fclose($handle);
                        }, 'laijau_orders_' . date('Y-m-d') . '.csv', [
                            'Content-Type' => 'text/csv',
                        ]);
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
            RelationManagers\LogisticsEventsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
            'view' => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
