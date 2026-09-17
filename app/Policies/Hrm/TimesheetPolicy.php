<?php

declare(strict_types=1);

namespace App\Policies\Hrm;

use App\Policies\BasePolicy;

class TimesheetPolicy extends BasePolicy
{
    protected string $modelName = 'Timesheet';

    protected array $allowedRoles = [
        'HR / Payroll Manager',
        'HR Manager',
    ];
}
