<?php

namespace App\Policies;

use App\Enums\DenebPermission;
use App\Models\Material;
use App\Models\User;

class MaterialPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(DenebPermission::MATERIALS_READ);
    }

    public function view(User $user, Material $material): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(DenebPermission::MATERIALS_CREATE);
    }

    public function update(User $user, Material $material): bool
    {
        return $user->hasPermissionTo(DenebPermission::MATERIALS_UPDATE);
    }

    public function delete(User $user, Material $material): bool
    {
        return $user->hasPermissionTo(DenebPermission::MATERIALS_DELETE);
    }
}
