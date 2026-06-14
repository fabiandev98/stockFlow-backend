<?php

namespace App\Policies;

use App\Enums\DenebPermission;
use App\Models\StockBatch;
use App\Models\User;

class StockBatchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermissionTo(DenebPermission::STOCK_BATCHES_READ);
    }

    public function view(User $user, StockBatch $stockBatch): bool
    {
        return $this->viewAny($user);
    }
}
