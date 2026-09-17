<?php

declare(strict_types=1);

namespace App\Policies;

class ShippingMethodPolicy extends BasePolicy
{
    protected string $modelName = 'ShippingMethod';

    protected array $allowedRoles = [
        'Store Manager',
    ];
}
