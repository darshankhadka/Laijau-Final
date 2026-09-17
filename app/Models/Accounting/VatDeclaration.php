<?php

namespace App\Models\Accounting;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class VatDeclaration extends Model
{
    use HasFactory;

    protected $table = 'accounting_vat_declarations';

    protected $fillable = [
        'declaration_number',
        'period_id',
        'period_name',
        'start_date',
        'end_date',
        'declaration_type',
        'output_vat_13',
        'input_vat_13',
        'net_vat_position',
        'taxable_sales_13',
        'taxable_purchases_13',
        'export_sales_0',
        'exempt_sales',
        'status',
        'submitted_at',
        'payment_date',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'output_vat_13' => 'decimal:4',
        'input_vat_13' => 'decimal:4',
        'net_vat_position' => 'decimal:4',
        'taxable_sales_13' => 'decimal:4',
        'taxable_purchases_13' => 'decimal:4',
        'export_sales_0' => 'decimal:4',
        'exempt_sales' => 'decimal:4',
        'submitted_at' => 'datetime',
        'payment_date' => 'date',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'period_id');
    }

    public static function generateNextDeclarationNumber(string $type = 'nepal_vat', ?string $date = null): string
    {
        $year = $date ? substr($date, 0, 4) : date('Y');
        $prefix = "VAT-{$year}-";

        return DB::transaction(function () use ($prefix) {
            $latest = self::where('declaration_number', 'like', "{$prefix}%")
                ->orderByDesc('id')
                ->lockForUpdate()
                ->value('declaration_number');

            if ($latest && preg_match('/^[A-Z]+-\d{4}-(\d+)$/', $latest, $m)) {
                $nextSeq = (int)$m[1] + 1;
            } else {
                $nextSeq = 1;
            }

            return sprintf('%s%02d', $prefix, $nextSeq);
        });
    }

    public function calculateNetVatPosition(): float
    {
        $net = (float)$this->output_vat_13 - (float)$this->input_vat_13;
        $rounded = round($net, 4);
        $this->update(['net_vat_position' => $rounded]);

        return $rounded;
    }
}
