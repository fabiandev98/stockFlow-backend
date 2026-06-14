<?php

namespace App\Policies;

use App\Enums\DenebPermission;
use App\Models\Purchase;
use App\Models\User;

class PurchasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(DenebPermission::PURCHASES_READ);
    }

    public function view(User $user, Purchase $purchase): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(DenebPermission::PURCHASES_CREATE);
    }

    public function update(User $user, Purchase $purchase): bool
    {
        return $user->hasPermissionTo(DenebPermission::PURCHASES_UPDATE);
    }

    public function delete(User $user, Purchase $purchase): bool
    {
        return $user->hasPermissionTo(DenebPermission::PURCHASES_DELETE);
    }
}
