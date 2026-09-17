<?php

declare(strict_types=1);

namespace App\Policies;

class CrmLeadPolicy extends BasePolicy
{
    protected string $modelName = 'CrmLead';

    protected array $allowedRoles = [
        'Store Manager',
        'Support Agent',
        'Workspace Admin',
        'admin',
    ];
}
