<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\OfflineSale;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BikriKhataEntry extends AccountingInvoice
{
    protected $table = 'accounting_invoices';

    protected static function booted(): void
    {
        static::addGlobalScope('sales_entries', function (Builder $builder) {
            $builder->whereIn('type', ['sales_invoice', 'credit_note']);
        });

        static::creating(function ($model) {
            if (empty($model->type)) {
                $model->type = 'sales_invoice';
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'reference_order_id');
    }

    public function offlineSale(): BelongsTo
    {
        return $this->belongsTo(OfflineSale::class, 'reference_offline_sale_id');
    }

    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(AccountingInvoice::class, 'original_invoice_id');
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(User::class, 'salesperson_id');
    }

    public function getCustomerTypeLabelAttribute(): string
    {
        return match ($this->customer_type) {
            'b2b_corporate' => 'Corporate / B2B (PAN)',
            'b2c_retail' => 'Retail / Consumer',
            'walk_in_pos' => 'Showroom Walk-in',
            'export_overseas' => 'Overseas Export',
            default => ucfirst(str_replace('_', ' ', (string)$this->customer_type)),
        };
    }

    public function getSalesChannelLabelAttribute(): string
    {
        return match ($this->sales_channel) {
            'pos_showroom' => '🏬 Showroom POS',
            'web_storefront' => '🌐 E-Commerce Webshop',
            'concierge_whatsapp' => '💬 WhatsApp Clienteling',
            'wholesale' => '📦 Wholesale / Bulk Sales',
            default => ucfirst(str_replace('_', ' ', (string)$this->sales_channel)),
        };
    }

    public function getTaxableAmountAttribute($value): float
    {
        $val = (float)$value;
        if ($val <= 0 && (float)$this->total_amount > 0 && (float)$this->exempt_amount <= 0 && (float)$this->export_amount <= 0) {
            return round((float)$this->total_amount / 1.13, 2);
        }
        return $val;
    }

    public function getVatAmountAttribute($value): float
    {
        $val = (float)$value;
        if ($val <= 0 && (float)$this->total_amount > 0 && (float)$this->exempt_amount <= 0 && (float)$this->export_amount <= 0) {
            $taxable = round((float)$this->total_amount / 1.13, 2);
            return round((float)$this->total_amount - $taxable, 2);
        }
        return $val;
    }

    public function isCreditNote(): bool
    {
        return $this->type === 'credit_note';
    }
}
