<?php

namespace App\Models\Hrm;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollTaxConfiguration extends Model
{
    use HasFactory;

    protected $table = 'hrm_payroll_tax_configurations';

    protected $fillable = [
        'fiscal_year',
        'name',
        'ssf_employee_rate',
        'ssf_employer_rate',
        'single_slab_1_limit',
        'single_slab_1_rate',
        'single_slab_2_limit',
        'single_slab_2_rate',
        'single_slab_3_limit',
        'single_slab_3_rate',
        'single_slab_4_limit',
        'single_slab_4_rate',
        'single_slab_5_limit',
        'single_slab_5_rate',
        'single_slab_top_rate',
        'married_slab_1_limit',
        'married_slab_1_rate',
        'married_slab_2_limit',
        'married_slab_2_rate',
        'married_slab_3_limit',
        'married_slab_3_rate',
        'married_slab_4_limit',
        'married_slab_4_rate',
        'married_slab_5_limit',
        'married_slab_5_rate',
        'married_slab_top_rate',
        'standard_daily_hours',
        'standard_monthly_work_days',
        'overtime_rate_multiplier',
        'cit_annual_max',
        'life_insurance_annual_max',
        'is_active',
        'effective_from',
        'effective_until',
    ];

    protected $casts = [
        'ssf_employee_rate' => 'decimal:2',
        'ssf_employer_rate' => 'decimal:2',
        'single_slab_1_limit' => 'decimal:2',
        'single_slab_1_rate' => 'decimal:2',
        'single_slab_2_limit' => 'decimal:2',
        'single_slab_2_rate' => 'decimal:2',
        'single_slab_3_limit' => 'decimal:2',
        'single_slab_3_rate' => 'decimal:2',
        'single_slab_4_limit' => 'decimal:2',
        'single_slab_4_rate' => 'decimal:2',
        'single_slab_5_limit' => 'decimal:2',
        'single_slab_5_rate' => 'decimal:2',
        'single_slab_top_rate' => 'decimal:2',
        'married_slab_1_limit' => 'decimal:2',
        'married_slab_1_rate' => 'decimal:2',
        'married_slab_2_limit' => 'decimal:2',
        'married_slab_2_rate' => 'decimal:2',
        'married_slab_3_limit' => 'decimal:2',
        'married_slab_3_rate' => 'decimal:2',
        'married_slab_4_limit' => 'decimal:2',
        'married_slab_4_rate' => 'decimal:2',
        'married_slab_5_limit' => 'decimal:2',
        'married_slab_5_rate' => 'decimal:2',
        'married_slab_top_rate' => 'decimal:2',
        'standard_daily_hours' => 'decimal:2',
        'standard_monthly_work_days' => 'integer',
        'overtime_rate_multiplier' => 'decimal:2',
        'cit_annual_max' => 'decimal:2',
        'life_insurance_annual_max' => 'decimal:2',
        'is_active' => 'boolean',
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];

    /**
     * Resolve statutory payroll and TDS configuration for the given fiscal year.
     */
    public static function resolveForFiscalYear(?string $fiscalYear = null): self
    {
        if ($fiscalYear) {
            $config = static::where('fiscal_year', $fiscalYear)->first();
            if ($config) {
                return $config;
            }
        }

        return static::where('is_active', true)->orderByDesc('fiscal_year')->first()
            ?? static::createDefaultConfig($fiscalYear ?? '2083/84');
    }

