<?php

namespace App\Policies;

use App\Enums\DenebPermission;
use App\Models\StockMovement;
use App\Models\User;

class StockMovementPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(DenebPermission::STOCK_MOVEMENTS_READ);
    }

    public function view(User $user, StockMovement $stockMovement): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(DenebPermission::STOCK_MOVEMENTS_CREATE);
    }
}
