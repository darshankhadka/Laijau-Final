<?php

declare(strict_types=1);

namespace App\Policies\Inventory;

use App\Policies\BasePolicy;

class StockAdjustmentPolicy extends BasePolicy
{
    protected string $modelName = 'StockAdjustment';

    protected array $allowedRoles = [
        'Warehouse Manager',
    ];
}