    /**
     * Calculates annual & monthly TDS withholding under Section 87 of Nepal Income Tax Act, 2058.
     */
    public function calculateTds(float $annualTaxableIncome, string $maritalStatus = 'single', bool $isSsfEnrolled = true): array
    {
        $taxable = max(0.0, $annualTaxableIncome);
        $totalAnnualTax = 0.0;
        $isMarried = strtolower($maritalStatus) === 'married';

        // 1. Slab 1 (Single 500k, Married 600k)
        $slab1Limit = (float)($isMarried ? $this->married_slab_1_limit : $this->single_slab_1_limit);
        $slab1Rate = (float)($isMarried ? $this->married_slab_1_rate : $this->single_slab_1_rate);
        if ($isSsfEnrolled) {
            // Per Finance Act, 1% Social Security Tax is waived for employees registered in SSF
            $slab1Rate = 0.0;
        }

        $taxableInSlab1 = min($taxable, $slab1Limit);
        $taxSlab1 = round($taxableInSlab1 * ($slab1Rate / 100.0), 2);
        $totalAnnualTax += $taxSlab1;
        $remTaxable = max(0.0, $taxable - $slab1Limit);

        // 2. Slab 2 (Next 200k @ 10%)
        $slab2Limit = (float)($isMarried ? $this->married_slab_2_limit : $this->single_slab_2_limit);
        $slab2Rate = (float)($isMarried ? $this->married_slab_2_rate : $this->single_slab_2_rate);
        $taxableInSlab2 = min($remTaxable, $slab2Limit);
        $taxSlab2 = round($taxableInSlab2 * ($slab2Rate / 100.0), 2);
        $totalAnnualTax += $taxSlab2;
        $remTaxable = max(0.0, $remTaxable - $slab2Limit);

        // 3. Slab 3 (Next 300k @ 20%)
        $slab3Limit = (float)($isMarried ? $this->married_slab_3_limit : $this->single_slab_3_limit);
        $slab3Rate = (float)($isMarried ? $this->married_slab_3_rate : $this->single_slab_3_rate);
        $taxableInSlab3 = min($remTaxable, $slab3Limit);
        $taxSlab3 = round($taxableInSlab3 * ($slab3Rate / 100.0), 2);
        $totalAnnualTax += $taxSlab3;
        $remTaxable = max(0.0, $remTaxable - $slab3Limit);

        // 4. Slab 4 (Single next 1M @ 30%, Married next 900k @ 30%)
        $slab4Limit = (float)($isMarried ? $this->married_slab_4_limit : $this->single_slab_4_limit);
        $slab4Rate = (float)($isMarried ? $this->married_slab_4_rate : $this->single_slab_4_rate);
        $taxableInSlab4 = min($remTaxable, $slab4Limit);
        $taxSlab4 = round($taxableInSlab4 * ($slab4Rate / 100.0), 2);
        $totalAnnualTax += $taxSlab4;
        $remTaxable = max(0.0, $remTaxable - $slab4Limit);

        // 5. Slab 5 (Next 3M @ 36% -> from 2M to 5M)
        $slab5Limit = (float)($isMarried ? $this->married_slab_5_limit : $this->single_slab_5_limit);
        $slab5Rate = (float)($isMarried ? $this->married_slab_5_rate : $this->single_slab_5_rate);
        $taxableInSlab5 = min($remTaxable, $slab5Limit);
        $taxSlab5 = round($taxableInSlab5 * ($slab5Rate / 100.0), 2);
        $totalAnnualTax += $taxSlab5;
        $remTaxable = max(0.0, $remTaxable - $slab5Limit);

        // 6. Surcharge Slab (Above 5M @ 39%)
        $topRate = (float)($isMarried ? $this->married_slab_top_rate : $this->single_slab_top_rate);
        if ($remTaxable > 0.0) {
            $taxTop = round($remTaxable * ($topRate / 100.0), 2);
            $totalAnnualTax += $taxTop;
        }

        $monthlyTds = round($totalAnnualTax / 12.0, 2);
        $effectiveRate = $annualTaxableIncome > 0 ? round(($totalAnnualTax / $annualTaxableIncome) * 100.0, 2) : 0.0;

        return [
            'annual_taxable_income' => round($annualTaxableIncome, 2),
            'annual_total_tax' => round($totalAnnualTax, 2),
            'monthly_tds_tax' => $monthlyTds,
            'effective_rate' => $effectiveRate,
            'marital_status' => $isMarried ? 'married' : 'single',
            'is_ssf_enrolled' => $isSsfEnrolled,
            'fiscal_year' => $this->fiscal_year,
        ];
    }

    /**
     * Calculate statutory SSF contributions on basic salary.
     */
    public function calculateSsf(float $basicSalary): array
    {
        $basic = max(0.0, $basicSalary);
        $eeRate = (float)$this->ssf_employee_rate;
        $erRate = (float)$this->ssf_employer_rate;

        $eeAmount = round($basic * ($eeRate / 100.0), 2);
        $erAmount = round($basic * ($erRate / 100.0), 2);

        return [
            'basic_salary' => $basic,
            'employee_rate' => $eeRate,
            'employee_amount' => $eeAmount,
            'employer_rate' => $erRate,
            'employer_amount' => $erAmount,
            'total_ssf_amount' => round($eeAmount + $erAmount, 2),
        ];
    }

    /**
     * Create default configuration instance.
     */
    public static function createDefaultConfig(string $fiscalYear): self
    {
        return static::create([
            'fiscal_year' => $fiscalYear,
            'name' => "Nepal Statutory Payroll & Tax Slabs FY {$fiscalYear}",
            'ssf_employee_rate' => 11.00,
            'ssf_employer_rate' => 20.00,
            'single_slab_1_limit' => 500000.00,
            'single_slab_1_rate' => 1.00,
            'single_slab_2_limit' => 200000.00,
            'single_slab_2_rate' => 10.00,
            'single_slab_3_limit' => 300000.00,
            'single_slab_3_rate' => 20.00,
            'single_slab_4_limit' => 1000000.00,
            'single_slab_4_rate' => 30.00,
            'single_slab_5_limit' => 3000000.00,
            'single_slab_5_rate' => 36.00,
            'single_slab_top_rate' => 39.00,
            'married_slab_1_limit' => 600000.00,
            'married_slab_1_rate' => 1.00,
            'married_slab_2_limit' => 200000.00,
            'married_slab_2_rate' => 10.00,
            'married_slab_3_limit' => 300000.00,
            'married_slab_3_rate' => 20.00,
            'married_slab_4_limit' => 900000.00,
            'married_slab_4_rate' => 30.00,
            'married_slab_5_limit' => 3000000.00,
            'married_slab_5_rate' => 36.00,
            'married_slab_top_rate' => 39.00,
            'standard_daily_hours' => 8.00,
            'standard_monthly_work_days' => 26,
            'overtime_rate_multiplier' => 1.50,
            'cit_annual_max' => 300000.00,
            'life_insurance_annual_max' => 40000.00,
            'is_active' => true,
        ]);
    }
}
