<?php

declare(strict_types=1);

namespace App\Policies;

class ContactMessagePolicy extends BasePolicy
{
    protected string $modelName = 'ContactMessage';

    protected array $allowedRoles = [
        'Store Manager',
        'Support Agent',
        'Workspace Admin',
        'admin',
    ];
}
