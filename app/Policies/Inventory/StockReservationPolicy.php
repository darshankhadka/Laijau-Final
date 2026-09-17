<?php

declare(strict_types=1);

namespace App\Policies\Inventory;

use App\Policies\BasePolicy;

class StockReservationPolicy extends BasePolicy
{
    protected string $modelName = 'StockReservation';

    protected array $allowedRoles = [
        'Warehouse Manager',
        'Store Manager',
    ];
}
