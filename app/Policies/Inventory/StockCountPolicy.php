<?php

declare(strict_types=1);

namespace App\Policies\Inventory;

use App\Policies\BasePolicy;

class StockCountPolicy extends BasePolicy
{
    protected string $modelName = 'StockCount';

    protected array $allowedRoles = [
        'Warehouse Manager',
    ];
}
