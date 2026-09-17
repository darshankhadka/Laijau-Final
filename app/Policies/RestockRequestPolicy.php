<?php

declare(strict_types=1);

namespace App\Policies;

class RestockRequestPolicy extends BasePolicy
{
    protected string $modelName = 'RestockRequest';

    protected array $allowedRoles = [
        'Store Manager',
        'Support Agent',
        'Warehouse Manager',
    ];
}
