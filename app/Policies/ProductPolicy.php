<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ProductPolicy extends BasePolicy
{
    protected string $modelName = 'Product';

    protected array $allowedRoles = [
        'Store Manager',
    ];

    /**
     * Determine whether the user can view products.
     * Cashiers and Warehouse Managers can view product catalog details.
     */
    public function view(User $user, ?Model $model = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        if (
            $user->hasRole('Cashier', 'admin') ||
            $user->hasRole('Cashier', 'web') ||
            $user->hasRole('Warehouse Manager', 'admin') ||
            $user->hasRole('Warehouse Manager', 'web')
        ) {
            return true;
        }

        return parent::view($user, $model);
    }
}
