<?php

declare(strict_types=1);

namespace App\Policies\Hrm;

use App\Policies\BasePolicy;

class EmployeePolicy extends BasePolicy
{
    protected string $modelName = 'Employee';

    protected array $allowedRoles = [
        'HR / Payroll Manager',
        'HR Manager',
    ];
}
