<?php

declare(strict_types=1);

namespace App\Policies;

class CouponPolicy extends BasePolicy
{
    protected string $modelName = 'Coupon';

    protected array $allowedRoles = [
        'Store Manager',
    ];
}
