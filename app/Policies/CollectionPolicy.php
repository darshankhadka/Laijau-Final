<?php

declare(strict_types=1);

namespace App\Policies;

class CollectionPolicy extends BasePolicy
{
    protected string $modelName = 'Collection';

    protected array $allowedRoles = [
        'Store Manager',
    ];
}
