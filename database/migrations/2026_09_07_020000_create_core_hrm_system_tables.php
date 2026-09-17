<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations for core Nepal HRM & Payroll tables.
     */
    public function up(): void
    {
        // 1. Departments
        Schema::create('hrm_departments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 20)->unique();
            $table->text('description')->nullable();
            $table->unsignedBigInteger('manager_id')->nullable(); // Soft reference to employee
            $table->string('location')->default('Kathmandu Central Showroom'); // Showroom, HQ, Remote, Warehouse
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 2. Positions
        Schema::create('hrm_positions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('department_id')->constrained('hrm_departments')->cascadeOnDelete();
            $table->string('title');
            $table->string('code', 20)->unique();
            $table->text('description')->nullable();
            $table->enum('employment_type', ['full_time', 'part_time', 'hourly'])->default('full_time');
            $table->decimal('min_salary_npr', 12, 2)->default(0.00);
            $table->decimal('max_salary_npr', 12, 2)->default(0.00);
            $table->decimal('hourly_rate_npr', 10, 2)->default(0.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // 3. Employees
        Schema::create('hrm_employees', function (Blueprint $table) {
            $table->id();
            $table->string('employee_number', 30)->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // Linked user account for ESS
            $table->string('first_name');
            $table->string('last_name');
            $table->text('national_id_encrypted')->nullable(); // Encrypted at rest
            $table->string('national_id_masked', 20)->nullable(); // e.g. ******-1234
            $table->string('email')->unique();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('city')->nullable();
            $table->string('country', 2)->default('NP');
            $table->foreignId('department_id')->nullable()->constrained('hrm_departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('hrm_positions')->nullOnDelete();
            $table->unsignedBigInteger('manager_id')->nullable();
            $table->enum('employment_type', ['full_time', 'part_time', 'hourly'])->default('full_time');
            $table->enum('status', ['active', 'probation', 'on_leave', 'terminated'])->default('active')->index();
            $table->date('hire_date');
            $table->date('probation_end_date')->nullable();
            $table->date('termination_date')->nullable();
            $table->string('bank_reg_number', 10)->nullable();
            $table->string('bank_account_number', 30)->nullable();
            $table->string('iban', 50)->nullable();
            $table->string('bic_swift', 20)->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('emergency_contact_relation')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Add foreign key constraint for manager after table creation
        Schema::table('hrm_departments', function (Blueprint $table) {
            $table->foreign('manager_id')->references('id')->on('hrm_employees')->nullOnDelete();
        });
        Schema::table('hrm_employees', function (Blueprint $table) {
            $table->foreign('manager_id')->references('id')->on('hrm_employees')->nullOnDelete();
        });

        // 4. Employment Contracts
        Schema::create('hrm_employment_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
            $table->enum('contract_type', ['full_time', 'part_time', 'contract', 'director'])->default('full_time');
            $table->date('start_date');
            $table->date('end_date')->nullable();
            $table->decimal('weekly_hours', 5, 2)->default(40.00);
            $table->decimal('monthly_salary_npr', 12, 2)->default(0.00);
            $table->decimal('hourly_rate_npr', 10, 2)->default(0.00);
            $table->decimal('pension_employer_rate', 5, 2)->default(8.00);
            $table->decimal('pension_employee_rate', 5, 2)->default(4.00);
            $table->string('ssf_type', 10)->default('A'); // Standard SSF
            $table->decimal('festival_allowance_rate', 5, 2)->default(8.33); // 1 month Dashain bonus / year
            $table->boolean('has_paid_lunch_break')->default(false);
            $table->text('terms_text')->nullable();
            $table->enum('status', ['draft', 'active', 'amended', 'expired', 'terminated'])->default('active')->index();
            $table->timestamps();
        });

        // 5. Timesheets & Attendance
        Schema::create('hrm_timesheets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
            $table->date('date')->index();
            $table->time('clock_in')->nullable();
            $table->time('clock_out')->nullable();
            $table->unsignedInteger('break_minutes')->default(0);
            $table->decimal('regular_hours', 6, 2)->default(0.00);
            $table->decimal('overtime_hours', 6, 2)->default(0.00);
            $table->string('location')->default('showroom_ktm'); // showroom_ktm, warehouse, remote
            $table->text('notes')->nullable();
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected'])->default('draft')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        // 6. Leave Requests & Absence
        Schema::create('hrm_leave_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
            $table->enum('leave_type', [
                'annual',          // Annual festival/earned leave
                'sickness',        // Sickness
                'casual',          // Casual leave
                'maternity',       // Maternity / Paternity
                'bereavement',     // Mourning leave
                'unpaid',          // Unpaid leave
                'other',           // Other
            ])->default('annual')->index();
            $table->date('start_date');
            $table->date('end_date');
            $table->decimal('days_count', 5, 2)->default(1.00);
            $table->boolean('is_paid')->default(true);
            $table->text('reason')->nullable();
            $table->string('doctor_note_path')->nullable();
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending')->index();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->timestamps();
        });

        // 7. Leave Balances
        Schema::create('hrm_holiday_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
            $table->unsignedSmallInteger('holiday_year'); // e.g. 2026
            $table->decimal('accrued_days', 6, 2)->default(0.00);
            $table->decimal('used_days', 6, 2)->default(0.00);
            $table->decimal('transferred_days', 6, 2)->default(0.00);
            $table->decimal('current_balance_days', 6, 2)->default(0.00);
            $table->decimal('leave_allowance_accrued_npr', 12, 2)->default(0.00);
            $table->decimal('leave_allowance_used_npr', 12, 2)->default(0.00);
            $table->timestamps();
        });

        // 8. Payroll Preparation Runs
        Schema::create('hrm_payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('run_number', 30)->unique(); // PAY-2026-09
            $table->string('name');
            $table->date('period_start');
            $table->date('period_end');
            $table->date('pay_date');
            $table->enum('status', ['draft', 'calculated', 'approved', 'posted_to_accounting', 'locked'])->default('draft')->index();
            $table->decimal('total_gross_salary_npr', 14, 2)->default(0.00);
            $table->decimal('total_ssf_employee_npr', 14, 2)->default(0.00);
            $table->decimal('total_tds_tax_npr', 14, 2)->default(0.00);
            $table->decimal('total_provident_employee_npr', 14, 2)->default(0.00);
            $table->decimal('total_provident_employer_npr', 14, 2)->default(0.00);
            $table->decimal('total_pension_employee_npr', 14, 2)->default(0.00);
            $table->decimal('total_pension_employer_npr', 14, 2)->default(0.00);
            $table->decimal('total_leave_liability_npr', 14, 2)->default(0.00);
            $table->decimal('total_net_payout_npr', 14, 2)->default(0.00);
            $table->decimal('total_employer_cost_npr', 14, 2)->default(0.00);
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 9. Payroll Run Line Items
        Schema::create('hrm_payroll_run_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained('hrm_payroll_runs')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('hrm_employees');
            $table->foreignId('contract_id')->nullable()->constrained('hrm_employment_contracts')->nullOnDelete();
            $table->decimal('base_salary_npr', 12, 2)->default(0.00);
            $table->decimal('hourly_rate_npr', 10, 2)->default(0.00);
            $table->decimal('hours_worked', 8, 2)->default(0.00);
            $table->decimal('overtime_amount_npr', 12, 2)->default(0.00);
            $table->decimal('bonus_amount_npr', 12, 2)->default(0.00);
            $table->decimal('allowance_amount_npr', 12, 2)->default(0.00);
            $table->decimal('gross_salary_npr', 12, 2)->default(0.00);
            $table->decimal('ssf_employee_npr', 12, 2)->default(0.00); // Nepal SSF Employee (11%)
            $table->decimal('taxable_income_npr', 12, 2)->default(0.00); // gross - deductions
            $table->decimal('tds_deduction_npr', 12, 2)->default(0.00); // Nepal statutory TDS
            $table->decimal('taxable_base_npr', 12, 2)->default(0.00);
            $table->decimal('tds_rate', 5, 2)->default(1.00); // Nepal IRD progressive TDS slab
            $table->decimal('tds_amount_npr', 12, 2)->default(0.00);
            $table->decimal('cit_employee_npr', 10, 2)->default(0.00);
            $table->decimal('ssf_employer_npr', 10, 2)->default(0.00); // Nepal SSF Employer (20%)
            $table->decimal('pension_employee_npr', 12, 2)->default(0.00);
            $table->decimal('pension_employer_npr', 12, 2)->default(0.00);
            $table->decimal('leave_allowance_earned_npr', 12, 2)->default(0.00);
            $table->decimal('net_salary_npr', 12, 2)->default(0.00);
            $table->decimal('total_cost_npr', 12, 2)->default(0.00);
            $table->boolean('is_reconciled')->default(false);
            $table->timestamps();
        });

        // 10. Expense Claims & Mileage
        Schema::create('hrm_expense_claims', function (Blueprint $table) {
            $table->id();
            $table->string('claim_number', 30)->unique(); // EXP-2026-001
            $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
            $table->string('title');
            $table->date('expense_date');
            $table->enum('category', [
                'travel',
                'transport_mileage',
                'packaging_supplies',
                'office',
                'meals_representation',
                'other',
            ])->default('office')->index();
            $table->foreignId('ledger_account_id')->nullable()->constrained('accounting_accounts')->nullOnDelete();
            $table->decimal('gross_amount_npr', 12, 2)->default(0.00);
            $table->decimal('vat_rate', 5, 2)->default(13.00);
            $table->decimal('vat_amount_npr', 12, 2)->default(0.00);
            $table->decimal('net_amount_npr', 12, 2)->default(0.00);
            $table->string('receipt_path')->nullable();
            $table->decimal('mileage_km', 8, 2)->nullable();
            $table->decimal('mileage_rate_npr', 6, 2)->nullable()->default(15.00); // Standard Nepal fuel allowance
            $table->enum('status', ['draft', 'submitted', 'approved', 'reimbursed', 'rejected'])->default('draft')->index();
            $table->foreignId('journal_entry_id')->nullable()->constrained('accounting_journal_entries')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('reimbursed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // 11. Recruitment Vacancies
        Schema::create('hrm_recruitment_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->foreignId('department_id')->nullable()->constrained('hrm_departments')->nullOnDelete();
            $table->foreignId('position_id')->nullable()->constrained('hrm_positions')->nullOnDelete();
            $table->enum('employment_type', ['full_time', 'part_time', 'hourly'])->default('full_time');
            $table->string('location')->default('Kathmandu Central Showroom');
            $table->enum('status', ['draft', 'published', 'closed'])->default('published')->index();
            $table->date('deadline')->nullable();
            $table->text('description')->nullable();
            $table->text('requirements')->nullable();
            $table->timestamps();
        });

        // 12. Recruitment Candidates & Pipeline
        Schema::create('hrm_recruitment_applicants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')->constrained('hrm_recruitment_jobs')->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone')->nullable();
            $table->string('resume_path')->nullable();
            $table->string('cover_letter_path')->nullable();
            $table->enum('stage', ['applied', 'screening', 'interview_1', 'interview_2', 'offer', 'hired', 'rejected'])->default('applied')->index();
            $table->unsignedTinyInteger('rating')->nullable()->default(3); // 1-5
            $table->text('notes')->nullable();
            $table->foreignId('hired_employee_id')->nullable()->constrained('hrm_employees')->nullOnDelete();
            $table->timestamps();
        });

        // 13. Employee Documents & Vault
        Schema::create('hrm_employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
            $table->string('title');
            $table->enum('document_type', [
                'contract',
                'amendment',
                'certification',
                'id_copy',
                'policy_acknowledgement',
                'other',
            ])->default('contract')->index();
            $table->string('file_path');
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable()->index();
            $table->boolean('is_confidential')->default(true);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // 14. Performance Reviews
        Schema::create('hrm_performance_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('hrm_employees')->cascadeOnDelete();
            $table->foreignId('reviewer_id')->constrained('hrm_employees');
            $table->enum('review_type', ['annual', 'probation', 'quarterly'])->default('annual');
            $table->date('review_date');
            $table->unsignedTinyInteger('overall_rating')->default(3); // 1-5
            $table->text('strengths')->nullable();
            $table->text('growth_areas')->nullable();
            $table->json('goals_json')->nullable();
            $table->text('notes')->nullable();
            $table->enum('status', ['scheduled', 'completed', 'signed'])->default('scheduled')->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hrm_performance_reviews');
        Schema::dropIfExists('hrm_employee_documents');
        Schema::dropIfExists('hrm_recruitment_applicants');
        Schema::dropIfExists('hrm_recruitment_jobs');
        Schema::dropIfExists('hrm_expense_claims');
        Schema::dropIfExists('hrm_payroll_run_items');
        Schema::dropIfExists('hrm_payroll_runs');
        Schema::dropIfExists('hrm_holiday_balances');
        Schema::dropIfExists('hrm_leave_requests');
        Schema::dropIfExists('hrm_timesheets');
        Schema::dropIfExists('hrm_employment_contracts');

        // Drop foreign keys on employees & departments before dropping them
        Schema::table('hrm_employees', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });
        Schema::table('hrm_departments', function (Blueprint $table) {
            $table->dropForeign(['manager_id']);
        });

        Schema::dropIfExists('hrm_employees');
        Schema::dropIfExists('hrm_positions');
        Schema::dropIfExists('hrm_departments');
    }
};
