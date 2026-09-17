<?php

declare(strict_types=1);

namespace App\Policies\Accounting;

use App\Policies\BasePolicy;

class AccountPolicy extends BasePolicy
{
    protected string $modelName = 'Account';

    protected array $allowedRoles = [
        'Accountant',
    ];
}
