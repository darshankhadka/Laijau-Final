<?php

declare(strict_types=1);

namespace App\Policies\Hrm;

use App\Policies\BasePolicy;

class RecruitmentJobPolicy extends BasePolicy
{
    protected string $modelName = 'RecruitmentJob';

    protected array $allowedRoles = [
        'HR / Payroll Manager',
        'HR Manager',
    ];
}
