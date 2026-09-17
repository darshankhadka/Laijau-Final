<?php

declare(strict_types=1);

namespace App\Policies\Accounting;

use App\Models\Accounting\AccountingFiscalYear;
use App\Models\User;
use App\Policies\BasePolicy;

class AccountingFiscalYearPolicy extends BasePolicy
{
    protected string $modelName = 'AccountingFiscalYear';

    protected array $allowedRoles = [
        'Accountant',
    ];
}
