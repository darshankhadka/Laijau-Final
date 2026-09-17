<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';
    protected static string | \UnitEnum | null $navigationGroup = 'Settings';
    protected static ?string $navigationLabel = 'System Users';
    protected static ?string $modelLabel = 'System User';
    protected static ?string $pluralModelLabel = 'System Users';
    protected static ?int $navigationSort = 30;
    protected static ?string $slug = 'users';

    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = \Filament\Facades\Filament::auth()->user()
            ?? \Illuminate\Support\Facades\Auth::guard('admin')->user()
            ?? \Illuminate\Support\Facades\Auth::guard('web')->user()
            ?? \Illuminate\Support\Facades\Auth::user();

        return $user instanceof \App\Models\User && $user->canManageUsers();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::canAccess();
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return static::canAccess();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('role', '!=', 'customer');
    }

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Group::make()->schema([
                    Section::make('Staff Credentials & Profile')
                        ->description('Identity, contact coordinates, and initial login password.')
                        ->schema([
                            TextInput::make('name')
                                ->label('Full Name')
                                ->required()
                                ->maxLength(255),

                            TextInput::make('email')
                                ->label('Email Address (Login Username)')
                                ->email()
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(255),

                            TextInput::make('phone')
                                ->label('Phone Number / Mobile')
                                ->tel()
                                ->maxLength(255),

                            TextInput::make('password')
                                ->label('Login Password')
                                ->password()
                                ->dehydrated(fn($state) => filled($state))
                                ->required(fn(string $context): bool => $context === 'create')
                                ->rule(Password::default())
                                ->placeholder(fn(string $context) => $context === 'edit' ? 'Leave blank to keep unchanged' : 'Enter strong password')
                                ->helperText('Passwords must meet corporate complexity rules (min 8 characters).'),

                            Toggle::make('is_active')
                                ->label('Account Active & Permitted to Login')
                                ->default(true)
                                ->helperText('Inactive staff accounts are immediately revoked from logging into Filament admin.'),
                        ])->columns(2),

                    Section::make('Role-Based Access Control (RBAC)')
                        ->description('Assign primary system domain role and granular Spatie permissions.')
                        ->schema([
                            Select::make('role')
                                ->label('Primary System Role')
                                ->options([
                                    'super_admin' => '👑 Super Admin (Full Root System Access)',
                                    'admin' => '🛡️ Administrator',
                                    'workspace_admin' => '🏢 Workspace Admin',
                                    'accountant' => '📊 Finance & Accounting Lead',
                                    'hr_manager' => '👥 HR & Payroll Manager',
                                    'store_manager' => '🏬 Showroom Store Manager',
                                    'warehouse_manager' => '📦 Inventory & Logistics Manager',
                                    'cashier' => '💳 Showroom POS Cashier',
                                    'support_agent' => '💬 Client Concierge & Support',
                                    'viewer' => '👁️ Auditor / Read-Only',
                                ])
                                ->default('admin')
                                ->required(),

                            Select::make('roles')
                                ->label('Security Roles (Spatie RBAC)')
                                ->relationship('roles', 'name')
                                ->multiple()
                                ->preload()
                                ->searchable()
                                ->helperText('Inherits permission policies mapped to Spatie role definitions.'),

                            Select::make('permissions')
                                ->label('Direct Granular Permissions')
                                ->relationship('permissions', 'name')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->helperText('Specific permission grants in addition to inherited role privileges.'),
                        ])->columns(1),
                ])->columnSpan(['lg' => 2]),

                Group::make()->schema([
                    Section::make('Login & Security Controls')
                        ->description('Account verification and audit tracking.')
                        ->schema([
                            DateTimePicker::make('email_verified_at')
                                ->label('Email Verified At')
                                ->helperText('Verified status allows self-service password recovery.'),

                            Placeholder::make('last_login_at')
                                ->label('Last Active Login')
                                ->content(fn(?User $record): string => $record?->last_login_at ? $record->last_login_at->diffForHumans() . " ({$record->last_login_at->format('Y-m-d H:i')})" : 'Never logged in'),

                            Placeholder::make('last_login_ip')
                                ->label('Last Known IP Address')
                                ->content(fn(?User $record): string => $record?->last_login_ip ?: 'No recorded IP'),

                            Placeholder::make('created_at')
                                ->label('Account Created')
                                ->content(fn(?User $record): string => $record?->created_at ? $record->created_at->format('M d, Y H:i') : '-'),
                        ]),

                    Section::make('Internal Administrative Notes')
                        ->schema([
                            Textarea::make('notes')
                                ->label('Staff / HR Notes')
                                ->rows(3)
                                ->placeholder('Employee ID, designated showroom branch, or workstation notes...'),
                        ]),
                ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Staff Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('email')
                    ->label('Email Address')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('phone')
                    ->label('Phone')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('role')
                    ->label('System Role')
                    ->badge()
                    ->color(fn(?string $state): string => match ($state) {
                        'super_admin', 'admin' => 'danger',
                        'workspace_admin', 'store_manager' => 'warning',
                        'accountant' => 'success',
                        'hr_manager' => 'primary',
                        'warehouse_manager' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn($state) => match ($state) {
                        'super_admin' => 'Super Admin',
                        'workspace_admin' => 'Workspace Admin',
                        'store_manager' => 'Store Manager',
                        'warehouse_manager' => 'Warehouse Mgr',
                        'hr_manager' => 'HR Manager',
                        'support_agent' => 'Support Agent',
                        default => ucfirst((string)$state),
                    }),

                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Security Roles')
                    ->badge()
                    ->color('primary')
                    ->separator(', ')
                    ->limitList(2),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->alignCenter()
                    ->tooltip(fn($record) => $record->is_active ? 'Active staff member' : 'Deactivated account (Blocked from login)'),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Active')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->placeholder('Never')
                    ->toggleable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->date('Y-m-d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('System Role')
                    ->options([
                        'super_admin' => 'Super Admin',
                        'admin' => 'Administrator',
                        'workspace_admin' => 'Workspace Admin',
                        'accountant' => 'Finance & Accountant',
                        'hr_manager' => 'HR & Payroll Manager',
                        'store_manager' => 'Store Manager',
                        'warehouse_manager' => 'Warehouse Manager',
                        'cashier' => 'Cashier',
                        'support_agent' => 'Support Agent',
                        'viewer' => 'Viewer',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Account Status')
                    ->placeholder('All Accounts')
                    ->trueLabel('Active Staff Only')
                    ->falseLabel('Deactivated Staff Only'),

                Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->label('Spatie Security Role'),
            ])
            ->actions([
                // Quick Toggle Activation
                \Filament\Actions\Action::make('toggle_status')
                    ->label(fn(User $record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn(User $record) => $record->is_active ? 'heroicon-o-no-symbol' : 'heroicon-o-check-circle')
                    ->color(fn(User $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn(User $record) => $record->is_active ? "Deactivate {$record->name}" : "Activate {$record->name}")
                    ->modalDescription(fn(User $record) => $record->is_active
                        ? 'Deactivating this user will immediately revoke their access and block them from logging into Filament admin.'
                        : 'Activating will restore login privileges and allow this staff member to access their assigned modules.')
                    ->disabled(fn(User $record) => (int) \Illuminate\Support\Facades\Auth::id() === (int) $record->id)
                    ->action(function (User $record) {
                        $newStatus = !$record->is_active;
                        $record->update(['is_active' => $newStatus]);

                        Notification::make()
                            ->title($newStatus ? 'Account Activated' : 'Account Deactivated')
                            ->body("User {$record->name} is now " . ($newStatus ? 'active' : 'deactivated') . '.')
                            ->success()
                            ->send();
                    }),

                // Quick Password Reset
                \Filament\Actions\Action::make('reset_password')
                    ->label('Reset Password')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->form([
                        TextInput::make('new_password')
                            ->label('New Password')
                            ->password()
                            ->required()
                            ->rule(Password::default()),
                    ])
                    ->action(function (User $record, array $data) {
                        $record->update([
                            'password' => $data['new_password'],
                        ]);

                        Notification::make()
                            ->title('Password Reset Successful')
                            ->body("Password for {$record->name} has been updated.")
                            ->success()
                            ->send();
                    }),

                \Filament\Actions\EditAction::make(),
                \Filament\Actions\DeleteAction::make()
                    ->disabled(fn(User $record) => (int) \Illuminate\Support\Facades\Auth::id() === (int) $record->id),
            ])
            ->bulkActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\BulkAction::make('bulk_deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(fn($records) => $records->each(fn($r) => $r->update(['is_active' => false]))),

                    \Filament\Actions\BulkAction::make('bulk_activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(fn($records) => $records->each(fn($r) => $r->update(['is_active' => true]))),

                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No System Users Found')
            ->emptyStateDescription('Create staff and administrator accounts with role-based access control (RBAC).')
            ->emptyStateIcon('heroicon-o-shield-check')
            ->emptyStateActions([
                \Filament\Actions\Action::make('create_user')
                    ->label('Create System User')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->url(fn() => static::getUrl('create')),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
