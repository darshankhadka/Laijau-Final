<?php

namespace Tests\Feature;

use App\Models\Accounting\Account;
use App\Models\Accounting\BankAccount;
use App\Models\Accounting\JournalEntry;
use App\Models\Hrm\Employee;
use App\Models\Hrm\PayrollRun;
use App\Models\Hrm\PayrollRunItem;
use App\Models\Hrm\PayrollTaxConfiguration;
use App\Models\Hrm\Timesheet;
use App\Models\User;
use App\Services\Accounting\AccountingService;
use App\Services\Hrm\HrmService;
use App\Services\Hrm\PayrollPreparationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NepalPeopleHrmOperationalTest extends TestCase
{
    use RefreshDatabase;

    protected AccountingService $accountingService;
    protected PayrollPreparationService $payrollService;
    protected HrmService $hrmService;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountingService = app(AccountingService::class);
        $this->payrollService = app(PayrollPreparationService::class);
        $this->hrmService = app(HrmService::class);

        $this->accountingService->ensureDefaultChartOfAccounts();
        $this->payrollService->seedNepalPayrollTaxConfigurations();

        $this->adminUser = User::firstOrCreate(
            ['email' => 'hr.admin@laijau.com'],
            [
                'name' => 'HR Admin',
                'password' => bcrypt('password'),
                'role' => 'admin',
            ]
        );
    }

    /**
     * Test 1: Data-driven Nepal payroll rules versioned by fiscal year (FY 2083/84).
     */
    public function test_seeds_and_resolves_nepal_fiscal_year_payroll_and_tax_rules(): void
    {
        $config = $this->payrollService->resolvePayrollTaxConfiguration('2083/84');

        $this->assertEquals('2083/84', $config->fiscal_year);
        $this->assertEquals(11.00, (float)$config->ssf_employee_rate);
        $this->assertEquals(20.00, (float)$config->ssf_employer_rate);
        $this->assertEquals(500000.00, (float)$config->single_slab_1_limit);
        $this->assertEquals(600000.00, (float)$config->married_slab_1_limit);

        // Test Single TDS calculation for annual taxable income of Rs. 800,000 with SSF
        // Slab 1: first 500k @ 0% (waived because SSF enrolled)
        // Slab 2: next 200k @ 10% = 20,000
        // Slab 3: remaining 100k @ 20% = 20,000
        // Total Annual = 40,000 -> Monthly = 3,333.33
        $singleTds = $config->calculateTds(800000.00, 'single', true);
        $this->assertEquals(40000.00, $singleTds['annual_total_tax']);
        $this->assertEquals(3333.33, $singleTds['monthly_tds_tax']);

        // Test Married TDS calculation for annual taxable income of Rs. 800,000 with SSF
        // Slab 1: first 600k @ 0% (waived because SSF enrolled)
        // Slab 2: next 200k @ 10% = 20,000
        // Total Annual = 20,000 -> Monthly = 1,666.67
        $marriedTds = $config->calculateTds(800000.00, 'married', true);
        $this->assertEquals(20000.00, $marriedTds['annual_total_tax']);
        $this->assertEquals(1666.67, $marriedTds['monthly_tds_tax']);
    }

    /**
     * Test 2: Employee joins with Nepal PAN, statutory profile, and salary assignment.
     */
    public function test_employee_joins_with_nepal_pan_salary_and_ssf_profile(): void
    {
        $employee = Employee::create([
            'employee_number' => Employee::generateNextEmployeeNumber(),
            'first_name' => 'Ram',
            'last_name' => 'Shrestha',
            'email' => 'ram.shrestha@laijau.com',
            'phone' => '+977 9851012345',
            'pan_number' => '102938475',
            'citizenship_number' => '27-01-78-12345',
            'marital_status' => 'married',
            'branch_location' => 'Kathmandu Showroom (Durbar Marg)',
            'basic_salary' => 40000.00,
            'allowance_amount' => 15000.00,
            'gross_salary' => 55000.00,
            'ssf_enrolled' => true,
            'ssf_number' => 'SSF-990011',
            'bank_name' => 'Nabil Bank Ltd.',
            'bank_branch' => 'Durbar Marg Branch',
            'bank_account_number' => '01201017500999',
            'bank_account_name' => 'Ram Shrestha',
            'status' => 'active',
            'hire_date' => '2026-08-01',
        ]);

        $this->assertNotEmpty($employee->employee_number);
        $this->assertTrue(str_starts_with($employee->employee_number, 'LAI-') || str_starts_with($employee->employee_number, 'EMP-'));
        $this->assertEquals('102938475', $employee->pan_number);
        $this->assertEquals('married', $employee->marital_status);
        $this->assertEquals(40000.00, (float)$employee->basic_salary);
        $this->assertTrue((bool)$employee->ssf_enrolled);
    }

    /**
     * Test 3 & 4: Mobile clock-in and clock-out with GPS, shift detection, and overtime.
     */
    public function test_mobile_clock_in_out_and_overtime_accumulation(): void
    {
        $employee = Employee::firstOrCreate(
            ['email' => 'bipin@laijau.com'],
            [
                'employee_number' => Employee::generateNextEmployeeNumber(),
                'first_name' => 'Bipin',
                'last_name' => 'Adhikari',
                'basic_salary' => 30000.00,
                'status' => 'active',
                'hire_date' => '2026-08-01',
            ]
        );

        // Mobile Clock-In at 10:00 AM
        $punchIn = $this->hrmService->clockIn($employee, [
            'date' => '2026-09-11',
            'time' => '10:00:00',
            'latitude' => 27.7123,
            'longitude' => 85.3189,
            'accuracy' => 12.5,
            'shift_name' => 'Showroom Retail Shift',
            'shift_start_time' => '10:00:00',
            'shift_end_time' => '19:00:00',
        ]);

        $this->assertEquals('submitted', $punchIn->status);
        $this->assertEquals('present', $punchIn->attendance_status);
        $this->assertFalse((bool)$punchIn->is_late);

        // Mobile Clock-Out at 20:00 PM (10 hours elapsed with 1 hr break = 9 worked hours = 8 regular + 1 overtime)
        $punchOut = $this->hrmService->clockOut($employee, [
            'date' => '2026-09-11',
            'time' => '20:00:00',
            'latitude' => 27.7123,
            'longitude' => 85.3189,
            'accuracy' => 10.0,
            'break_minutes' => 60,
        ]);

        $this->assertEquals(8.00, (float)$punchOut->regular_hours);
        $this->assertEquals(1.00, (float)$punchOut->overtime_hours);

        // Manager Approves Timesheet
        $punchOut->update(['status' => 'approved', 'approved_by' => $this->adminUser->id, 'approved_at' => now()]);
        $this->assertEquals('approved', $punchOut->status);
    }

    /**
     * Test 5: Monthly payroll generation integrating attendance, SSF, and Section 87 TDS.
     */
    public function test_monthly_payroll_generation_with_attendance_ssf_and_tds(): void
    {
        $employee = Employee::firstOrCreate(
            ['email' => 'priya@laijau.com'],
            [
                'employee_number' => Employee::generateNextEmployeeNumber(),
                'first_name' => 'Priya',
                'last_name' => 'Khadka',
                'pan_number' => '987654321',
                'marital_status' => 'single',
                'basic_salary' => 30000.00,
                'allowance_amount' => 10000.00,
                'ssf_enrolled' => true,
                'status' => 'active',
                'hire_date' => '2026-08-01',
            ]
        );

        // Log 2 approved timesheets with 2 overtime hours
        Timesheet::create([
            'employee_id' => $employee->id,
            'date' => '2026-09-02',
            'clock_in' => '10:00:00',
            'clock_out' => '20:00:00',
            'break_minutes' => 60,
            'regular_hours' => 8.00,
            'overtime_hours' => 1.00,
            'status' => 'approved',
            'attendance_status' => 'present',
        ]);
        Timesheet::create([
            'employee_id' => $employee->id,
            'date' => '2026-09-03',
            'clock_in' => '10:00:00',
            'clock_out' => '20:00:00',
            'break_minutes' => 60,
            'regular_hours' => 8.00,
            'overtime_hours' => 1.00,
            'status' => 'approved',
            'attendance_status' => 'present',
        ]);

        $run = $this->payrollService->createPayrollRun(
            'Salary — September 2026',
            '2026-09-01',
            '2026-09-30',
            '2026-09-30',
            '2083/84',
            $this->adminUser
        );

        $this->assertGreaterThanOrEqual(1, $run->items->count());
        $item = $run->items->where('employee_id', $employee->id)->first();
        $this->assertNotNull($item);

        $this->assertEquals(30000.00, (float)$item->basic_salary);
        $this->assertEquals(10000.00, (float)$item->allowance_amount);
        $this->assertEquals(2.00, (float)$item->overtime_hours);
        $this->assertGreaterThan(0.0, (float)$item->overtime_amount);

        // SSF Employee (11% on basic 30,000) = 3,300
        $this->assertEquals(3300.00, (float)$item->ssf_employee_amount);
        // SSF Employer (20% on basic 30,000) = 6,000
        $this->assertEquals(6000.00, (float)$item->ssf_employer_amount);

        // Gross = 30,000 + 10,000 + overtime (~432.69)
        $this->assertGreaterThanOrEqual(40400.00, (float)$item->gross_salary);

        // Net salary strictly equals Gross - SSF Employee - TDS
        $expectedNet = round($item->gross_salary - $item->ssf_employee_amount - $item->tds_tax_amount, 2);
        $this->assertEquals($expectedNet, (float)$item->net_salary);

        // Verify Payslip reference generated
        $this->assertNotNull($item->payslip_number);
        $this->assertStringStartsWith('SLIP-208384-', $item->payslip_number);
    }

    /**
     * Test 6: Payroll approval and balanced General Ledger voucher posting.
     */
    public function test_payroll_approval_and_double_entry_gl_posting(): void
    {
        $employee = Employee::firstOrCreate(
            ['email' => 'suman@laijau.com'],
            [
                'employee_number' => Employee::generateNextEmployeeNumber(),
                'first_name' => 'Suman',
                'last_name' => 'Giri',
                'basic_salary' => 50000.00,
                'allowance_amount' => 20000.00,
                'ssf_enrolled' => true,
                'status' => 'active',
                'hire_date' => '2026-08-01',
            ]
        );

        $run = $this->payrollService->createPayrollRun(
            'Salary — Bhadra 2083',
            '2026-08-17',
            '2026-09-16',
            '2026-09-16',
            '2083/84',
            $this->adminUser
        );

        $entry = $this->payrollService->postPayrollToAccounting($run, $this->adminUser);

        $this->assertInstanceOf(JournalEntry::class, $entry);
        $this->assertTrue((bool)$entry->is_balanced);
        $this->assertEquals('posted_to_accounting', $run->fresh()->status);
        $this->assertEquals($entry->id, $run->fresh()->journal_entry_id);

        // Verify debits = credits
        $totalDebit = (float)$entry->lines()->sum('debit');
        $totalCredit = (float)$entry->lines()->sum('credit');
        $this->assertEquals(round($totalDebit, 2), round($totalCredit, 2));

        // Verify Expense Accounts debited: 6120/1810 & 6130/1830
        $debitAccounts = $entry->lines()->where('debit', '>', 0)->pluck('account_number')->toArray();
        $this->assertTrue(in_array('6120', $debitAccounts) || in_array('1810', $debitAccounts));
        $this->assertTrue(in_array('6130', $debitAccounts) || in_array('1830', $debitAccounts));
    }

    /**
     * Test 7: Net salary disbursement via Bank Account and Bank Reconciliation sync.
     */
    public function test_salary_disbursement_payout_and_bank_reconciliation_sync(): void
    {
        $bankAccount = BankAccount::firstOrCreate(
            ['name' => 'Nabil Bank Operating Account NPR'],
            [
                'bank_name' => 'Nabil Bank Ltd.',
                'account_number' => '01201017500123',
                'currency' => 'NPR',
                'current_balance' => 500000.00,
            ]
        );
        $bankAccount->update(['current_balance' => 500000.00]);

        $employee = Employee::firstOrCreate(
            ['email' => 'aarav@laijau.com'],
            [
                'employee_number' => Employee::generateNextEmployeeNumber(),
                'first_name' => 'Aarav',
                'last_name' => 'Thapa',
                'basic_salary' => 35000.00,
                'allowance_amount' => 10000.00,
                'ssf_enrolled' => true,
                'status' => 'active',
                'hire_date' => '2026-08-01',
            ]
        );

        $run = $this->payrollService->createPayrollRun(
            'Salary — August 2026',
            '2026-08-01',
            '2026-08-31',
            '2026-08-31',
            '2083/84',
            $this->adminUser
        );

        $this->payrollService->postPayrollToAccounting($run, $this->adminUser);

        $netPayout = (float)$run->net_salary;
        $initialBalance = (float)$bankAccount->current_balance;

        $payoutEntry = $this->payrollService->recordSalaryDisbursement(
            $run,
            $bankAccount,
            $this->adminUser,
            'NCHL-SAL-2083-05'
        );

        $this->assertInstanceOf(JournalEntry::class, $payoutEntry);
        $this->assertTrue((bool)$payoutEntry->is_balanced);
        $this->assertEquals('paid', $run->fresh()->status);
        $this->assertEquals($payoutEntry->id, $run->fresh()->payout_journal_entry_id);

        // Bank balance decreased
        $this->assertEquals(round($initialBalance - $netPayout, 2), round((float)$bankAccount->fresh()->current_balance, 2));

        // Bank transaction created for Bank Reconciliation
        $this->assertDatabaseHas('accounting_bank_transactions', [
            'bank_account_id' => $bankAccount->id,
            'external_reference' => 'NCHL-SAL-2083-05',
        ]);
    }

    /**
     * Test 8: Mobile-First Portal HTTP endpoints and statutory printable payslip.
     */
    public function test_mobile_portal_endpoints_and_payslip_view(): void
    {
        $employee = Employee::firstOrCreate(
            ['email' => 'maya@laijau.com'],
            [
                'employee_number' => Employee::generateNextEmployeeNumber(),
                'first_name' => 'Maya',
                'last_name' => 'Gurung',
                'pan_number' => '554433221',
                'marital_status' => 'single',
                'basic_salary' => 28000.00,
                'status' => 'active',
                'hire_date' => '2026-08-01',
            ]
        );

        $this->actingAs($this->adminUser, 'admin');

        // GET /hrm/portal
        $response = $this->get(route('hrm.portal', ['employee_id' => $employee->id]));
        $response->assertStatus(200);
        $response->assertSee('LAIJAU HR');
        $response->assertSee('Maya');
        $response->assertSee('CLOCK IN');

        // POST /hrm/clock-in
        $clockInResponse = $this->post(route('hrm.clock_in', ['employee_id' => $employee->id]), [
            'latitude' => 27.7123,
            'longitude' => 85.3189,
        ]);
        $clockInResponse->assertRedirect();

        $this->assertDatabaseHas('hrm_timesheets', [
            'employee_id' => $employee->id,
            'attendance_status' => 'present',
        ]);

        // POST /hrm/clock-out
        $clockOutResponse = $this->post(route('hrm.clock_out', ['employee_id' => $employee->id]), [
            'break_minutes' => 60,
        ]);
        $clockOutResponse->assertRedirect();

        // Generate payslip and test view
        $run = $this->payrollService->createPayrollRun(
            'Salary — Sept 2026',
            '2026-09-01',
            '2026-09-30',
            '2026-09-30',
            '2083/84',
            $this->adminUser
        );
        $item = $run->items->where('employee_id', $employee->id)->first();

        $payslipResponse = $this->get(route('hrm.payslip', $item->id));
        $payslipResponse->assertStatus(200);
        $payslipResponse->assertSeeText('LAIJAU');
        $payslipResponse->assertSeeText('Delta Nine business group');
        $payslipResponse->assertSee('Maya Gurung');
        $payslipResponse->assertSee('554433221');
        $payslipResponse->assertSee('Net Take Home Salary');
    }

    /**
     * Test 9: Complete Synthetic Employee Lifecycle:
     * Create employee -> assign salary -> assign shift -> mobile clock-in -> clock-out ->
     * late arrival -> overtime -> absence -> generate payroll -> calculate TDS/SSF ->
     * approve payroll -> generate payslip -> post GL -> pay salary -> reconcile bank.
     */
    public function test_complete_synthetic_employee_lifecycle_end_to_end(): void
    {
        // 1. Create Employee
        $employee = Employee::firstOrCreate(
            ['email' => 'devendra.adhikari@laijau.com'],
            [
                'employee_number' => Employee::generateNextEmployeeNumber(),
                'first_name' => 'Devendra',
                'last_name' => 'Adhikari',
                'phone' => '+977 9841998877',
                'pan_number' => '109283746',
                'citizenship_number' => '27-01-79-99887',
                'marital_status' => 'married',
                'branch_location' => 'Kathmandu Showroom (Durbar Marg)',
                'status' => 'active',
                'hire_date' => '2026-08-01',
            ]
        );
        $this->assertNotEmpty($employee->employee_number);
        $this->assertTrue(str_starts_with($employee->employee_number, 'LAI-') || str_starts_with($employee->employee_number, 'EMP-'));

        // 2. Assign Salary Structure & Bank Details
        $employee->update([
            'basic_salary' => 35000.00,
            'allowance_amount' => 15000.00,
            'gross_salary' => 50000.00,
            'ssf_enrolled' => true,
            'ssf_number' => 'SSF-109283',
            'bank_name' => 'Nabil Bank Ltd.',
            'bank_branch' => 'Teendhara Branch',
            'bank_account_number' => '01201017500123',
            'bank_account_name' => 'Devendra Adhikari',
        ]);
        $this->assertEquals(35000.00, (float)$employee->fresh()->basic_salary);

        // 3. Assign Shift & Day 1: Mobile Clock-In on time & Clock-Out with 1 hr Overtime
        $shiftName = 'Showroom Retail Shift';
        $shiftStart = '10:00:00';
        $shiftEnd = '19:00:00';

        $day1PunchIn = $this->hrmService->clockIn($employee, [
            'date' => '2026-09-01',
            'time' => '10:00:00',
            'latitude' => 27.7123,
            'longitude' => 85.3189,
            'accuracy' => 10.0,
            'shift_name' => $shiftName,
            'shift_start_time' => $shiftStart,
            'shift_end_time' => $shiftEnd,
        ]);
        $this->assertEquals('present', $day1PunchIn->attendance_status);
        $this->assertFalse((bool)$day1PunchIn->is_late);

        $day1PunchOut = $this->hrmService->clockOut($employee, [
            'date' => '2026-09-01',
            'time' => '20:00:00', // 10 hrs elapsed - 1 hr break = 9 worked hrs (8 regular + 1 overtime)
            'latitude' => 27.7123,
            'longitude' => 85.3189,
            'break_minutes' => 60,
        ]);
        $this->assertEquals(8.00, (float)$day1PunchOut->regular_hours);
        $this->assertEquals(1.00, (float)$day1PunchOut->overtime_hours);
        $day1PunchOut->update(['status' => 'approved', 'approved_by' => $this->adminUser->id]);

        // 4. Day 2: Mobile Clock-In with Late Arrival (10:35 AM -> 35 mins late)
        $day2PunchIn = $this->hrmService->clockIn($employee, [
            'date' => '2026-09-02',
            'time' => '10:35:00',
            'latitude' => 27.7123,
            'longitude' => 85.3189,
            'shift_name' => $shiftName,
            'shift_start_time' => $shiftStart,
            'shift_end_time' => $shiftEnd,
        ]);
        $this->assertTrue((bool)$day2PunchIn->is_late);
        $this->assertEquals(35, (int)$day2PunchIn->late_minutes);

        $day2PunchOut = $this->hrmService->clockOut($employee, [
            'date' => '2026-09-02',
            'time' => '19:00:00',
            'break_minutes' => 60,
        ]);
        $day2PunchOut->update(['status' => 'approved', 'approved_by' => $this->adminUser->id]);

        // 5. Day 3: Absence Record
        $day3Absence = Timesheet::create([
            'employee_id' => $employee->id,
            'date' => '2026-09-03',
            'shift_name' => $shiftName,
            'attendance_status' => 'absent',
            'regular_hours' => 0.00,
            'overtime_hours' => 0.00,
            'notes' => 'Unexcused Absence',
            'status' => 'approved',
        ]);
        $this->assertEquals('absent', $day3Absence->attendance_status);

        // 6. Generate Monthly Payroll Run under FY 2083/84
        $run = $this->payrollService->createPayrollRun(
            'Salary — Ashwin 2083',
            '2026-09-01',
            '2026-09-30',
            '2026-09-30',
            '2083/84',
            $this->adminUser
        );
        $this->assertContains($run->status, ['calculated', 'posted_to_accounting', 'approved']);

        $item = $run->items()->where('employee_id', $employee->id)->first();
        $this->assertNotNull($item);

        // Verify Basic & Allowances
        $this->assertEquals(35000.00, (float)$item->basic_salary);
        $this->assertEquals(15000.00, (float)$item->allowance_amount);

        // Verify Overtime Pay: 1 hr OT @ 1.5x hourly rate
        $this->assertEquals(1.00, (float)$item->overtime_hours);
        $this->assertGreaterThan(0.00, (float)$item->overtime_amount);

        // Verify SSF: Employee 11% (Rs. 3,850) & Employer 20% (Rs. 7,000) on Rs. 35,000 basic
        $this->assertEquals(3850.00, (float)$item->ssf_employee_amount);
        $this->assertEquals(7000.00, (float)$item->ssf_employer_amount);

        // 7. Approve Payroll
        $run->update(['status' => 'approved', 'approved_by' => $this->adminUser->id, 'approved_at' => now()]);
        $this->assertContains($run->status, ['calculated', 'posted_to_accounting', 'approved']);

        // 8. Verify Payslip Generation
        $this->assertNotNull($item->payslip_number);
        $this->assertStringStartsWith('SLIP-208384-', $item->payslip_number);

        $payslipView = $this->actingAs($this->adminUser, 'admin')->get(route('hrm.payslip', $item->id));
        $payslipView->assertStatus(200);
        $payslipView->assertSeeText('Devendra Adhikari');
        $payslipView->assertSeeText('109283746');
        $payslipView->assertSeeText('SSF-109283');

        // 9. Post Payroll Voucher to General Ledger
        $glEntry = $this->payrollService->postPayrollToAccounting($run, $this->adminUser);
        $this->assertInstanceOf(JournalEntry::class, $glEntry);
        $this->assertTrue((bool)$glEntry->is_balanced);
        $this->assertEquals('posted_to_accounting', $run->fresh()->status);

        // Verify GL debit and credit balance
        $totalDebit = (float)$glEntry->lines()->sum('debit');
        $totalCredit = (float)$glEntry->lines()->sum('credit');
        $this->assertEquals(round($totalDebit, 2), round($totalCredit, 2));

        // 10. Pay Salary via Bank Account
        $bankAccount = BankAccount::firstOrCreate(
            ['name' => 'Nabil Bank Operating Account NPR'],
            [
                'bank_name' => 'Nabil Bank Ltd.',
                'account_number' => '01201017500123',
                'currency' => 'NPR',
                'current_balance' => 300000.00,
            ]
        );
        $bankAccount->update(['current_balance' => 300000.00]);
        $initialBankBalance = (float)$bankAccount->current_balance;

        $payoutVoucher = $this->payrollService->recordSalaryDisbursement(
            $run,
            $bankAccount,
            $this->adminUser,
            'NCHL-SAL-DEVENDRA-01'
        );
        $this->assertInstanceOf(JournalEntry::class, $payoutVoucher);
        $this->assertTrue((bool)$payoutVoucher->is_balanced);
        $this->assertEquals('paid', $run->fresh()->status);

        // 11. Reconcile Bank
        $netDisbursed = (float)$run->net_salary;
        $this->assertEquals(round($initialBankBalance - $netDisbursed, 2), round((float)$bankAccount->fresh()->current_balance, 2));

        $this->assertDatabaseHas('accounting_bank_transactions', [
            'bank_account_id' => $bankAccount->id,
            'external_reference' => 'NCHL-SAL-DEVENDRA-01',
            'amount' => -$netDisbursed,
            'is_reconciled' => true,
        ]);
    }
}

