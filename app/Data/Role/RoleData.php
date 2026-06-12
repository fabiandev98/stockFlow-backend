<?php

namespace App\Data\Role;

use Spatie\LaravelData\Data;

class RoleData extends Data
{
    public function __construct(
        public string $name,
        public int $hierarchy,
        public ?string $description,
        /** @var array<string> */
        public array $permissions,
    ) {}
}
