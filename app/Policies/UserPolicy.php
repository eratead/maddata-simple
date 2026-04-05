<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('is_admin');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $user->hasPermission('is_admin')
            || $user->hasPermission('can_manage_users')
            || $user->id === $model->id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('is_admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return $user->hasPermission('is_admin');
    }

    /**
     * Determine whether the acting user may change the target user's role.
     * A user may never change their own role or admin flag.
     * This gate is purely a self-targeting guard; broader role-assignment
     * permissions are enforced by each controller's escalation logic.
     */
    public function changeRole(User $user, User $target): bool
    {
        // Self-role-change is never permitted
        if ($user->id === $target->id) {
            return false;
        }

        // Must have admin OR can_manage_users to change someone else's role
        return $user->hasPermission('is_admin') || $user->hasPermission('can_manage_users');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, User $model): bool
    {
        if (! $user->hasPermission('is_admin')) {
            return false;
        }

        // Cannot self-delete
        if ($user->id === $model->id) {
            return false;
        }

        // Cannot delete the last active admin
        $activeAdminCount = User::where('is_active', true)
            ->where(fn ($q) => $q
                ->where('is_admin', true)
                ->orWhereHas('userRole', fn ($r) => $r->whereJsonContains('permissions->is_admin', true))
            )
            ->count();

        $targetIsAdmin = $model->is_admin
            || ($model->userRole && $model->userRole->hasPermission('is_admin'));

        if ($activeAdminCount <= 1 && $targetIsAdmin) {
            return false;
        }

        return true;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, User $model): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}
