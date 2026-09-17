<?php

declare(strict_types=1);

namespace App\Policies\Inventory;

use App\Models\Inventory\PurchaseOrder;
use App\Models\User;
use App\Policies\BasePolicy;

class PurchaseOrderPolicy extends BasePolicy
{
    protected string $modelName = 'PurchaseOrder';

    protected array $allowedRoles = [
        'Warehouse Manager',
        'Store Manager',
    ];

    /**
     * Determine whether the user can approve the purchase order.
     */
    public function approve(User $user, ?PurchaseOrder $po = null): bool
    {
        return $this->authorize($user, 'Approve', $po);
    }
}
