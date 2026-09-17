<?php

declare(strict_types=1);

namespace App\Policies\Inventory;

use App\Policies\BasePolicy;

class StockLevelPolicy extends BasePolicy
{
    protected string $modelName = 'StockLevel';

    protected array $allowedRoles = [
        'Warehouse Manager',
        'Store Manager',
        'Cashier',
    ];
}
