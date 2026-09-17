<?php

declare(strict_types=1);

namespace App\Policies\Hrm;

use App\Models\Hrm\ExpenseClaim;
use App\Models\User;
use App\Policies\BasePolicy;

class ExpenseClaimPolicy extends BasePolicy
{
    protected string $modelName = 'ExpenseClaim';

    protected array $allowedRoles = [
        'HR / Payroll Manager',
        'HR Manager',
        'Accountant',
    ];

    /**
     * Determine whether the user can approve the expense claim.
     * Enforces separation of duties: non-super-admin cannot approve their own claim.
     */
    public function approve(User $user, ?ExpenseClaim $claim = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Prevent self-approval IDOR: claimant cannot approve their own expense claim
        if ($claim && $claim->employee) {
            $claimantUserId = $claim->employee->user_id;
            if ($claimantUserId && (int)$claimantUserId === (int)$user->id) {
                return false;
            }
        }

        return $this->authorize($user, 'Approve', $claim);
    }
}
