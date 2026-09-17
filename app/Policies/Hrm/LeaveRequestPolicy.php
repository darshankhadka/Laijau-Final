<?php

declare(strict_types=1);

namespace App\Policies\Hrm;

use App\Policies\BasePolicy;

class LeaveRequestPolicy extends BasePolicy
{
    protected string $modelName = 'LeaveRequest';

    protected array $allowedRoles = [
        'HR / Payroll Manager',
        'HR Manager',
    ];
}
