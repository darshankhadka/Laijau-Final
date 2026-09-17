<?php

declare(strict_types=1);

namespace App\Models\Accounting;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountingFiscalYear extends Model
{
    use HasFactory;

    protected $table = 'accounting_fiscal_years';

    protected $fillable = [
        'fiscal_year',
        'start_date',
        'end_date',
        'is_current',
        'status',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_current' => 'boolean',
    ];

    public function periods(): HasMany
    {
        return $this->hasMany(AccountingPeriod::class, 'fiscal_year', 'fiscal_year');
    }

    public static function getCurrent(): ?self
    {
        return self::where('is_current', true)->first()
            ?: self::where('status', 'open')->latest('start_date')->first();
    }

    public static function resolveForDate(string | Carbon $date): ?self
    {
        $d = Carbon::parse($date)->format('Y-m-d');
        return self::where('start_date', '<=', $d)
            ->where('end_date', '>=', $d)
            ->first()
            ?: self::getCurrent();
    }

    public function isLocked(): bool
    {
        return in_array($this->status, ['locked', 'closed'], true);
    }
}
