<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Database\Eloquent\Model;

abstract class BasePolicy
{
    use HandlesAuthorization;

    /**
     * The model class name or permission subject identifier (e.g., 'Order', 'JournalEntry').
     */
    protected string $modelName;

    /**
     * Optional allowed roles that have general access to this domain.
     *
     * @var array<string>
     */
    protected array $allowedRoles = [];

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->authorize($user, 'ViewAny');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, ?Model $model = null): bool
    {
        return $this->authorize($user, 'View', $model);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->authorize($user, 'Create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, ?Model $model = null): bool
    {
        return $this->authorize($user, 'Update', $model);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, ?Model $model = null): bool
    {
        return $this->authorize($user, 'Delete', $model);
    }

    /**
     * Determine whether the user can bulk delete models.
     */
    public function deleteAny(User $user): bool
    {
        return $this->authorize($user, 'DeleteAny');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, ?Model $model = null): bool
    {
        return $this->authorize($user, 'Restore', $model);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, ?Model $model = null): bool
    {
        return $this->authorize($user, 'ForceDelete', $model);
    }

    /**
     * Determine whether the user can replicate the model.
     */
    public function replicate(User $user, ?Model $model = null): bool
    {
        return $this->authorize($user, 'Replicate', $model);
    }

    /**
     * Determine whether the user can reorder models.
     */
    public function reorder(User $user): bool
    {
        return $this->authorize($user, 'Reorder');
    }

    /**
     * Core permission verification logic.
     */
    protected function authorize(User $user, string $action, ?Model $model = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $permission = "{$action}:{$this->modelName}";

        // Check if user has explicit permission (via Spatie Permission / Gate)
        try {
            if ($user->hasPermissionTo($permission, 'admin') || $user->hasPermissionTo($permission, 'web')) {
                return true;
            }
        } catch (\Throwable $e) {
            // Permission does not exist in DB yet; proceed to role fallback
        }

        // Viewer role: Read-only access to view and viewAny
        if ($user->isViewer() && in_array($action, ['ViewAny', 'View'], true)) {
            return true;
        }

        // Delete actions strictly require explicit delete permission or super admin
        if (in_array($action, ['Delete', 'DeleteAny', 'ForceDelete'], true)) {
            return false;
        }

        // Check role fallback for configured domain roles (view, create, update only)
        if (!empty($this->allowedRoles)) {
            foreach ($this->allowedRoles as $role) {
                if ($user->hasRole($role, 'admin') || $user->hasRole($role, 'web') || $user->role === strtolower(str_replace(' ', '_', $role))) {
                    // Allowed domain role has access
                    return true;
                }
            }
        }

        return false;
    }
}
