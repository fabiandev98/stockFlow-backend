<?php

namespace App\Policies;

use App\Enums\DenebPermission;
use App\Models\MaterialCategory;
use App\Models\User;

class MaterialCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(DenebPermission::MATERIAL_CATEGORIES_READ);
    }

    public function view(User $user, MaterialCategory $materialCategory): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(DenebPermission::MATERIAL_CATEGORIES_CREATE);
    }

    public function update(User $user, MaterialCategory $materialCategory): bool
    {
        return $user->hasPermissionTo(DenebPermission::MATERIAL_CATEGORIES_UPDATE);
    }

    public function delete(User $user, MaterialCategory $materialCategory): bool
    {
        return $user->hasPermissionTo(DenebPermission::MATERIAL_CATEGORIES_DELETE);
    }
}
