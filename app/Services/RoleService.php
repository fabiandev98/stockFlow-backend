<?php

namespace App\Services;

use App\Data\Role\RoleData;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class RoleService
{
    /**
     * @return QueryBuilder<Role>
     */
    public function indexQueryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(Role::orderBy('hierarchy')->with('permissions'))
            ->allowedFilters([
                AllowedFilter::callback(
                    'global',
                    function (Builder $query, $value) {
                        $query
                            ->where('name', 'LIKE', "%$value%")
                            ->orWhere('display_name', 'LIKE', "%$value%")
                            ->orWhere('description', 'LIKE', "%$value%")
                            ->orWhere('hierarchy', '=', $value)
                            ->orWhereHas(
                                'permissions',
                                function ($query) use ($value) {
                                    $query->where(
                                        'name',
                                        'LIKE',
                                        "%$value%",
                                    );
                                },
                            );
                    },
                ),
            ]);
    }

    public function createRole(RoleData $roleData, User $currentUser): Role
    {
        $this->validateHierarchyLimit();

        return DB::transaction(function () use ($roleData, $currentUser) {
            $role = $this->createRoleInstance($roleData);
            $role->assignHierarchy($roleData->hierarchy, true);

            $this->syncRolePermissions($role, $roleData->permissions, $currentUser);

            return $role->fresh(['permissions']) ?? $role;
        });
    }

    public function updateRole(Role $role, RoleData $roleData, User $currentUser): Role
    {
        return DB::transaction(function () use ($role, $roleData, $currentUser) {
            $this->validateSuperAdminChanges($role, $roleData);

            $this->updateRoleAttributes($role, $roleData);
            $this->updateRoleHierarchy($role, $roleData);
            $this->syncRolePermissions($role, $roleData->permissions, $currentUser);

            return $this->getFreshRole($role);
        });
    }

    public function deleteRole(Role $role): void
    {
        $this->validateRoleDeletion($role);

        DB::transaction(fn () => $this->performRoleDeletion($role));
    }

    private function validateHierarchyLimit(): void
    {
        if (Role::getMaxHierarchy() >= Role::MAX_ROLE_HIERARCHY) {
            throw new HttpException(
                Response::HTTP_BAD_REQUEST,
                'Maximum hierarchy limit reached'
            );
        }
    }

    private function createRoleInstance(RoleData $roleData): Role
    {
        $role = new Role([
            'name' => strtolower($roleData->name),
            'display_name' => ucwords($roleData->name),
            'description' => $roleData->description,
        ]);
        // Do not move the following line into the array above, it won't work.
        // Temporary value. It must be at least +2 because some shifts needs to be made.
        $role->hierarchy = Role::getMaxHierarchy() + 10;

        if (! $role->save()) {
            $this->logAndThrowError('Failed to create role', $roleData->name);
        }

        return $role;
    }

    private function validateSuperAdminChanges(Role $role, RoleData $roleData): void
    {
        if (! $role->isSuperAdmin()) {
            return;
        }

        if (strtolower($roleData->name) !== $role->name) {
            throw new HttpException(
                Response::HTTP_CONFLICT,
                'Superadmin role name cannot be changed'
            );
        }

        if ($roleData->hierarchy !== Role::SUPERADMIN_HIERARCHY) {
            throw new HttpException(
                Response::HTTP_FORBIDDEN,
                'Superadmin hierarchy cannot be changed'
            );
        }
    }

    private function updateRoleAttributes(Role $role, RoleData $roleData): void
    {
        $role->fill([
            'name' => strtolower($roleData->name),
            'display_name' => ucwords($roleData->name),
            'description' => $roleData->description,
        ]);

        if (! $role->save()) {
            $this->logAndThrowError('Failed to update role attributes', null, (int) $role->id);
        }
    }

    private function updateRoleHierarchy(Role $role, RoleData $roleData): void
    {
        if ($role->hierarchy !== $roleData->hierarchy) {
            $role->assignHierarchy($roleData->hierarchy, false);
        }
    }

    /**
     * Summary of syncRolePermissions
     *
     * @param  array<string>  $permissions
     */
    private function syncRolePermissions(Role $role, array $permissions, User $currentUser): void
    {
        $allowedPermissions = $this->filterUserPermissions($permissions, $currentUser);
        $role->syncPermissions($allowedPermissions);
    }

    private function getFreshRole(Role $role): Role
    {
        $freshRole = $role->fresh(['permissions']);

        if (! $freshRole) {
            $this->logAndThrowError('Could not retrieve role after update', null, (int) $role->id);
        }

        return $freshRole;
    }

    private function validateRoleDeletion(Role $role): void
    {
        if ($role->isSuperAdmin()) {
            throw new HttpException(
                Response::HTTP_FORBIDDEN,
                'Superadmin role cannot be deleted'
            );
        }

        if (
            User::whereHas('roles', function ($query) use ($role) {
                $query->where('roles.id', $role->id);
            })->exists()
        ) {
            throw new HttpException(
                Response::HTTP_CONFLICT,
                "Cannot delete role '{$role->display_name}' - it's assigned to users"
            );
        }
    }

    private function performRoleDeletion(Role $role): void
    {
        if (! $role->delete()) {
            $this->logAndThrowError('Could not delete role', $role->name, (int) $role->id);
        }
    }

    /**
     * @param  array<string>  $requestedPermissions
     * @return array<string>
     */
    private function filterUserPermissions(array $requestedPermissions, User $user): array
    {
        $userRole = $user->role();

        if (! $userRole) {
            return [];
        }

        if ($userRole->isSuperAdmin()) {
            return array_intersect($requestedPermissions, Role::getAllDenebPermissionsNames());
        }

        $userPermissions = $user->getAllPermissions()->pluck('name')->toArray();

        return array_intersect($requestedPermissions, $userPermissions);
    }

    private function logAndThrowError(string $message, ?string $name = null, ?int $roleId = null): never
    {
        $context = array_filter(compact('name', 'roleId'));
        Log::error($message, $context);

        throw new HttpException(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'Operation failed. Please try again or contact support.'
        );
    }
}
