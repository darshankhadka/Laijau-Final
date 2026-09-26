<?php

namespace App\Services\Hrm;

use App\Models\Hrm\Department;
use App\Models\Hrm\Employee;
use App\Models\Hrm\EmployeeDocument;
use App\Models\Hrm\EmploymentContract;
use App\Models\Hrm\ExpenseClaim;
use App\Models\Hrm\HolidayBalance;
use App\Models\Hrm\LeaveRequest;
use App\Models\Hrm\Position;
use App\Models\Hrm\RecruitmentApplicant;
use App\Models\Hrm\RecruitmentJob;
use App\Models\Hrm\Timesheet;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use App\Services\Settings\SettingsService;

class HrmService
{
    protected SettingsService $settingsService;

    public function __construct(?SettingsService $settingsService = null)
    {
        $this->settingsService = $settingsService ?? app(SettingsService::class);
    }
    /**
     * Seed default organizational structure for Laijau (Nepal).
     */
    public function seedDefaultOrganization(): void
    {
        DB::transaction(function () {
            // 1. Departments
            $depts = [
                ['name' => 'Management & Strategy', 'code' => 'MGMT', 'location' => 'Kathmandu HQ, Nepal'],
                ['name' => 'Laijau Showroom', 'code' => 'SHOWROOM', 'location' => 'Laijau Showroom, Kathmandu, Nepal'],
                ['name' => 'E-Commerce & Digital Marketing', 'code' => 'ECOM', 'location' => 'Kathmandu HQ, Nepal'],
                ['name' => 'Quality Control & Inspection', 'code' => 'QC', 'location' => 'Kathmandu Inspection Hub'],
                ['name' => 'Warehouse & Logistics', 'code' => 'LOGISTICS', 'location' => 'Kathmandu Fulfillment Hub'],
                ['name' => 'Finance & HR', 'code' => 'FINHR', 'location' => 'Kathmandu HQ, Nepal'],
            ];

            $deptModels = [];
            foreach ($depts as $d) {
                $deptModels[$d['code']] = Department::firstOrCreate(
                    ['code' => $d['code']],
                    ['name' => $d['name'], 'location' => $d['location'], 'is_active' => true]
                );
            }

            // 2. Positions
            $positions = [
                ['department_id' => $deptModels['MGMT']->id, 'title' => 'Managing Director & Founder', 'code' => 'DIR-01', 'employment_type' => 'full_time', 'min_salary_npr' => 75000, 'max_salary_npr' => 150000],
                ['department_id' => $deptModels['SHOWROOM']->id, 'title' => 'Showroom Manager', 'code' => 'SH-MGR', 'employment_type' => 'full_time', 'min_salary_npr' => 45000, 'max_salary_npr' => 65000],
                ['department_id' => $deptModels['SHOWROOM']->id, 'title' => 'Showroom Stylist & Sales Assistant', 'code' => 'SH-STYLIST', 'employment_type' => 'hourly', 'hourly_rate_npr' => 350.00],
                ['department_id' => $deptModels['ECOM']->id, 'title' => 'E-Commerce & Digital Brand Manager', 'code' => 'ECOM-MGR', 'employment_type' => 'full_time', 'min_salary_npr' => 50000, 'max_salary_npr' => 75000],
                ['department_id' => $deptModels['QC']->id, 'title' => 'Senior Quality & Packaging Specialist', 'code' => 'QC-SR', 'employment_type' => 'full_time', 'min_salary_npr' => 40000, 'max_salary_npr' => 60000],
                ['department_id' => $deptModels['LOGISTICS']->id, 'title' => 'Inventory & Logistics Specialist', 'code' => 'LOG-SPEC', 'employment_type' => 'part_time', 'hourly_rate_npr' => 300.00],
            ];

            foreach ($positions as $p) {
                Position::firstOrCreate(
                    ['code' => $p['code']],
                    $p
                );
            }

            // 3. Seed demo staff if table is empty
            if (Employee::count() === 0) {
                $this->seedInitialStaff($deptModels);
            }
        });
    }

