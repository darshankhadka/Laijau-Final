<?php

declare(strict_types=1);

namespace App\Policies;

class OfflineSalePolicy extends BasePolicy
{
    protected string $modelName = 'OfflineSale';

    protected array $allowedRoles = [
        'Cashier',
        'Store Manager',
        'Accountant',
    ];
}
