<?php

declare(strict_types=1);

namespace App\Policies\Accounting;

use App\Policies\BasePolicy;

class AccountingInvoicePolicy extends BasePolicy
{
    protected string $modelName = 'AccountingInvoice';

    protected array $allowedRoles = [
        'Accountant',
    ];
}