    /**
     * Seed initial showroom & HQ staff.
     */
    protected function seedInitialStaff(array $deptModels): void
    {
        $dirPos = Position::where('code', 'DIR-01')->first();
        $shMgrPos = Position::where('code', 'SH-MGR')->first();
        $stylistPos = Position::where('code', 'SH-STYLIST')->first();
        $qcPos = Position::where('code', 'QC-SR')->first();

        // 1. Director
        $e1 = Employee::create([
            'employee_number' => 'MED-0001',
            'first_name' => 'Aarav',
            'last_name' => 'Sharma',
            'email' => 'aarav@laijau.com',
            'phone' => '+977 9801234567',
            'address' => 'Durbarmarg, Ward 1',
            'postal_code' => '44600',
            'city' => 'Kathmandu',
            'country' => 'NP',
            'department_id' => $deptModels['MGMT']->id,
            'position_id' => $dirPos?->id,
            'employment_type' => 'full_time',
            'status' => 'active',
            'hire_date' => '2024-01-01',
            'bank_reg_number' => 'NABIL',
            'bank_account_number' => '01000123456789',
            'emergency_contact_name' => 'Priya Sharma',
            'emergency_contact_phone' => '+977 9801998877',
            'emergency_contact_relation' => 'Spouse',
        ]);
        $e1->save();

        EmploymentContract::create([
            'employee_id' => $e1->id,
            'contract_type' => 'executive',
            'start_date' => '2024-01-01',
            'weekly_hours' => 40.00,
            'monthly_salary_npr' => 85000.00,
            'pension_employer_rate' => 10.00,
            'pension_employee_rate' => 10.00,
            'ssf_type' => 'A',
            'festival_allowance_rate' => 8.33,
            'status' => 'active',
        ]);

        $this->ensureHolidayBalance($e1, (int)date('Y'));

        // 2. Showroom Manager
        $e2 = Employee::create([
            'employee_number' => 'MED-0002',
            'first_name' => 'Sunita',
            'last_name' => 'Adhikari',
            'email' => 'sunita@laijau.com',
            'phone' => '+977 9841234567',
            'address' => 'New Road, Ward 22',
            'postal_code' => '44600',
            'city' => 'Kathmandu',
            'country' => 'NP',
            'department_id' => $deptModels['SHOWROOM']->id,
            'position_id' => $shMgrPos?->id,
            'manager_id' => $e1->id,
            'employment_type' => 'full_time',
            'status' => 'active',
            'hire_date' => '2024-06-01',
            'bank_reg_number' => 'NABIL',
            'bank_account_number' => '01000234567890',
            'emergency_contact_name' => 'Ramesh Adhikari',
            'emergency_contact_phone' => '+977 9841990011',
            'emergency_contact_relation' => 'Parent',
        ]);
        $e2->save();

        EmploymentContract::create([
            'employee_id' => $e2->id,
            'contract_type' => 'salaried_staff',
            'start_date' => '2024-06-01',
            'weekly_hours' => 40.00,
            'monthly_salary_npr' => 55000.00,
            'pension_employer_rate' => 10.00,
            'pension_employee_rate' => 10.00,
            'ssf_type' => 'A',
            'festival_allowance_rate' => 8.33,
            'status' => 'active',
        ]);

        $this->ensureHolidayBalance($e2, (int)date('Y'));

        // 3. Showroom Stylist
        $e3 = Employee::create([
            'employee_number' => 'MED-0003',
            'first_name' => 'Bipin',
            'last_name' => 'Shrestha',
            'email' => 'bipin@laijau.com',
            'phone' => '+977 9811234567',
            'address' => 'Thamel, Ward 26',
            'postal_code' => '44600',
            'city' => 'Kathmandu',
            'country' => 'NP',
            'department_id' => $deptModels['SHOWROOM']->id,
            'position_id' => $stylistPos?->id,
            'manager_id' => $e2->id,
            'employment_type' => 'hourly',
            'status' => 'active',
            'hire_date' => '2025-02-01',
            'bank_reg_number' => 'NABIL',
            'bank_account_number' => '01000345678901',
        ]);
        $e3->save();

        EmploymentContract::create([
            'employee_id' => $e3->id,
            'contract_type' => 'hourly_wage',
            'start_date' => '2025-02-01',
            'weekly_hours' => 20.00,
            'hourly_rate_npr' => 350.00,
            'pension_employer_rate' => 0.00,
            'pension_employee_rate' => 0.00,
            'ssf_type' => 'C',
            'festival_allowance_rate' => 0.00,
            'status' => 'active',
        ]);

        $this->ensureHolidayBalance($e3, (int)date('Y'));

        // Update department managers
        $deptModels['MGMT']->update(['manager_id' => $e1->id]);
        $deptModels['SHOWROOM']->update(['manager_id' => $e2->id]);
    }

