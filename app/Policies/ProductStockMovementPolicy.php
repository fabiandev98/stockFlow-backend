<?php

namespace App\Policies;

use App\Enums\DenebPermission;
use App\Models\ProductStockMovement;
use App\Models\User;

class ProductStockMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(DenebPermission::STOCK_MOVEMENTS_READ);
    }

    public function view(User $user, ProductStockMovement $productStockMovement): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(DenebPermission::STOCK_MOVEMENTS_CREATE);
    }
}
