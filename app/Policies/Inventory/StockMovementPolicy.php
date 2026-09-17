<?php

declare(strict_types=1);

namespace App\Policies\Inventory;

use App\Policies\BasePolicy;

class StockMovementPolicy extends BasePolicy
{
    protected string $modelName = 'StockMovement';

    protected array $allowedRoles = [
        'Warehouse Manager',
        'Store Manager',
    ];
}