    /**
     * Ensure an employee has a HolidayBalance record for the given holiday year.
     * Statutory leave accrual per Nepal Labour Act (2074).
     */
    public function ensureHolidayBalance(Employee $employee, int $year): HolidayBalance
    {
        $annualDays = $this->settingsService->getDecimal('hrm', 'holiday_annual_entitlement_days', 18.00);

        $balance = HolidayBalance::firstOrCreate(
            [
                'employee_id' => $employee->id,
                'holiday_year' => $year,
            ],
            [
                'accrued_days' => $annualDays,
                'used_days' => 0.00,
                'transferred_days' => 0.00,
                'current_balance_days' => $annualDays,
                'leave_allowance_accrued_npr' => 0.00,
                'leave_allowance_used_npr' => 0.00,
            ]
        );

        $balance->recalculate();
        $balance->save();

        return $balance;
    }

    /**
     * Accrue monthly leave for active employees under Nepal Labour Act (2074).
     */
    public function processMonthlyHolidayAccrual(int $year): void
    {
        $monthlyAccrual = $this->settingsService->getDecimal('hrm', 'holiday_monthly_accrual_days', 1.50);
        $employees = Employee::where('status', 'active')->get();
        foreach ($employees as $emp) {
            $balance = $this->ensureHolidayBalance($emp, $year);
            $balance->accrued_days += $monthlyAccrual;
            $balance->recalculate();
            $balance->save();
        }
    }

    /**
     * Convert a recruited applicant to an Employee.
     */
    public function convertApplicantToEmployee(
        RecruitmentApplicant $applicant,
        array $employeeData,
        ?array $contractData = null
    ): Employee {
        return DB::transaction(function () use ($applicant, $employeeData, $contractData) {
            $employeeNumber = Employee::generateNextEmployeeNumber();

            $employee = new Employee();
            $employee->employee_number = $employeeNumber;
            $employee->first_name = $employeeData['first_name'] ?? $applicant->first_name;
            $employee->last_name = $employeeData['last_name'] ?? $applicant->last_name;
            $employee->email = $employeeData['email'] ?? $applicant->email;
            $employee->phone = $employeeData['phone'] ?? $applicant->phone;
            $employee->address = $employeeData['address'] ?? null;
            $employee->postal_code = $employeeData['postal_code'] ?? null;
            $employee->city = $employeeData['city'] ?? null;
            $employee->country = $employeeData['country'] ?? 'NP';
            $employee->department_id = $employeeData['department_id'] ?? $applicant->job?->department_id;
            $employee->position_id = $employeeData['position_id'] ?? $applicant->job?->position_id;
            $employee->manager_id = $employeeData['manager_id'] ?? null;
            $employee->employment_type = $employeeData['employment_type'] ?? 'full_time';
            $employee->status = 'active';
            $employee->hire_date = $employeeData['hire_date'] ?? date('Y-m-d');
            $employee->bank_reg_number = $employeeData['bank_reg_number'] ?? null;
            $employee->bank_account_number = $employeeData['bank_account_number'] ?? null;

            if (!empty($employeeData['cpr'])) {
                $employee->setCpr($employeeData['cpr']);
            }

            $employee->save();

            // Create initial contract if provided
            if ($contractData) {
                EmploymentContract::create(array_merge([
                    'employee_id' => $employee->id,
                    'start_date' => $employee->hire_date,
                    'status' => 'active',
                ], $contractData));
            }

            // Create initial holiday balance
            $this->ensureHolidayBalance($employee, (int)date('Y'));

            // Update applicant record
            $applicant->update([
                'stage' => 'hired',
                'hired_employee_id' => $employee->id,
            ]);

            return $employee;
        });
    }

    /**
     * Get list of documents expiring within the next N days.
     */
    public function getExpiringDocuments(?int $days = null): Collection
    {
        $alertDays = $days ?? $this->settingsService->getInteger('hrm', 'document_expiry_alert_days', 30);
        return EmployeeDocument::with('employee')
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>=', now()->toDateString())
            ->where('expiry_date', '<=', now()->addDays($alertDays)->toDateString())
            ->orderBy('expiry_date', 'asc')
            ->get();
    }

