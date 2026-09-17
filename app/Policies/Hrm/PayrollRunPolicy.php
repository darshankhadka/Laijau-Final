<?php

declare(strict_types=1);

namespace App\Policies\Hrm;

use App\Models\Hrm\PayrollRun;
use App\Models\User;
use App\Policies\BasePolicy;

class PayrollRunPolicy extends BasePolicy
{
    protected string $modelName = 'PayrollRun';

    protected array $allowedRoles = [
        'HR / Payroll Manager',
        'HR Manager',
    ];

    /**
     * Determine whether the user can calculate payroll.
     */
    public function calculate(User $user, ?PayrollRun $run = null): bool
    {
        return $this->authorize($user, 'Calculate', $run);
    }

    /**
     * Determine whether the user can approve/commit payroll.
     */
    public function approve(User $user, ?PayrollRun $run = null): bool
    {
        return $this->authorize($user, 'Approve', $run);
    }
}
