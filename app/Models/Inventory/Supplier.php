<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    use HasFactory;

    protected $table = 'inventory_suppliers';

    protected $fillable = [
        'code',
        'name',
        'legal_name',
        'contact_person',
        'email',
        'phone',
        'address',
        'postal_code',
        'city',
        'country',
        'currency',
        'due_balance',
        'payment_terms',
        'lead_time_days',
        'tax_vat_number',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'due_balance' => 'decimal:2',
        'lead_time_days' => 'integer',
        'is_active' => 'boolean',
    ];

    public function setCountryAttribute($value): void
    {
        if (empty($value)) {
            $this->attributes['country'] = 'NP';
            return;
        }

        $upper = strtoupper(trim((string)$value));
        if ($upper === 'NEPAL' || $upper === 'NP') {
            $this->attributes['country'] = 'NP';
        } elseif ($upper === 'INDIA' || $upper === 'IN') {
            $this->attributes['country'] = 'IN';
        } elseif ($upper === 'CHINA' || $upper === 'CN') {
            $this->attributes['country'] = 'CN';
        } else {
            $this->attributes['country'] = substr($upper, 0, 2);
        }
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'supplier_id');
    }

    public function getTotalPurchasesNprAttribute(): float
    {
        return (float) $this->purchaseOrders()
            ->whereIn('status', ['received', 'partially_received', 'closed'])
            ->sum('subtotal_currency');
    }


    public function getOutstandingPosCountAttribute(): int
    {
        return $this->purchaseOrders()
            ->whereIn('status', ['ordered', 'submitted', 'approved', 'in_transit'])
            ->count();
    }

    public function getLastPurchaseDateAttribute(): ?string
    {
        $last = $this->purchaseOrders()->orderBy('order_date', 'desc')->first();
        return $last?->order_date?->toDateString();
    }
}
