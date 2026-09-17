<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\Order;
use App\Models\User;
use App\Services\Customer\CustomerService;
use Filament\Forms;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class CustomerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';
    protected static string | \UnitEnum | null $navigationGroup = 'Sales';
    protected static ?string $navigationLabel = 'Customers';
    protected static ?string $modelLabel = 'Customer';
    protected static ?string $pluralModelLabel = 'Customers';
    protected static ?string $slug = 'customers';
    protected static ?int $navigationSort = 20;

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email', 'phone'];
    }

    public static function getGlobalSearchResultTitle(\Illuminate\Database\Eloquent\Model $record): string
    {
        return $record->name . ($record->phone ? ' (' . $record->phone . ')' : '');
    }

    public static function getGlobalSearchResultDetails(\Illuminate\Database\Eloquent\Model $record): array
    {
        return [
            'Email' => $record->email ?? '—',
            'Phone' => $record->phone ?? '—',
            'Segment' => $record->customer_segment,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('role', 'customer')
            ->withCount([
                'orders as online_orders_count' => fn($q) => $q->whereNotIn('status', [Order::STATUS_CANCELLED, 'failed_delivery']),
                'offlineSales as pos_sales_count' => fn($q) => $q->where('status', 'completed'),
            ])
            ->withSum([
                'orders as online_spend_sum' => fn($q) => $q->whereNotIn('status', [Order::STATUS_CANCELLED, 'failed_delivery']),
            ], 'total_amount')
            ->withSum([
                'offlineSales as pos_spend_sum' => fn($q) => $q->where('status', 'completed'),
            ], 'total_amount')
            ->with(['orders' => fn($q) => $q->latest('created_at')->limit(1), 'offlineSales' => fn($q) => $q->latest('sold_at')->limit(1)]);
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Group::make()->schema([
                    Section::make('Customer Profile')
                        ->schema([
                            TextInput::make('name')->required()->maxLength(255)->label('Full Name'),
                            TextInput::make('phone')->tel()->maxLength(255)->label('Mobile Phone (+977)'),
                            TextInput::make('email')->email()->required()->maxLength(255)->label('Email Address'),
                            TextInput::make('password')
                                ->password()
                                ->dehydrated(fn($state) => filled($state))
                                ->required(fn(string $context): bool => $context === 'create')
                                ->maxLength(255),
                        ])->columns(2),

                    Section::make('Delivery Location & Addresses')
                        ->schema([
                            TextInput::make('province')->label('Province'),
                            TextInput::make('district')->label('District'),
                            TextInput::make('municipality')->label('Municipality / Nagarpalika'),
                            TextInput::make('ward')->label('Ward No.'),
                            TextInput::make('tole')->label('Tole / Street Address'),
                            TextInput::make('address')->label('Full Shipping Address')->columnSpanFull(),
                        ])->columns(3),
                ])->columnSpan(['lg' => 2]),

                Group::make()->schema([
                    Section::make('Internal Staff Notes')
                        ->schema([
                            Textarea::make('notes')
                                ->label('Internal Operational Notes')
                                ->placeholder('Special customer preferences, delivery alerts, sizing notes...')
                                ->rows(6),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // 1. Customer Identity
                Tables\Columns\TextColumn::make('name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->description(fn(User $record) => $record->email ?: 'No email')
                    ->url(fn(User $record) => Pages\ViewCustomer::getUrl(['record' => $record->id])),

                // 2. Phone with WhatsApp link
                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->formatStateUsing(fn($state) => $state ?: '—')
                    ->fontFamily('mono'),

                // 3. Segment Badge
                Tables\Columns\TextColumn::make('customer_segment')
                    ->label('Segment')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'VIP' => 'warning',
                        'Returning' => 'info',
                        'New' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'VIP' => '★ VIP',
                        default => $state,
                    }),

                // 4. Combined Orders Count
                Tables\Columns\TextColumn::make('total_orders')
                    ->label('Orders')
                    ->getStateUsing(function (User $record): int {
                        $online = (int) ($record->online_orders_count ?? 0);
                        $pos = (int) ($record->pos_sales_count ?? 0);
                        return $online + $pos;
                    })
                    ->badge()
                    ->color('primary')
                    ->alignCenter(),

                // 5. Lifetime Spend (NPR)
                Tables\Columns\TextColumn::make('lifetime_spend')
                    ->label('Lifetime Spend')
                    ->getStateUsing(function (User $record): float {
                        $online = (float) ($record->online_spend_sum ?? 0);
                        $pos = (float) ($record->pos_spend_sum ?? 0);
                        return round($online + $pos, 2);
                    })
                    ->formatStateUsing(fn($state) => 'Rs. ' . number_format((float)$state, 0))
                    ->weight('bold')
                    ->alignEnd(),

                // 6. Average Order Value
                Tables\Columns\TextColumn::make('average_order')
                    ->label('AOV')
                    ->getStateUsing(function (User $record): float {
                        $count = (int)($record->online_orders_count ?? 0) + (int)($record->pos_sales_count ?? 0);
                        $spend = (float)($record->online_spend_sum ?? 0) + (float)($record->pos_spend_sum ?? 0);
                        return $count > 0 ? round($spend / $count, 0) : 0.0;
                    })
                    ->formatStateUsing(fn($state) => 'Rs. ' . number_format((float)$state, 0))
                    ->color('gray')
                    ->alignEnd(),

                // 7. Last Order Date
                Tables\Columns\TextColumn::make('last_order_date')
                    ->label('Last Order')
                    ->getStateUsing(fn(User $record) => $record->last_order_date)
                    ->formatStateUsing(fn($state) => $state ? $state->format('M d, Y') : '—')
                    ->color('gray'),

                // 8. Member Since
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Member Since')
                    ->date('M Y')
                    ->sortable()
                    ->color('gray'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                // Customer Segment Filter
                Tables\Filters\SelectFilter::make('segment')
                    ->label('Customer Segment')
                    ->options([
                        'vip' => '★ VIP Customers (Spend >= 50k or Orders >= 5)',
                        'returning' => 'Returning Customers (>1 Order)',
                        'new' => 'New Customers (First Order)',
                        'inactive' => 'Inactive Customers (No orders >90 days)',
                        'no_orders' => 'Registered with No Orders',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'vip' => $query->where(function ($q) {
                                $q->has('orders', '>=', 5)
                                    ->orWhereHas('orders', fn($sub) => $sub->whereRaw('total_amount >= 50000'));
                            }),
                            'returning' => $query->has('orders', '>', 1),
                            'new' => $query->has('orders', '=', 1),
                            'no_orders' => $query->doesntHave('orders')->doesntHave('offlineSales'),
                            default => $query,
                        };
                    }),

                // Order Count Tier Filter
                Tables\Filters\SelectFilter::make('orders_tier')
                    ->label('Order Count')
                    ->options([
                        '1' => '1 Order',
                        '2_5' => '2 – 5 Orders',
                        '6_10' => '6 – 10 Orders',
                        '10_plus' => '10+ Orders',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            '1' => $query->has('orders', '=', 1),
                            '2_5' => $query->has('orders', '>=', 2)->has('orders', '<=', 5),
                            '6_10' => $query->has('orders', '>=', 6)->has('orders', '<=', 10),
                            '10_plus' => $query->has('orders', '>', 10),
                            default => $query,
                        };
                    }),
            ])
            ->header(view('filament.components.crm-nav', ['active' => 'customers']))
            ->emptyStateHeading('No Customers Found')
            ->emptyStateDescription('No retail customers matched the specified search or filter criteria.')
            ->emptyStateIcon('heroicon-o-users')
            ->recordActions([
                \Filament\Actions\ViewAction::make()->label('View Dossier'),

                \Filament\Actions\Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('success')
                    ->visible(fn(User $record): bool => !empty($record->phone))
                    ->url(function (User $record): ?string {
                        $clean = preg_replace('/[^0-9]/', '', (string)$record->phone);
                        if (strlen($clean) === 10) $clean = '977' . $clean;
                        return "https://wa.me/{$clean}?text=" . urlencode("Namaste {$record->name}! Laijau Customer Care here.");
                    })
                    ->openUrlInNewTab(),

                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make()
                    ->before(function (\Filament\Actions\DeleteAction $action, User $record) {
                        if ($record->orders()->count() > 0 || $record->offlineSales()->count() > 0) {
                            Notification::make()
                                ->title('Cannot Delete Customer')
                                ->body("Customer '{$record->name}' has existing historical sales/orders. Deleting them would violate audit trail requirements.")
                                ->danger()
                                ->send();
                            $action->halt();
                        }
                    }),
            ])
            ->headerActions([
                \Filament\Actions\Action::make('export_customers_csv')
                    ->label('Export CSV')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function () {
                        return response()->streamDownload(function () {
                            $handle = fopen('php://output', 'w');
                            fputcsv($handle, [
                                'Customer ID',
                                'Name',
                                'Phone',
                                'Email',
                                'District',
                                'Segment',
                                'Lifetime Orders',
                                'Lifetime Spend (NPR)',
                                'Registered Date',
                            ]);

                            $service = app(CustomerService::class);
                            User::where('role', 'customer')->orderBy('id', 'desc')->chunk(100, function ($customers) use ($handle, $service) {
                                foreach ($customers as $cust) {
                                    $metrics = $service->getCustomerMetrics($cust);
                                    fputcsv($handle, [
                                        $cust->id,
                                        $cust->name,
                                        $cust->phone,
                                        $cust->email,
                                        $cust->district,
                                        $metrics['segment'],
                                        $metrics['total_orders'],
                                        $metrics['lifetime_spend'],
                                        $cust->created_at ? $cust->created_at->format('Y-m-d') : '',
                                    ]);
                                }
                            });

                            fclose($handle);
                        }, 'laijau_customers_' . date('Y-m-d') . '.csv', [
                            'Content-Type' => 'text/csv',
                        ]);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'create' => Pages\CreateCustomer::route('/create'),
            'view' => Pages\ViewCustomer::route('/{record}'),
            'edit' => Pages\EditCustomer::route('/{record}/edit'),
        ];
    }
}
