<?php

declare(strict_types=1);

namespace App\Policies;

class ProductAttributePolicy extends BasePolicy
{
    protected string $modelName = 'ProductAttribute';

    protected array $allowedRoles = [
        'Store Manager',
    ];
}