    /**
     * Get workforce KPIs and metrics for HR dashboard.
     */
    public function getWorkforceKpis(): array
    {
        $activeEmployees = Employee::with(['activeContract', 'position'])->where('status', 'active')->get();
        $activeCount = $activeEmployees->count();

        $stdWeekly = $this->settingsService->getDecimal('hrm', 'standard_weekly_hours', 48.00);
        if ($stdWeekly <= 0) {
            $stdWeekly = 48.00;
        }

        $totalFte = 0.0;
        $monthlyGrossCost = 0.0;

        foreach ($activeEmployees as $emp) {
            $contract = $emp->activeContract;
            if ($contract) {
                $hours = (float)$contract->weekly_hours;
                $totalFte += round($hours / $stdWeekly, 2);

                if ($contract->contract_type === 'hourly_wage' || $contract->contract_type === 'timeloennet' || $emp->employment_type === 'hourly') {
                    $rate = (float)($contract->hourly_rate_npr ?: 175.00);
                    $monthlyGrossCost += round($hours * 4.3333 * $rate, 2);
                } else {
                    $monthlyGrossCost += (float)($contract->monthly_salary_npr ?: 0.00);
                }
            } else {
                $totalFte += ($emp->employment_type === 'full_time') ? 1.0 : 0.5;
                $monthlyGrossCost += (float)($emp->position?->min_salary_npr ?: 30000.00);
            }
        }

        $pendingLeaves = LeaveRequest::where('status', 'pending')->count();
        $pendingTimesheets = Timesheet::where('status', 'submitted')->count();
        $pendingExpenses = ExpenseClaim::where('status', 'submitted')->count();
        $openJobs = RecruitmentJob::where('status', 'published')->count();
        $totalDepts = Department::where('is_active', true)->count();

        return [
            'active_employees' => $activeCount,
            'total_fte' => round($totalFte, 1),
            'monthly_gross_salary_cost_npr' => round($monthlyGrossCost, 2),
            'monthly_gross_salary_cost_npr' => round($monthlyGrossCost, 2),
            'pending_leaves_count' => $pendingLeaves,
            'pending_timesheets_count' => $pendingTimesheets,
            'pending_expenses_count' => $pendingExpenses,
            'open_jobs_count' => $openJobs,
            'total_departments' => $totalDepts,
        ];
    }

    /**
     * Resolve employee record associated with an authenticated user.
     */
    public function getEmployeeForUser(?User $user): ?Employee
    {
        if (!$user) {
            return null;
        }

        return Employee::where('user_id', $user->id)
            ->orWhere('email', $user->email)
            ->first();
    }

    /**
     * Employee Mobile Clock-In handler with shift & geolocation capture.
     */
    public function clockIn(Employee $employee, array $options = []): Timesheet
    {
        return DB::transaction(function () use ($employee, $options) {
            $today = $options['date'] ?? now('Asia/Kathmandu')->toDateString();
            $nowTime = $options['time'] ?? now('Asia/Kathmandu')->format('H:i:s');

            $timesheet = Timesheet::firstOrNew([
                'employee_id' => $employee->id,
                'date' => $today,
            ]);

            if ($timesheet->clock_in && !$timesheet->clock_out) {
                // Already clocked in
                return $timesheet;
            }

            $timesheet->shift_name = $options['shift_name'] ?? 'Showroom Retail Shift';
            $timesheet->shift_start_time = $options['shift_start_time'] ?? '10:00:00';
            $timesheet->shift_end_time = $options['shift_end_time'] ?? '19:00:00';
            $timesheet->clock_in = $nowTime;
            $timesheet->clock_in_latitude = $options['latitude'] ?? null;
            $timesheet->clock_in_longitude = $options['longitude'] ?? null;
            $timesheet->clock_in_accuracy = $options['accuracy'] ?? null;
            $timesheet->location = $options['location'] ?? ($employee->branch_location ?: 'Laijau Showroom');
            $timesheet->device_info = $options['device_info'] ?? request()->header('User-Agent');
            $timesheet->status = 'submitted';

            $timesheet->recalculateHours();
            $timesheet->save();

            return $timesheet;
        });
    }

