<?php

namespace App\Policies;

use App\Enums\DenebPermission;
use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(DenebPermission::USERS_READ);
    }

    public function view(User $user, User $model): bool
    {
        if ($user->id === $model->id) {
            return true;
        }

        return $user->hasPermissionTo(DenebPermission::USERS_READ);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(DenebPermission::USERS_CREATE);
    }

    /**
     * Determine whether the user can update the model.
     *
     * The logic is as follows:
     * 1. A user can always update their own profile.
     * 2. To update others, a user must have the 'users-update' permission.
     * 3. A Superadmin (hierarchy 0) can update any user.
     * 4. A non-Superadmin can only update users with a role of a strictly
     *    lower hierarchy (higher hierarchy number).
     */
    public function update(User $user, User $model): bool
    {
        // Case 1: User is updating their own profile.
        if ($user->id === $model->id) {
            return true;
        }

        // Case 2: To update others, first check for the required permission.
        if (! $user->hasPermissionTo(DenebPermission::USERS_UPDATE)) {
            return false;
        }

        $currentUserRole = $user->role();
        $modelUserRole = $model->role();

        // If either user lacks a role, we cannot safely compare hierarchies. Deny access.
        if (! $currentUserRole || ! $modelUserRole) {
            return false;
        }

        // Case 3: Superadmin can update anyone.
        if ($currentUserRole->isSuperAdmin()) {
            return true;
        }

        // Case 4: Non-superadmins can only update users with a lower-precedence role.
        return $currentUserRole->hierarchy < $modelUserRole->hierarchy;
    }

    public function delete(User $user, User $model): bool
    {
        // Users can self-delete. Change this if needed.
        if ($user->id === $model->id) {
            return true;
        }

        $currentUserRole = $user->role();
        $modelUserRole = $model->role();

        if (! $currentUserRole || ! $modelUserRole) {
            return false; // Cannot determine hierarchy if roles are missing.
        }

        if (! $user->hasPermissionTo(DenebPermission::USERS_DELETE)) {
            return false;
        }

        // Superadmin can delete anyone (last superadmin check is in service).
        if ($currentUserRole->isSuperAdmin()) {
            return true;
        }

        // Non-superadmins can delete users with a strictly lower hierarchy.
        return $currentUserRole->hierarchy < $modelUserRole->hierarchy;
    }
}
