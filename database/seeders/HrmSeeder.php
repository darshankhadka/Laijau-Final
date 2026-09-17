<?php

namespace Database\Seeders;

use App\Models\Hrm\Department;
use App\Models\Hrm\Employee;
use App\Models\Hrm\ExpenseClaim;
use App\Models\Hrm\LeaveRequest;
use App\Models\Hrm\PayrollRun;
use App\Models\Hrm\Position;
use App\Models\Hrm\RecruitmentApplicant;
use App\Models\Hrm\RecruitmentJob;
use App\Models\Hrm\Timesheet;
use App\Services\Hrm\ExpenseService;
use App\Services\Hrm\HrmService;
use App\Services\Hrm\PayrollPreparationService;
use Illuminate\Database\Seeder;

class HrmSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Seed base organization structure & staff
        app(HrmService::class)->seedDefaultOrganization();

        // 2. Fetch seeded employees
        $aarav = Employee::where('employee_number', 'MED-0001')->first();
        $sunita = Employee::where('employee_number', 'MED-0002')->first();
        $bipin = Employee::where('employee_number', 'MED-0003')->first();

        // 3. Seed Sample Timesheets for Hourly & Overtime Tracking
        if ($bipin) {
            Timesheet::firstOrCreate([
                'employee_id' => $bipin->id,
                'date' => now()->subDays(3)->toDateString(),
            ], [
                'clock_in' => '09:00:00',
                'clock_out' => '17:30:00',
                'break_minutes' => 30,
                'regular_hours' => 7.5,
                'overtime_hours' => 0.5,
                'location' => 'Laijau Showroom, Kathmandu',
                'notes' => 'Showroom VIP client styling & Pashmina presentations',
                'status' => 'approved',
            ]);

            Timesheet::firstOrCreate([
                'employee_id' => $bipin->id,
                'date' => now()->subDays(2)->toDateString(),
            ], [
                'clock_in' => '09:00:00',
                'clock_out' => '17:00:00',
                'break_minutes' => 30,
                'regular_hours' => 7.5,
                'overtime_hours' => 0.0,
                'location' => 'Laijau Showroom, Kathmandu',
                'notes' => 'Inventory receipt from Patan artisan cooperative',
                'status' => 'submitted',
            ]);
        }

        // 4. Seed Leave Requests
        if ($bipin) {
            LeaveRequest::firstOrCreate([
                'employee_id' => $bipin->id,
                'start_date' => now()->addDays(14)->toDateString(),
            ], [
                'end_date' => now()->addDays(18)->toDateString(),
                'leave_type' => 'annual',
                'days_count' => 5.0,
                'is_paid' => true,
                'status' => 'pending',
                'reason' => 'Annual Dashain holiday under Nepal Labour Act',
            ]);
        }

        if ($sunita) {
            LeaveRequest::firstOrCreate([
                'employee_id' => $sunita->id,
                'start_date' => now()->subDays(10)->toDateString(),
            ], [
                'end_date' => now()->subDays(10)->toDateString(),
                'leave_type' => 'sick',
                'days_count' => 1.0,
                'is_paid' => true,
                'status' => 'approved',
                'approved_at' => now()->subDays(11),
                'reason' => 'Medical sick leave',
            ]);
        }

        // 5. Seed Expense Claims
        if ($aarav) {
            ExpenseClaim::firstOrCreate([
                'claim_number' => 'EXP-2026-001',
            ], [
                'employee_id' => $aarav->id,
                'title' => 'Showroom display packaging & bespoke ribbon supplies',
                'category' => 'packaging_supplies',
                'expense_date' => now()->subDays(5)->toDateString(),
                'gross_amount_npr' => 12500.00,
                'vat_rate' => 13.0,
                'vat_amount_npr' => 1438.05,
                'net_amount_npr' => 11061.95,
                'mileage_km' => null,
                'notes' => 'Purchased at New Road Kathmandu for flagship showroom presentation',
                'status' => 'submitted',
            ]);
        }

        if ($sunita) {
            ExpenseClaim::firstOrCreate([
                'claim_number' => 'EXP-2026-002',
            ], [
                'employee_id' => $sunita->id,
                'title' => 'Client advisory travel to Pokhara retail exhibition',
                'category' => 'transport_mileage',
                'expense_date' => now()->subDays(8)->toDateString(),
                'gross_amount_npr' => 4500.00,
                'vat_rate' => 0.0,
                'vat_amount_npr' => 0.00,
                'net_amount_npr' => 4500.00,
                'mileage_km' => 200,
                'notes' => 'Travel & transport reimbursement for client exhibition',
                'status' => 'submitted',
            ]);
        }

        // 6. Seed Job Openings & Candidates
        $showroomDept = Department::where('code', 'SHOWROOM')->first();
        if ($showroomDept) {
            $job1 = RecruitmentJob::firstOrCreate([
                'title' => 'Senior Showroom Stylist & Client Advisor',
            ], [
                'department_id' => $showroomDept->id,
                'employment_type' => 'full_time',
                'location' => 'Laijau Showroom, Kathmandu',
                'deadline' => now()->addDays(20)->toDateString(),
                'description' => 'We are seeking an experienced luxury fashion stylist with deep appreciation for authentic Nepalese textiles and contemporary styling.',
                'requirements' => "- 2+ years luxury retail experience\n- Fluent in Nepali and English\n- Passion for South Asian heritage craft and contemporary styling",
                'status' => 'published',
            ]);

            RecruitmentApplicant::firstOrCreate([
                'job_id' => $job1->id,
                'email' => 'candidate.ramesh@example.com',
            ], [
                'first_name' => 'Ramesh',
                'last_name' => 'Thapa',
                'phone' => '+977 9841234567',
                'stage' => 'interview_1',
                'rating' => 5,
                'notes' => '4 years of client styling experience at luxury fashion stores in Kathmandu.',
            ]);
        }

        // 7. Seed Initial Completed Payroll Run for Previous Month
        $payrollService = app(PayrollPreparationService::class);
        $prevMonth = now()->subMonth();
        $run = PayrollRun::firstOrCreate([
            'run_number' => 'PAY-' . $prevMonth->format('Y-m'),
        ], [
            'name' => 'Payroll ' . $prevMonth->translatedFormat('F Y'),
            'period_start' => $prevMonth->copy()->startOfMonth()->toDateString(),
            'period_end' => $prevMonth->copy()->endOfMonth()->toDateString(),
            'pay_date' => $prevMonth->copy()->endOfMonth()->toDateString(),
            'status' => 'draft',
        ]);

        $payrollService->calculateRunItems($run);
    }
}
