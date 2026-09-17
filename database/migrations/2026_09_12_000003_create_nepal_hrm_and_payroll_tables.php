<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Data-Driven Nepal Payroll & Statutory Tax Configurations
        if (!Schema::hasTable('hrm_payroll_tax_configurations')) {
            Schema::create('hrm_payroll_tax_configurations', function (Blueprint $table) {
                $table->id();
                $table->string('fiscal_year', 20)->unique(); // e.g. '2083/84', '2082/83'
                $table->string('name')->default('Nepal Statutory Payroll & Tax Slabs');
                $table->decimal('ssf_employee_rate', 5, 2)->default(11.00); // 11% basic salary
                $table->decimal('ssf_employer_rate', 5, 2)->default(20.00); // 20% basic salary (10% PF + 8.33% Gratuity + 1.67% Accident/Disability)

                // Single Individual Income Tax Slabs (Nepal Income Tax Act 2058 Sec 87)
                $table->decimal('single_slab_1_limit', 12, 2)->default(500000.00);
                $table->decimal('single_slab_1_rate', 5, 2)->default(1.00); // 1% SST (waived if enrolled in SSF)
                $table->decimal('single_slab_2_limit', 12, 2)->default(200000.00); // Next 200k (500k to 700k)
                $table->decimal('single_slab_2_rate', 5, 2)->default(10.00);
                $table->decimal('single_slab_3_limit', 12, 2)->default(300000.00); // Next 300k (700k to 1M)
                $table->decimal('single_slab_3_rate', 5, 2)->default(20.00);
                $table->decimal('single_slab_4_limit', 12, 2)->default(1000000.00); // Next 1M (1M to 2M)
                $table->decimal('single_slab_4_rate', 5, 2)->default(30.00);
                $table->decimal('single_slab_5_limit', 12, 2)->default(3000000.00); // Next 3M (2M to 5M)
                $table->decimal('single_slab_5_rate', 5, 2)->default(36.00);
                $table->decimal('single_slab_top_rate', 5, 2)->default(39.00); // Above 5M

                // Married / Couple Income Tax Slabs
                $table->decimal('married_slab_1_limit', 12, 2)->default(600000.00);
                $table->decimal('married_slab_1_rate', 5, 2)->default(1.00); // 1% SST (waived if enrolled in SSF)
                $table->decimal('married_slab_2_limit', 12, 2)->default(200000.00); // Next 200k (600k to 800k)
                $table->decimal('married_slab_2_rate', 5, 2)->default(10.00);
                $table->decimal('married_slab_3_limit', 12, 2)->default(300000.00); // Next 300k (800k to 1.1M)
                $table->decimal('married_slab_3_rate', 5, 2)->default(20.00);
                $table->decimal('married_slab_4_limit', 12, 2)->default(900000.00); // Next 900k (1.1M to 2M)
                $table->decimal('married_slab_4_rate', 5, 2)->default(30.00);
                $table->decimal('married_slab_5_limit', 12, 2)->default(3000000.00); // Next 3M (2M to 5M)
                $table->decimal('married_slab_5_rate', 5, 2)->default(36.00);
                $table->decimal('married_slab_top_rate', 5, 2)->default(39.00); // Above 5M

                // Deductions, Shift Rules & Allowances
                $table->decimal('standard_daily_hours', 5, 2)->default(8.00); // Nepal Labour Act 2074
                $table->unsignedInteger('standard_monthly_work_days')->default(26);
                $table->decimal('overtime_rate_multiplier', 4, 2)->default(1.50); // 1.5x hourly rate under Labour Act
                $table->decimal('cit_annual_max', 12, 2)->default(300000.00);
                $table->decimal('life_insurance_annual_max', 12, 2)->default(40000.00);
                $table->boolean('is_active')->default(true);
                $table->date('effective_from')->nullable();
                $table->date('effective_until')->nullable();
                $table->timestamps();
            });
        }

        // 2. Nepal Employee Operational Columns
        $empCols = [
            'pan_number' => fn (Blueprint $table) => $table->string('pan_number', 20)->nullable()->after('phone'),
            'citizenship_number' => fn (Blueprint $table) => $table->string('citizenship_number', 50)->nullable()->after('pan_number'),
            'marital_status' => fn (Blueprint $table) => $table->enum('marital_status', ['single', 'married'])->default('single')->after('citizenship_number'),
            'branch_location' => fn (Blueprint $table) => $table->string('branch_location', 100)->default('Kathmandu Showroom (Durbar Marg)')->after('marital_status'),
            'basic_salary' => fn (Blueprint $table) => $table->decimal('basic_salary', 12, 2)->default(0.00)->after('branch_location'),
            'allowance_amount' => fn (Blueprint $table) => $table->decimal('allowance_amount', 12, 2)->default(0.00)->after('basic_salary'),
            'gross_salary' => fn (Blueprint $table) => $table->decimal('gross_salary', 12, 2)->default(0.00)->after('allowance_amount'),
            'ssf_enrolled' => fn (Blueprint $table) => $table->boolean('ssf_enrolled')->default(true)->after('gross_salary'),
            'ssf_number' => fn (Blueprint $table) => $table->string('ssf_number', 50)->nullable()->after('ssf_enrolled'),
            'bank_name' => fn (Blueprint $table) => $table->string('bank_name', 100)->nullable()->after('bank_account_number'),
            'bank_branch' => fn (Blueprint $table) => $table->string('bank_branch', 100)->nullable()->after('bank_name'),
            'bank_account_name' => fn (Blueprint $table) => $table->string('bank_account_name', 150)->nullable()->after('bank_branch'),
            'annual_leave_quota' => fn (Blueprint $table) => $table->decimal('annual_leave_quota', 5, 2)->default(18.00)->after('bank_account_name'),
            'sick_leave_quota' => fn (Blueprint $table) => $table->decimal('sick_leave_quota', 5, 2)->default(12.00)->after('annual_leave_quota'),
        ];
        foreach ($empCols as $col => $cb) {
            if (Schema::hasTable('hrm_employees') && !Schema::hasColumn('hrm_employees', $col)) {
                Schema::table('hrm_employees', $cb);
            }
        }

        // 3. Nepal Attendance & Mobile Punch Columns
        $timesheetCols = [
            'shift_name' => fn (Blueprint $table) => $table->string('shift_name', 100)->default('Showroom Retail Shift')->after('date'),
            'shift_start_time' => fn (Blueprint $table) => $table->time('shift_start_time')->default('10:00:00')->after('shift_name'),
            'shift_end_time' => fn (Blueprint $table) => $table->time('shift_end_time')->default('19:00:00')->after('shift_start_time'),
            'clock_in_latitude' => fn (Blueprint $table) => $table->decimal('clock_in_latitude', 10, 7)->nullable()->after('clock_in'),
            'clock_in_longitude' => fn (Blueprint $table) => $table->decimal('clock_in_longitude', 10, 7)->nullable()->after('clock_in_latitude'),
            'clock_in_accuracy' => fn (Blueprint $table) => $table->decimal('clock_in_accuracy', 8, 2)->nullable()->after('clock_in_longitude'),
            'clock_out_latitude' => fn (Blueprint $table) => $table->decimal('clock_out_latitude', 10, 7)->nullable()->after('clock_out'),
            'clock_out_longitude' => fn (Blueprint $table) => $table->decimal('clock_out_longitude', 10, 7)->nullable()->after('clock_out_latitude'),
            'clock_out_accuracy' => fn (Blueprint $table) => $table->decimal('clock_out_accuracy', 8, 2)->nullable()->after('clock_out_longitude'),
            'is_late' => fn (Blueprint $table) => $table->boolean('is_late')->default(false)->after('clock_out_accuracy'),
            'late_minutes' => fn (Blueprint $table) => $table->unsignedInteger('late_minutes')->default(0)->after('is_late'),
            'is_early_departure' => fn (Blueprint $table) => $table->boolean('is_early_departure')->default(false)->after('late_minutes'),
            'early_departure_minutes' => fn (Blueprint $table) => $table->unsignedInteger('early_departure_minutes')->default(0)->after('is_early_departure'),
            'attendance_status' => fn (Blueprint $table) => $table->string('attendance_status', 30)->default('present')->after('status')->index(),
            'is_missing_punch' => fn (Blueprint $table) => $table->boolean('is_missing_punch')->default(false)->after('attendance_status'),
            'correction_requested' => fn (Blueprint $table) => $table->boolean('correction_requested')->default(false)->after('is_missing_punch'),
            'correction_notes' => fn (Blueprint $table) => $table->text('correction_notes')->nullable()->after('correction_requested'),
            'device_info' => fn (Blueprint $table) => $table->string('device_info', 255)->nullable()->after('correction_notes'),
        ];
        foreach ($timesheetCols as $col => $cb) {
            if (Schema::hasTable('hrm_timesheets') && !Schema::hasColumn('hrm_timesheets', $col)) {
                Schema::table('hrm_timesheets', $cb);
            }
        }

        // 4. Nepal Payroll Runs Header Columns
        $runCols = [
            'fiscal_year' => fn (Blueprint $table) => $table->string('fiscal_year', 20)->default('2082/83')->after('run_number'),
            'total_basic_salary' => fn (Blueprint $table) => $table->decimal('total_basic_salary', 14, 2)->default(0.00)->after('status'),
            'total_allowances' => fn (Blueprint $table) => $table->decimal('total_allowances', 14, 2)->default(0.00)->after('total_basic_salary'),
            'total_overtime_amount' => fn (Blueprint $table) => $table->decimal('total_overtime_amount', 14, 2)->default(0.00)->after('total_allowances'),
            'total_ssf_employee' => fn (Blueprint $table) => $table->decimal('total_ssf_employee', 14, 2)->default(0.00)->after('total_overtime_amount'),
            'total_ssf_employer' => fn (Blueprint $table) => $table->decimal('total_ssf_employer', 14, 2)->default(0.00)->after('total_ssf_employee'),
            'total_tds_tax' => fn (Blueprint $table) => $table->decimal('total_tds_tax', 14, 2)->default(0.00)->after('total_ssf_employer'),
            'total_net_salary' => fn (Blueprint $table) => $table->decimal('total_net_salary', 14, 2)->default(0.00)->after('total_tds_tax'),
            'total_employer_cost' => fn (Blueprint $table) => $table->decimal('total_employer_cost', 14, 2)->default(0.00)->after('total_net_salary'),
            'payout_journal_entry_id' => fn (Blueprint $table) => $table->foreignId('payout_journal_entry_id')->nullable()->constrained('accounting_journal_entries')->nullOnDelete()->after('journal_entry_id'),
            'payout_bank_account_id' => fn (Blueprint $table) => $table->foreignId('payout_bank_account_id')->nullable()->constrained('accounting_bank_accounts')->nullOnDelete()->after('payout_journal_entry_id'),
            'payout_date' => fn (Blueprint $table) => $table->date('payout_date')->nullable()->after('payout_bank_account_id'),
            'payout_reference' => fn (Blueprint $table) => $table->string('payout_reference', 100)->nullable()->after('payout_date'),
        ];
        foreach ($runCols as $col => $cb) {
            if (Schema::hasTable('hrm_payroll_runs') && !Schema::hasColumn('hrm_payroll_runs', $col)) {
                Schema::table('hrm_payroll_runs', $cb);
            }
        }

        // 5. Nepal Payroll Run Line Items Columns
        $itemCols = [
            'basic_salary' => fn (Blueprint $table) => $table->decimal('basic_salary', 12, 2)->default(0.00)->after('contract_id'),
            'allowance_amount' => fn (Blueprint $table) => $table->decimal('allowance_amount', 12, 2)->default(0.00)->after('basic_salary'),
            'overtime_hours' => fn (Blueprint $table) => $table->decimal('overtime_hours', 8, 2)->default(0.00)->after('allowance_amount'),
            'overtime_amount' => fn (Blueprint $table) => $table->decimal('overtime_amount', 12, 2)->default(0.00)->after('overtime_hours'),
            'absent_days' => fn (Blueprint $table) => $table->decimal('absent_days', 5, 2)->default(0.00)->after('overtime_amount'),
            'absence_deduction' => fn (Blueprint $table) => $table->decimal('absence_deduction', 12, 2)->default(0.00)->after('absent_days'),
            'bonus_amount' => fn (Blueprint $table) => $table->decimal('bonus_amount', 12, 2)->default(0.00)->after('absence_deduction'),
            'gross_salary' => fn (Blueprint $table) => $table->decimal('gross_salary', 12, 2)->default(0.00)->after('bonus_amount'),
            'ssf_employee_amount' => fn (Blueprint $table) => $table->decimal('ssf_employee_amount', 12, 2)->default(0.00)->after('gross_salary'),
            'ssf_employer_amount' => fn (Blueprint $table) => $table->decimal('ssf_employer_amount', 12, 2)->default(0.00)->after('ssf_employee_amount'),
            'taxable_income' => fn (Blueprint $table) => $table->decimal('taxable_income', 12, 2)->default(0.00)->after('ssf_employer_amount'),
            'tds_tax_rate' => fn (Blueprint $table) => $table->decimal('tds_tax_rate', 5, 2)->default(0.00)->after('taxable_income'),
            'tds_tax_amount' => fn (Blueprint $table) => $table->decimal('tds_tax_amount', 12, 2)->default(0.00)->after('tds_tax_rate'),
            'cit_deduction' => fn (Blueprint $table) => $table->decimal('cit_deduction', 12, 2)->default(0.00)->after('tds_tax_amount'),
            'other_deductions' => fn (Blueprint $table) => $table->decimal('other_deductions', 12, 2)->default(0.00)->after('cit_deduction'),
            'net_salary' => fn (Blueprint $table) => $table->decimal('net_salary', 12, 2)->default(0.00)->after('other_deductions'),
            'employer_total_cost' => fn (Blueprint $table) => $table->decimal('employer_total_cost', 12, 2)->default(0.00)->after('net_salary'),
            'payslip_number' => fn (Blueprint $table) => $table->string('payslip_number', 50)->nullable()->unique()->after('employer_total_cost'),
        ];
        foreach ($itemCols as $col => $cb) {
            if (Schema::hasTable('hrm_payroll_run_items') && !Schema::hasColumn('hrm_payroll_run_items', $col)) {
                Schema::table('hrm_payroll_run_items', $cb);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hrm_payroll_tax_configurations');
    }
};
