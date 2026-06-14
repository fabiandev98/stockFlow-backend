<?php

namespace App\Policies;

use App\Enums\DenebPermission;
use App\Models\Supplier;
use App\Models\User;

class SupplierPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(DenebPermission::SUPPLIERS_READ);
    }

    public function view(User $user, Supplier $supplier): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasPermissionTo(DenebPermission::SUPPLIERS_CREATE);
    }

    public function update(User $user, Supplier $supplier): bool
    {
        return $user->hasPermissionTo(DenebPermission::SUPPLIERS_UPDATE);
    }

    public function delete(User $user, Supplier $supplier): bool
    {
        return $user->hasPermissionTo(DenebPermission::SUPPLIERS_DELETE);
    }
}
