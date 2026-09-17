<?php

namespace App\Http\Controllers\Hrm;

use App\Http\Controllers\Controller;
use App\Models\Hrm\Employee;
use App\Models\Hrm\PayrollRunItem;
use App\Models\Hrm\Timesheet;
use App\Services\Hrm\HrmService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EmployeePortalController extends Controller
{
    public function __construct(
        protected HrmService $hrmService
    ) {}

    /**
     * Resolve the employee for the current user session or query.
     */
    protected function resolveEmployee(Request $request): ?Employee
    {
        $user = Auth::guard('admin')->user() ?? Auth::guard('web')->user();

        if ($request->has('employee_id') && ($user?->isSuperAdmin() || $user?->hasRole('admin', 'web'))) {
            return Employee::find($request->input('employee_id'));
        }

        $employee = $this->hrmService->getEmployeeForUser($user);

        if (!$employee && ($user?->isSuperAdmin() || app()->environment('local', 'testing'))) {
            $employee = Employee::where('status', 'active')->first();
        }

        return $employee;
    }

    /**
     * Display the mobile-first employee portal.
     */
    public function index(Request $request)
    {
        $employee = $this->resolveEmployee($request);

        if (!$employee) {
            // If still no employee found, create a sample employee so portal is immediately interactive
            $employee = Employee::firstOrCreate(
                ['email' => 'ram.shrestha@laijau.com'],
                [
                    'employee_number' => Employee::generateNextEmployeeNumber(),
                    'first_name' => 'Ram',
                    'last_name' => 'Shrestha',
                    'phone' => '+977 9851012345',
                    'pan_number' => '102938475',
                    'marital_status' => 'married',
                    'branch_location' => 'Laijau Showroom',
                    'basic_salary' => 35000.00,
                    'allowance_amount' => 15000.00,
                    'gross_salary' => 50000.00,
                    'ssf_enrolled' => true,
                    'status' => 'active',
                    'hire_date' => now()->subMonths(6)->toDateString(),
                ]
            );
        }

        $status = $this->hrmService->getEmployeeTodayStatus($employee);

        // Recent 5 attendance punches
        $recentTimesheets = Timesheet::where('employee_id', $employee->id)
            ->orderBy('date', 'desc')
            ->limit(5)
            ->get();

        // Recent payslips
        $recentPayslips = PayrollRunItem::with('payrollRun')
            ->where('employee_id', $employee->id)
            ->orderBy('id', 'desc')
            ->limit(3)
            ->get();

        $activeTab = $request->query('tab', 'home');

        return view('hrm.mobile-portal', compact('employee', 'status', 'recentTimesheets', 'recentPayslips', 'activeTab'));
    }

    /**
     * Handle mobile punch in.
     */
    public function clockIn(Request $request)
    {
        $employee = $this->resolveEmployee($request);

        if (!$employee) {
            return $request->wantsJson()
                ? response()->json(['error' => 'Employee profile not found.'], 404)
                : back()->with('error', 'Employee profile not found.');
        }

        $timesheet = $this->hrmService->clockIn($employee, [
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'accuracy' => $request->input('accuracy'),
            'shift_name' => 'Showroom Retail Shift',
            'shift_start_time' => '10:00:00',
            'shift_end_time' => '19:00:00',
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Clocked in successfully!',
                'timesheet' => $timesheet,
                'status' => $this->hrmService->getEmployeeTodayStatus($employee),
            ]);
        }

        return redirect()->route('hrm.portal')->with('success', 'Clocked in at ' . Carbon::parse($timesheet->clock_in)->format('h:i A'));
    }

    /**
     * Handle mobile punch out.
     */
    public function clockOut(Request $request)
    {
        $employee = $this->resolveEmployee($request);

        if (!$employee) {
            return $request->wantsJson()
                ? response()->json(['error' => 'Employee profile not found.'], 404)
                : back()->with('error', 'Employee profile not found.');
        }

        $timesheet = $this->hrmService->clockOut($employee, [
            'latitude' => $request->input('latitude'),
            'longitude' => $request->input('longitude'),
            'accuracy' => $request->input('accuracy'),
            'break_minutes' => $request->input('break_minutes', 60),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Clocked out successfully!',
                'timesheet' => $timesheet,
                'status' => $this->hrmService->getEmployeeTodayStatus($employee),
            ]);
        }

        return redirect()->route('hrm.portal')->with('success', 'Clocked out at ' . Carbon::parse($timesheet->clock_out)->format('h:i A') . " ({$timesheet->regular_hours} hrs worked)");
    }

    /**
     * Render statutory printable payslip.
     */
    public function payslip(Request $request, PayrollRunItem $item)
    {
        $item->load(['employee', 'payrollRun']);
        return view('print.payslip', compact('item'));
    }
}
