<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\Supplier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KharidKhataEntry extends AccountingInvoice
{
    protected $table = 'accounting_invoices';

    protected static function booted(): void
    {
        static::addGlobalScope('purchase_entries', function (Builder $builder) {
            $builder->whereIn('type', ['supplier_bill', 'debit_note']);
        });

        static::creating(function ($model) {
            if (empty($model->type)) {
                $model->type = 'supplier_bill';
            }
        });
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class, 'reference_purchase_order_id');
    }

    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(AccountingInvoice::class, 'original_invoice_id');
    }

    public function getPurchaseTypeLabelAttribute(): string
    {
        return match ($this->purchase_type) {
            'merchandise' => 'Merchandise Procurement',
            'local_taxable_13' => 'Local Taxable (13% VAT)',
            'local_exempt' => 'Local Exempt (0% VAT)',
            'import_customs' => 'Overseas Import (Customs)',
            'capital_fixed_asset' => 'Capital / Fixed Asset',
            'administrative_service' => 'Service / Operational',
            default => ucfirst(str_replace('_', ' ', (string)$this->purchase_type)),
        };
    }

    public function isDebitNote(): bool
    {
        return $this->type === 'debit_note';
    }
}
