<?php

namespace App\Data\User;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\Optional;

class UserData extends Data
{
    public function __construct(
        public string|Optional $name = new Optional,
        public string|Optional $email = new Optional,
        public string|Optional $password = new Optional,
        public string|Optional $password_confirmation = new Optional,
        public string|Optional $current_password = new Optional,
        public int|Optional $role_id = new Optional,
    ) {}

    public function hasPassword(): bool
    {
        return ! ($this->password instanceof Optional);
    }

    public function hasRoleId(): bool
    {
        return ! ($this->role_id instanceof Optional);
    }

    public function getRoleIdOrNull(): ?int
    {
        return $this->role_id instanceof Optional ? null : $this->role_id;
    }
}
