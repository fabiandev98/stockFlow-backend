<?php

namespace App\Policies;

use App\Enums\DenebPermission;
use App\Models\Sale;
use App\Models\User;

class SalePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(DenebPermission::SALES_READ);
    }

    public function view(User $user, Sale $sale): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(DenebPermission::SALES_CREATE);
    }
}
