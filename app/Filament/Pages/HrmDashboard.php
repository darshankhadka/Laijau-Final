<?php

namespace App\Filament\Pages;

use App\Models\Hrm\Department;
use App\Models\Hrm\Employee;
use App\Models\Hrm\ExpenseClaim;
use App\Models\Hrm\LeaveRequest;
use App\Models\Hrm\PayrollRun;
use App\Models\Hrm\RecruitmentJob;
use App\Models\Hrm\Timesheet;
use App\Services\Hrm\HrmService;
use Filament\Pages\Page;

class HrmDashboard extends Page
{
    protected string $view = 'filament.pages.hrm-dashboard';

    protected static bool $shouldRegisterNavigation = false;
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-group';
    protected static string | \UnitEnum | null $navigationGroup = 'HRM';
    protected static ?string $navigationLabel = 'HR Dashboard';
    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        $user = auth('admin')->user() ?? auth('web')->user();
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
            return true;
        }

        return $user->hasRole('HR / Payroll Manager', 'admin')
            || $user->hasRole('HR / Payroll Manager', 'web')
            || $user->hasRole('HR Manager', 'admin')
            || $user->hasRole('HR Manager', 'web')
            || $user->hasRole('Workspace Admin', 'admin')
            || $user->hasRole('Workspace Admin', 'web')
            || $user->can('page_HrmDashboard')
            || $user->role === 'hr_manager';
    }

    public function getViewData(): array
    {
        $hrmService = app(HrmService::class);
        $kpis = $hrmService->getWorkforceKpis();

        $departments = Department::withCount(['employees' => function ($q) {
            $q->where('status', 'active');
        }])->get();

        $latestPayroll = PayrollRun::with('items')->latest()->first();

        $pendingLeaves = LeaveRequest::with('employee')
            ->where('status', 'pending')
            ->latest()
            ->take(5)
            ->get();

        $pendingExpenses = ExpenseClaim::with('employee')
            ->where('status', 'submitted')
            ->latest()
            ->take(5)
            ->get();

        $openJobs = RecruitmentJob::withCount('applicants')
            ->where('status', 'published')
            ->get();

        return [
            'kpis' => $kpis,
            'departments' => $departments,
            'latestPayroll' => $latestPayroll,
            'pendingLeaves' => $pendingLeaves,
            'pendingExpenses' => $pendingExpenses,
            'openJobs' => $openJobs,
        ];
    }
}
