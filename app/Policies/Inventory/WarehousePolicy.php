<?php

declare(strict_types=1);

namespace App\Policies\Inventory;

use App\Policies\BasePolicy;

class WarehousePolicy extends BasePolicy
{
    protected string $modelName = 'Warehouse';

    protected array $allowedRoles = [
        'Warehouse Manager',
    ];
}
