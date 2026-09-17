<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class OrderPolicy extends BasePolicy
{
    protected string $modelName = 'Order';

    protected array $allowedRoles = [
        'Store Manager',
    ];

    /**
     * Determine whether the user can view the order.
     */
    public function view(User $user, ?Model $model = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Accountant can view orders for auditing & tax reconciliation
        if ($user->hasRole('Accountant', 'admin') || $user->hasRole('Accountant', 'web')) {
            return true;
        }

        return parent::view($user, $model);
    }

    /**
     * Determine whether the user can update the order.
     */
    public function update(User $user, ?Model $model = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return parent::update($user, $model);
    }

    /**
     * Determine whether the user can delete an order.
     */
    public function delete(User $user, ?Model $model = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        // Viewers cannot delete orders under any circumstances
        if ($user->isViewer()) {
            return false;
        }

        return parent::delete($user, $model);
    }
}
