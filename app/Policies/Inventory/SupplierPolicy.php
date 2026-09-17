<?php

declare(strict_types=1);

namespace App\Policies\Inventory;

use App\Policies\BasePolicy;

class SupplierPolicy extends BasePolicy
{
    protected string $modelName = 'Supplier';

    protected array $allowedRoles = [
        'Warehouse Manager',
        'Store Manager',
    ];
}
