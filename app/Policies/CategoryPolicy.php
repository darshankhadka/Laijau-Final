<?php

declare(strict_types=1);

namespace App\Policies;

class CategoryPolicy extends BasePolicy
{
    protected string $modelName = 'Category';

    protected array $allowedRoles = [
        'Store Manager',
        'Workspace Admin',
        'admin',
    ];
}
