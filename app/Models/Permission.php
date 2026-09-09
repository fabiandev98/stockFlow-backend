<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;

class Permission extends SpatiePermission
{
    public $guarded = [];

    /** @var string */
    protected $guard_name = 'sanctum';
}
