<?php

namespace App\Services;

use App\Data\User\UserData;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class UserService
{
    /**
     * @return QueryBuilder<User>
     */
    public function indexQueryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(User::with(['roles', 'roles.permissions']))
            ->allowedFilters([
                AllowedFilter::callback(
                    'global',
                    /**
                     * @param  Builder<User>  $query
                     */
                    function (Builder $query, mixed $value): void {
                        $query->where('name', 'LIKE', "%{$value}%")
                            ->orWhere('email', 'LIKE', "%{$value}%");
                    }
                ),
            ]);
    }

    public function createUser(UserData $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = $this->buildUser(new User, $data);
            $this->saveUserOrFail($user, 'create');

            $this->dispatchRegisteredEvent($user);

            $roleId = $this->resolveRoleIdForCreation($data);
            $user->syncRoles([$roleId]);

            return $user->fresh() ?? $user;
        });
    }

    public function signUpUser(UserData $data): User
    {
        return DB::transaction(function () use ($data): User {
            $user = $this->buildUser(new User, $data);
            $this->saveUserOrFail($user, 'sign-up');

            $this->dispatchRegisteredEvent($user);

            $basicRoleName = $this->getBasicRoleName();
            $user->assignRole($basicRoleName);

            return $user->fresh() ?? $user;
        });
    }

    public function updateUser(User $user, UserData $data): User
    {
        return DB::transaction(function () use ($user, $data): User {
            $originalRole = $user->role();

            $this->validateRoleChange($data, $originalRole);

            $user = $this->buildUser($user, $data);
            $this->saveUserOrFail($user, 'update');

            $this->syncRoleIfNeeded($user, $data, $originalRole);

            return $user->fresh() ?? $user;
        });
    }

    public function updatePassword(User $user, UserData $data): bool
    {
        if (! $data->hasPassword()) {
            throw new HttpException(
                Response::HTTP_BAD_REQUEST,
                'Password is required for this operation.'
            );
        }

        /** @var string $password */
        $password = $data->password;
        $user->password = Hash::make($password);

        $this->saveUserOrFail($user, 'update-password');

        return true;
    }

    public function deleteUser(User $user): void
    {
        DB::transaction(function () use ($user): void {
            $role = $user->role();

            if ($role && $this->isLastAdminLeft($role)) {
                throw new HttpException(
                    Response::HTTP_CONFLICT,
                    trans('validation.custom.user_destroy.sole_admin')
                );
            }

            if (! $user->delete()) {
                $this->logError('delete', ['user_id' => $user->id]);
                throw new HttpException(
                    Response::HTTP_INTERNAL_SERVER_ERROR,
                    "Could not delete user {$user->name}."
                );
            }
        });
    }

    public function isLastAdminLeft(Role $role): bool
    {
        if (! $role->isSuperAdmin()) {
            return false;
        }

        $adminRole = Role::where(
            'hierarchy',
            Role::SUPERADMIN_HIERARCHY
        )->first();

        if (! $adminRole) {
            Log::critical('Superadmin role not found');

            return true;
        }

        return User::whereHas('roles', function ($query) use ($adminRole) {
            $query->where('roles.id', $adminRole->id);
        })->count() < 2;
    }

    // Protected methods para extensibilidad

    protected function buildUser(User $user, UserData $data): User
    {
        $user->fill($data->toArray());

        if ($data->hasPassword()) {
            /** @var string $password */
            $password = $data->password;
            $user->password = Hash::make($password);
        }

        return $user;
    }

    /**
     * @param  'create'|'update'|'sign-up'|'update-password'  $operation
     */
    protected function saveUserOrFail(User $user, string $operation): void
    {
        if (! $user->save()) {
            $this->logError($operation, [
                'email' => $user->email,
                'user_id' => $user->id,
            ]);

            $messages = [
                'create' => 'Failed to create user.',
                'update' => 'Failed to update user.',
                'sign-up' => 'Failed to create your account.',
                'update-password' => 'Failed to update password.',
            ];

            throw new HttpException(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                $messages[$operation]
            );
        }
    }

    protected function resolveRoleIdForCreation(UserData $data): int
    {
        $roleId = $data->getRoleIdOrNull();

        if ($roleId !== null) {
            return $roleId;
        }

        return $this->getDefaultRoleId();
    }

    protected function getDefaultRoleId(): int
    {
        $role = Role::orderBy('hierarchy', 'desc')->first();

        if (! $role) {
            $this->logError('get-default-role', []);
            throw new HttpException(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'Cannot assign role. No roles available in the system.'
            );
        }

        return (int) $role->id;
    }

    protected function getBasicRoleName(): string
    {
        // Get the role with the highest hierarchy number (least important role)
        $basicRole = Role::orderBy('hierarchy', 'desc')->first();

        if (! $basicRole) {
            $this->logError('get-basic-role', []);
            throw new HttpException(
                Response::HTTP_INTERNAL_SERVER_ERROR,
                'Account created, but role assignment failed. No roles available in the system.'
            );
        }

        return $basicRole->name;
    }

    protected function validateRoleChange(
        UserData $data,
        ?Role $originalRole
    ): void {
        if (! $data->hasRoleId()) {
            return;
        }

        $newRoleId = $data->getRoleIdOrNull();

        if ($newRoleId === null) {
            return;
        }

        if (! $originalRole || $originalRole->id === $newRoleId) {
            return;
        }

        if (
            $originalRole->isSuperAdmin()
            && $this->isLastAdminLeft($originalRole)
        ) {
            throw new HttpException(
                Response::HTTP_CONFLICT,
                trans('validation.custom.user_destroy.sole_admin')
            );
        }
    }

    protected function syncRoleIfNeeded(
        User $user,
        UserData $data,
        ?Role $originalRole
    ): void {
        if (! $data->hasRoleId()) {
            return;
        }

        $newRoleId = $data->getRoleIdOrNull();

        if ($newRoleId === null) {
            return;
        }

        if ($originalRole && $originalRole->id === $newRoleId) {
            return;
        }

        $user->syncRoles([$newRoleId]);
    }

    protected function dispatchRegisteredEvent(User $user): void
    {
        event(new Registered($user));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    protected function logError(string $operation, array $context): void
    {
        Log::error("User service operation failed: {$operation}", $context);
    }
}
