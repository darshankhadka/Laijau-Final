<?php

declare(strict_types=1);

namespace App\Policies\Accounting;

use App\Models\Accounting\BikriKhataEntry;
use App\Models\User;
use App\Policies\BasePolicy;

class BikriKhataEntryPolicy extends BasePolicy
{
    protected string $modelName = 'BikriKhataEntry';

    protected array $allowedRoles = [
        'Accountant',
    ];

    public function delete(User $user, $model = null): bool
    {
        // Statutory Nepal VAT Act 2052: Sales book records cannot be deleted. Adjustments must use Credit Notes.
        return false;
    }
}
