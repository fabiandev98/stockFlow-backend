<?php

namespace App\Data\Supplier;

use Spatie\LaravelData\Data;

class SupplierData extends Data
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $contact_name,
        public readonly ?string $phone,
        public readonly ?string $email,
    ) {}
}
