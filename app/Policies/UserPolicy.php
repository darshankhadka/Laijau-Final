<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends BasePolicy
{
    protected string $modelName = 'User';

    protected array $allowedRoles = [
        'Workspace Admin',
    ];

    /**
     * Determine whether the user can update the given user account.
     * Hardened against privilege escalation and self-promotion IDOR.
     */
    public function update(User $user, ?Model $model = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        /** @var User|null $target */
        $target = $model;

        // Non-super-admins cannot update Super Admin or Admin accounts
        if ($target && ($target->isSuperAdmin() || $target->role === 'admin' || $target->hasRole('Super Admin', 'admin'))) {
            return false;
        }

        // Non-super-admins cannot edit customer accounts if they are not Workspace Admin or Store Manager
        if ($user->isWorkspaceAdmin() || $user->hasRole('Store Manager', 'web') || $user->hasRole('Store Manager', 'admin')) {
            // Can update regular customers or non-elevated staff
            return $target ? $target->role === 'customer' : true;
        }

        return $this->authorize($user, 'Update', $target);
    }

    /**
     * Determine whether the user can delete the given user account.
     */
    public function delete(User $user, ?Model $model = null): bool
    {
        if ($user->isSuperAdmin()) {
            // Cannot delete own active super admin account to prevent lockout
            return $model ? (int)$user->id !== (int)$model->id : true;
        }

        /** @var User|null $target */
        $target = $model;

        // Cannot delete self
        if ($target && (int)$user->id === (int)$target->id) {
            return false;
        }

        // Non-super-admins cannot delete Super Admin or Admin accounts
        if ($target && ($target->isSuperAdmin() || $target->role === 'admin' || $target->hasRole('Super Admin', 'admin'))) {
            return false;
        }

        return $this->authorize($user, 'Delete', $target);
    }
}
