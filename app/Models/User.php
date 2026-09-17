<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'alt_phone',
        'whatsapp_phone',
        'address',
        'city',
        'postal_code',
        'country',
        'province',
        'district',
        'municipality',
        'ward',
        'tole',
        'landmark',
        'password',
        'role',
        'is_active',
        'last_login_at',
        'last_login_ip',
        'gdpr_consent',
        'gdpr_consented_at',
        'saved_measurements',
        'saved_addresses',
        'google_id',
        'avatar',
        'notes',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'provider_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'gdpr_consent' => 'boolean',
            'gdpr_consented_at' => 'datetime',
            'saved_measurements' => 'array',
            'saved_addresses' => 'array',
        ];
    }

    /**
     * Return country code, defaulting to NP (Nepal).
     */
    public function getCountryAttribute(mixed $value): string
    {
        return \App\Services\TaxCalculatorService::normalizeCountryCode($value ?? 'NP');
    }

    /**
     * Store country code, defaulting to NP (Nepal).
     */
    public function setCountryAttribute(mixed $value): void
    {
        $this->attributes['country'] = \App\Services\TaxCalculatorService::normalizeCountryCode($value ?? 'NP');
    }

    /**
     * Normalize email to lowercase and trimmed string.
     */
    public function setEmailAttribute(mixed $value): void
    {
        $this->attributes['email'] = strtolower(trim((string)$value));
    }

    public function canAccessPanel(Panel $panel): bool
    {
        // Deactivated users are strictly blocked from panel access
        if ($this->is_active === false) {
            return false;
        }

        // Public customers are restricted to storefront
        if ($this->role === 'customer') {
            return false;
        }

        // All active staff and system accounts have panel login access
        return true;
    }

    /**
     * Check if user has Super Admin privileges.
     */
    public function isSuperAdmin(): bool
    {
        if (in_array($this->role, ['admin', 'super_admin'], true)) {
            return true;
        }

        return $this->exists && ($this->hasRole('Super Admin', 'admin') || $this->hasRole('Super Admin', 'web'));
    }

    /**
     * Check if user is Workspace Admin.
     */
    public function isWorkspaceAdmin(): bool
    {
        if (in_array($this->role, ['workspace_admin', 'admin', 'super_admin'], true)) {
            return true;
        }

        return $this->exists && ($this->hasRole('Workspace Admin', 'admin') || $this->hasRole('Workspace Admin', 'web'));
    }

    /**
     * Check if user is Support Agent.
     */
    public function isSupportAgent(): bool
    {
        if (in_array($this->role, ['support_agent'], true)) {
            return true;
        }

        return $this->exists && ($this->hasRole('Support Agent', 'admin') || $this->hasRole('Support Agent', 'web'));
    }

    /**
     * Check if user is Viewer (Read-only).
     */
    public function isViewer(): bool
    {
        if (in_array($this->role, ['viewer'], true)) {
            return true;
        }

        return $this->exists && ($this->hasRole('Viewer', 'admin') || $this->hasRole('Viewer', 'web'));
    }

    /**
     * Check if user has administrative privileges (Super Admin, Administrator, Workspace Admin).
     */
    public function isAdmin(): bool
    {
        return $this->isSuperAdmin()
            || $this->isWorkspaceAdmin()
            || in_array($this->role, ['admin', 'super_admin', 'workspace_admin'], true);
    }

    /**
     * Super Admin can manage all settings including secrets, maintenance, and server controls.
     */
    public function canManageAllSettings(): bool
    {
        return $this->isSuperAdmin();
    }

    /**
     * Workspace Admin or Super Admin can manage general business configuration.
     */
    public function canManageBusinessSettings(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Settings panel is strictly restricted to Administrators.
     */
    public function canViewSettings(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Authorize viewing module settings control plane.
     */
    public function canViewModuleSettings(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Authorize staff user management.
     */
    public function canManageUsers(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Authorize modifying module settings.
     */
    public function canEditModuleSettings(?string $module = null): bool
    {

        return $this->isSuperAdmin() 
            || $this->isWorkspaceAdmin() 
            || $this->hasRole('Super Admin') 
            || $this->hasRole('Workspace Admin')
            || in_array($this->role, ['admin', 'super_admin', 'workspace_admin']);
    }

    /**
     * Authorize enabling/disabling module statuses.
     */
    public function canManageModuleStatus(?string $module = null): bool
    {
        return $this->isSuperAdmin();
    }

    /**
     * Authorize viewing and modifying encrypted sensitive secrets.
     */
    public function canManageSensitiveSettings(?string $module = null): bool
    {
        return $this->isSuperAdmin();
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function offlineSales(): HasMany
    {
        return $this->hasMany(OfflineSale::class, 'user_id');
    }

    public function crmLeads(): HasMany
    {
        return $this->hasMany(CrmLead::class, 'customer_id')->latest();
    }

    public function contactMessages(): HasMany
    {
        return $this->hasMany(ContactMessage::class, 'customer_id')->latest();
    }

    public function getOutstandingFollowUpsAttribute(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->crmLeads()
            ->whereNotNull('follow_up_date')
            ->whereNotIn('stage', [CrmLead::STAGE_WON, CrmLead::STAGE_LOST])
            ->orderBy('follow_up_date')
            ->get();
    }

    public function getLifetimeSpendAttribute(): float
    {
        $online = (float)$this->orders()->whereNotIn('status', [Order::STATUS_CANCELLED, 'failed_delivery'])->sum('total_amount');
        $pos = (float)$this->offlineSales()->where('status', 'completed')->sum('total_amount');
        return round($online + $pos, 2);
    }

    public function getTotalOrdersCountAttribute(): int
    {
        $online = $this->orders()->whereNotIn('status', [Order::STATUS_CANCELLED, 'failed_delivery'])->count();
        $pos = $this->offlineSales()->where('status', 'completed')->count();
        return $online + $pos;
    }

    public function getAverageOrderValueAttribute(): float
    {
        $count = $this->total_orders_count;
        return $count > 0 ? round($this->lifetime_spend / $count, 2) : 0.00;
    }

    public function getLastOrderDateAttribute(): ?\Carbon\Carbon
    {
        $latestOrder = $this->orders()->latest('created_at')->first();
        $latestPos = $this->offlineSales()->latest('sold_at')->first();

        $orderDate = $latestOrder?->created_at;
        $posDate = $latestPos?->sold_at;

        if (!$orderDate) return $posDate;
        if (!$posDate) return $orderDate;

        return $orderDate->gt($posDate) ? $orderDate : $posDate;
    }

    public function getCustomerSegmentAttribute(): string
    {
        $spend = $this->lifetime_spend;
        $count = $this->total_orders_count;
        $lastOrder = $this->last_order_date;

        if ($spend >= 50000 || $count >= 5) {
            return 'VIP';
        }

        if ($count > 1) {
            if ($lastOrder && $lastOrder->lt(now()->subDays(90))) {
                return 'Inactive';
            }
            return 'Returning';
        }

        if ($count === 1) {
            if ($lastOrder && $lastOrder->lt(now()->subDays(90))) {
                return 'Inactive';
            }
            return 'New';
        }

        return 'No Orders';
    }

    public function getPrimaryAddressAttribute(): string
    {
        $parts = array_filter([
            $this->tole,
            $this->ward ? 'Ward No. ' . $this->ward : null,
            $this->landmark ? 'Near ' . $this->landmark : null,
            $this->municipality,
            $this->district,
            $this->province,
        ]);

        if (!empty($parts)) {
            return implode(', ', $parts);
        }

        return (string)($this->address ?: 'Kathmandu, Nepal');
    }

    public function getFormattedPhoneAttribute(): string
    {
        if (empty($this->phone)) return '—';
        $p = preg_replace('/[^0-9]/', '', (string)$this->phone);
        if (strlen($p) === 10) {
            return substr($p, 0, 4) . ' ' . substr($p, 4, 3) . ' ' . substr($p, 7);
        }
        return (string)$this->phone;
    }
}
