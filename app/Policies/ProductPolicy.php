<?php

namespace App\Policies;

use App\Enums\DenebPermission;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(DenebPermission::PRODUCTS_READ);
    }

    public function view(User $user, Product $product): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(DenebPermission::PRODUCTS_CREATE);
    }

    public function update(User $user, Product $product): bool
    {
        return $user->hasPermissionTo(DenebPermission::PRODUCTS_UPDATE);
    }

    public function delete(User $user, Product $product): bool
    {
        return $user->hasPermissionTo(DenebPermission::PRODUCTS_DELETE);
    }
}
