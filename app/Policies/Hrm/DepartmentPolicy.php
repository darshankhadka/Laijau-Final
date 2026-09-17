<?php

declare(strict_types=1);

namespace App\Policies\Hrm;

use App\Policies\BasePolicy;

class DepartmentPolicy extends BasePolicy
{
    protected string $modelName = 'Department';

    protected array $allowedRoles = [
        'HR / Payroll Manager',
        'HR Manager',
    ];
}
