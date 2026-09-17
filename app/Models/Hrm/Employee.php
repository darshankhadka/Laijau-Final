<?php

namespace App\Models\Hrm;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class Employee extends Model
{
    use HasFactory;

    protected $table = 'hrm_employees';

    protected $fillable = [
        'employee_number',
        'user_id',
        'first_name',
        'last_name',
        'cpr_encrypted',
        'cpr_masked',
        'pan_number',
        'citizenship_number',
        'marital_status',
        'branch_location',
        'basic_salary',
        'allowance_amount',
        'gross_salary',
        'ssf_enrolled',
        'ssf_number',
        'email',
        'phone',
        'address',
        'postal_code',
        'city',
        'country',
        'department_id',
        'position_id',
        'manager_id',
        'employment_type',
        'status',
        'hire_date',
        'probation_end_date',
        'termination_date',
        'bank_name',
        'bank_branch',
        'bank_account_number',
        'bank_account_name',
        'bank_reg_number',
        'iban',
        'bic_swift',
        'annual_leave_quota',
        'sick_leave_quota',
        'emergency_contact_name',
        'emergency_contact_phone',
        'emergency_contact_relation',
        'notes',
    ];

    protected $casts = [
        'hire_date' => 'date',
        'probation_end_date' => 'date',
        'termination_date' => 'date',
        'basic_salary' => 'decimal:2',
        'allowance_amount' => 'decimal:2',
        'gross_salary' => 'decimal:2',
        'ssf_enrolled' => 'boolean',
        'annual_leave_quota' => 'decimal:2',
        'sick_leave_quota' => 'decimal:2',
    ];

    /**
     * Set the CPR number securely: encrypts at rest and generates a safe masked version.
     */
    public function setCpr(string $cpr): void
    {
        $clean = preg_replace('/[^0-9]/', '', $cpr);
        if (strlen($clean) === 10) {
            $formatted = substr($clean, 0, 6) . '-' . substr($clean, 6, 4);
            $this->cpr_encrypted = Crypt::encryptString($formatted);
            $this->cpr_masked = '******-' . substr($clean, 6, 4);
        } else {
            $this->cpr_encrypted = Crypt::encryptString($cpr);
            $this->cpr_masked = '******-' . substr($clean, -4);
        }
    }

    /**
     * Decrypt CPR number for authorized administrative tasks.
     */
    public function getDecryptedCpr(): ?string
    {
        if (empty($this->cpr_encrypted)) {
            return null;
        }

        try {
            return Crypt::decryptString($this->cpr_encrypted);
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function getDisplayNameAttribute(): string
    {
        $pos = $this->position ? " ({$this->position->title})" : '';
        return "{$this->employee_number} - {$this->full_name}{$pos}";
    }

    /**
     * Generate the next sequential employee number: LAI-0001, LAI-0002, etc.
     */
    public static function generateNextEmployeeNumber(): string
    {
        $prefix = 'LAI-';
        if (class_exists(\App\Services\Settings\SettingsService::class)) {
            $prefix = app(\App\Services\Settings\SettingsService::class)->getString('hrm', 'employee_id_prefix', 'LAI-');
        }
        $prefix = rtrim($prefix, '-') . '-';

        return app(\App\Services\DocumentSequenceService::class)->next(
            'employee',
            $prefix,
            4,
            fn ($p) => \App\Services\DocumentSequenceService::determineMaxFromTable('hrm_employees', 'employee_number', $p)
        );
    }

    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollRunItem::class, 'employee_id');
    }

    public function latestPayrollItem(): HasOne
    {
        return $this->hasOne(PayrollRunItem::class, 'employee_id')->latestOfMany();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class, 'position_id');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function subordinates(): HasMany
    {
        return $this->hasMany(Employee::class, 'manager_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(EmploymentContract::class, 'employee_id');
    }

    public function activeContract(): HasOne
    {
        return $this->hasOne(EmploymentContract::class, 'employee_id')
            ->where('status', 'active')
            ->latestOfMany();
    }

    public function timesheets(): HasMany
    {
        return $this->hasMany(Timesheet::class, 'employee_id');
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class, 'employee_id');
    }

    public function holidayBalances(): HasMany
    {
        return $this->hasMany(HolidayBalance::class, 'employee_id');
    }

    public function currentHolidayBalance(): HasOne
    {
        return $this->hasOne(HolidayBalance::class, 'employee_id')
            ->where('holiday_year', (int)date('Y'))
            ->latestOfMany();
    }

    public function expenseClaims(): HasMany
    {
        return $this->hasMany(ExpenseClaim::class, 'employee_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EmployeeDocument::class, 'employee_id');
    }

    public function performanceReviews(): HasMany
    {
        return $this->hasMany(PerformanceReview::class, 'employee_id');
    }
}
