<?php

namespace App\Policies;

use App\Enums\DenebPermission;
use App\Models\ProductCategory;
use App\Models\User;

class ProductCategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(DenebPermission::PRODUCT_CATEGORIES_READ);
    }

    public function view(User $user, ProductCategory $productCategory): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(DenebPermission::PRODUCT_CATEGORIES_CREATE);
    }

    public function update(User $user, ProductCategory $productCategory): bool
    {
        return $user->hasPermissionTo(DenebPermission::PRODUCT_CATEGORIES_UPDATE);
    }

    public function delete(User $user, ProductCategory $productCategory): bool
    {
        return $user->hasPermissionTo(DenebPermission::PRODUCT_CATEGORIES_DELETE);
    }
}
