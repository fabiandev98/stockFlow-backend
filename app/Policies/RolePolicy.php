<?php

namespace App\Policies;

use App\Enums\DenebPermission;
use App\Models\Role;
use App\Models\User;

class RolePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(DenebPermission::ROLES_READ);
    }

    /**
     * Determine whether the user can view the model.
     * A user can view a role if they have the permission and:
     * - They are a Superadmin.
     * - The role to view has a lower hierarchy than their own.
     */
    public function view(User $user, Role $role): bool
    {
        if (! $this->viewAny($user)) {
            return false;
        }

        $currentUserRole = $user->role();
        if (! $currentUserRole) {
            return false; // User without a role cannot view specific roles.
        }

        // Superadmin can view any role.
        if ($currentUserRole->isSuperAdmin()) {
            return true;
        }

        return $this->checkHierarchy($currentUserRole, $role);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermissionTo(DenebPermission::ROLES_CREATE);
    }

    /**
     * Determine whether the user can update the model.
     * A user can update a role if they have the permission and:
     * - They are a Superadmin.
     * - The role to update has a lower hierarchy than their own.
     */
    public function update(User $user, Role $role): bool
    {
        if (! $user->hasPermissionTo(DenebPermission::ROLES_UPDATE)) {
            return false;
        }

        $currentUserRole = $user->role();
        if (! $currentUserRole) {
            return false; // User without a role cannot update roles.
        }

        // Superadmin can update any role.
        if ($currentUserRole->isSuperAdmin()) {
            return true;
        }

        return $this->checkHierarchy($currentUserRole, $role);
    }

    /**
     * Determine whether the user can delete the model.
     * A user can delete a role if they have the permission and:
     * - The role to be deleted is NOT the Superadmin role.
     * - They are a Superadmin.
     * - The role to delete has a lower hierarchy than their own.
     */
    public function delete(User $user, Role $role): bool
    {
        // CRITICAL: The Superadmin role (hierarchy 0) can never be deleted.
        if ($role->isSuperAdmin()) {
            return false;
        }

        if (! $user->hasPermissionTo(DenebPermission::ROLES_DELETE)) {
            return false;
        }

        $currentUserRole = $user->role();
        if (! $currentUserRole) {
            return false; // User without a role cannot delete roles.
        }

        // Superadmin can delete any non-superadmin role.
        if ($currentUserRole->isSuperAdmin()) {
            return true;
        }

        return $this->checkHierarchy($currentUserRole, $role);
    }

    /**
     * Checks if the user's role has a higher precedence (lower hierarchy number)
     * than the target role.
     */
    private function checkHierarchy(Role $userRole, Role $role): bool
    {
        return $userRole->hierarchy < $role->hierarchy;
    }
}
