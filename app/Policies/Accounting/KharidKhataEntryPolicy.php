<?php

declare(strict_types=1);

namespace App\Policies\Accounting;

use App\Models\Accounting\KharidKhataEntry;
use App\Models\User;
use App\Policies\BasePolicy;

class KharidKhataEntryPolicy extends BasePolicy
{
    protected string $modelName = 'KharidKhataEntry';

    protected array $allowedRoles = [
        'Accountant',
    ];

    public function delete(User $user, $model = null): bool
    {
        // Statutory Nepal VAT Act 2052: Purchase book records cannot be deleted. Adjustments must use Debit Notes.
        return false;
    }
}
