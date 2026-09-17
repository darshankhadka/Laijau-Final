<?php

namespace App\Models\Accounting;

use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class AccountingInvoice extends Model
{
    use HasFactory;

    protected $table = 'accounting_invoices';

    protected $fillable = [
        'invoice_number',
        'type',
        'fiscal_year',
        'tax_period_id',
        'contact_name',
        'contact_email',
        'contact_phone',
        'contact_cvr',
        'buyer_pan',
        'seller_pan',
        'customer_type',
        'purchase_type',
        'sales_channel',
        'contact_address',
        'contact_country',
        'issue_date',
        'due_date',
        'currency',
        'exchange_rate_to_npr',
        'subtotal',
        'taxable_amount',
        'exempt_amount',
        'export_amount',
        'discount_amount',
        'vat_amount',
        'total_amount',
        'paid_amount',
        'payment_status',
        'is_credit',
        'journal_entry_id',
        'reference_order_id',
        'reference_offline_sale_id',
        'reference_purchase_order_id',
        'original_invoice_id',
        'tds_applicable',
        'tds_rate',
        'tds_amount',
        'supporting_document_path',
        'posted_to_gl',
        'branch',
        'salesperson_id',
        'payment_terms',
        'notes',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'due_date' => 'date',
        'exchange_rate_to_npr' => 'decimal:6',
        'subtotal' => 'decimal:4',
        'taxable_amount' => 'decimal:4',
        'exempt_amount' => 'decimal:4',
        'export_amount' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'vat_rate' => 'decimal:2',
        'vat_amount' => 'decimal:4',
        'total_amount' => 'decimal:4',
        'paid_amount' => 'decimal:4',
        'tds_rate' => 'decimal:2',
        'tds_amount' => 'decimal:4',
        'is_credit' => 'boolean',
        'tds_applicable' => 'boolean',
        'posted_to_gl' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(AccountingInvoiceItem::class, 'accounting_invoice_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'reference_order_id');
    }

    public function offlineSale(): BelongsTo
    {
        return $this->belongsTo(\App\Models\OfflineSale::class, 'reference_offline_sale_id');
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Inventory\PurchaseOrder::class, 'reference_purchase_order_id');
    }

    public function originalInvoice(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_invoice_id');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'tax_period_id');
    }

    public function salesperson(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'salesperson_id');
    }

    public function tdsRecords(): HasMany
    {
        return $this->hasMany(TdsRecord::class, 'reference_id')->where('reference_type', self::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (AccountingInvoice $invoice) {
            if (in_array($invoice->payment_status, ['paid', 'partial'], true) || $invoice->journal_entry_id !== null) {
                throw new \RuntimeException("Posted and settled invoices (#{$invoice->invoice_number}) are legally binding under the Nepal VAT Act and cannot be deleted. Please issue a credit note or adjustment journal instead.");
            }
        });
    }

    public static function generateNextInvoiceNumber(string $type = 'sales_invoice', ?string $date = null): string
    {
        $year = $date ? substr($date, 0, 4) : date('Y');
        $settings = class_exists(\App\Services\Settings\SettingsService::class) ? app(\App\Services\Settings\SettingsService::class) : null;

        $customPrefix = match ($type) {
            'supplier_bill' => $settings ? $settings->getString('accounting', 'supplier_bill_prefix', 'KH-') : 'KH-',
            'credit_note' => $settings ? $settings->getString('accounting', 'credit_note_prefix', 'CN-') : 'CN-',
            default => $settings ? $settings->getString('accounting', 'sales_invoice_prefix', 'INV-') : 'INV-',
        };
        $customPrefix = rtrim($customPrefix, '-') . '-';
        $prefix = "{$customPrefix}{$year}-";

        return app(\App\Services\DocumentSequenceService::class)->next(
            "{$type}_{$year}",
            $prefix,
            4,
            fn($p) => \App\Services\DocumentSequenceService::determineMaxFromTable('accounting_invoices', 'invoice_number', $p)
        );
    }

    public function recalculateTotals(): void
    {
        $totals = $this->items()
            ->selectRaw('COALESCE(SUM(quantity * unit_price), 0) as subtotal, COALESCE(SUM(vat_amount), 0) as vat, COALESCE(SUM(total_amount), 0) as total')
            ->first();

        $sub = round((float)($totals->subtotal ?? 0), 4);
        $vat = round((float)($totals->vat ?? 0), 4);
        $tot = round((float)($totals->total ?? 0), 4);

        $this->update([
            'subtotal' => $sub,
            'vat_amount' => $vat,
            'total_amount' => $tot,
        ]);
    }

    public function getVatRateAttribute(): float
    {
        if ((float)$this->taxable_amount > 0 && (float)$this->vat_amount > 0) {
            return round(((float)$this->vat_amount / (float)$this->taxable_amount) * 100, 2);
        }
        return 13.00;
    }
}
