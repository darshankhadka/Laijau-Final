<?php

declare(strict_types=1);

namespace App\Policies\Accounting;

use App\Models\Accounting\TdsRecord;
use App\Models\User;
use App\Policies\BasePolicy;

class TdsRecordPolicy extends BasePolicy
{
    protected string $modelName = 'TdsRecord';

    protected array $allowedRoles = [
        'Accountant',
    ];
}