    /**
     * Employee Mobile Clock-Out handler with worked hours & overtime calculation.
     */
    public function clockOut(Employee $employee, array $options = []): Timesheet
    {
        return DB::transaction(function () use ($employee, $options) {
            $today = $options['date'] ?? now('Asia/Kathmandu')->toDateString();
            $nowTime = $options['time'] ?? now('Asia/Kathmandu')->format('H:i:s');

            $timesheet = Timesheet::where('employee_id', $employee->id)
                ->where('date', $today)
                ->latest()
                ->first();

            if (!$timesheet) {
                // No punch in found, create with missing punch
                $timesheet = new Timesheet([
                    'employee_id' => $employee->id,
                    'date' => $today,
                    'shift_name' => $options['shift_name'] ?? 'Showroom Retail Shift',
                    'shift_start_time' => $options['shift_start_time'] ?? '10:00:00',
                    'shift_end_time' => $options['shift_end_time'] ?? '19:00:00',
                    'clock_in' => $options['clock_in'] ?? ($options['shift_start_time'] ?? '10:00:00'), // Default assumption
                    'is_missing_punch' => true,
                    'notes' => 'Clock-in was missed; recorded upon punch-out.',
                    'status' => 'submitted',
                ]);
            }

            $timesheet->clock_out = $nowTime;
            $timesheet->clock_out_latitude = $options['latitude'] ?? null;
            $timesheet->clock_out_longitude = $options['longitude'] ?? null;
            $timesheet->clock_out_accuracy = $options['accuracy'] ?? null;
            $timesheet->break_minutes = $options['break_minutes'] ?? 60; // 1 hour standard break

            $timesheet->recalculateHours();
            $timesheet->save();

            return $timesheet;
        });
    }

    /**
     * Get active clock-in status for an employee on a specific date.
     */
    public function getEmployeeTodayStatus(Employee $employee, ?string $date = null): array
    {
        $targetDate = $date ?? now()->toDateString();
        $timesheet = Timesheet::where('employee_id', $employee->id)
            ->where('date', $targetDate)
            ->latest()
            ->first();

        $isClockedIn = false;
        $isClockedOut = false;
        $clockInFormatted = null;
        $clockOutFormatted = null;
        $workingSeconds = 0;

        if ($timesheet) {
            if ($timesheet->clock_in) {
                $isClockedIn = true;
                $clockInFormatted = Carbon::parse($timesheet->clock_in)->format('h:i A');

                if ($timesheet->clock_out) {
                    $isClockedOut = true;
                    $clockOutFormatted = Carbon::parse($timesheet->clock_out)->format('h:i A');
                    $workingSeconds = max(0, strtotime($timesheet->clock_out) - strtotime($timesheet->clock_in) - (($timesheet->break_minutes ?? 0) * 60));
                } else {
                    $workingSeconds = max(0, now()->timestamp - strtotime($targetDate . ' ' . $timesheet->clock_in));
                }
            }
        }

        $hours = floor($workingSeconds / 3600);
        $minutes = floor(($workingSeconds % 3600) / 60);

        return [
            'date' => $targetDate,
            'is_clocked_in' => $isClockedIn,
            'is_clocked_out' => $isClockedOut,
            'clock_in_time' => $clockInFormatted,
            'clock_out_time' => $clockOutFormatted,
            'working_hours' => $hours,
            'working_minutes' => $minutes,
            'working_time_formatted' => sprintf('%02dh %02dm', $hours, $minutes),
            'shift_name' => $timesheet?->shift_name ?? 'Showroom Retail Shift',
            'shift_start' => '08:00 AM',
            'shift_end' => '07:00 PM',
            'shift_timing' => '8:00 AM – 7:00 PM',
            'is_late' => (bool)($timesheet?->is_late),
            'late_minutes' => (int)($timesheet?->late_minutes ?? 0),
            'overtime_hours' => (float)($timesheet?->overtime_hours ?? 0.0),
            'regular_hours' => (float)($timesheet?->regular_hours ?? 0.0),
            'timesheet' => $timesheet,
        ];
    }

    /**
     * Alias for getEmployeeTodayStatus.
     */
    public function getTodayStatus(Employee $employee, ?string $date = null): array
    {
        return $this->getEmployeeTodayStatus($employee, $date);
    }
}
