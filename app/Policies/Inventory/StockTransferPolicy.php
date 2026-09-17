<?php

declare(strict_types=1);

namespace App\Policies\Inventory;

use App\Policies\BasePolicy;

class StockTransferPolicy extends BasePolicy
{
    protected string $modelName = 'StockTransfer';

    protected array $allowedRoles = [
        'Warehouse Manager',
    ];
}
