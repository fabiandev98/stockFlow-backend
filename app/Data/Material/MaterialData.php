<?php

namespace App\Data\Material;

use Spatie\LaravelData\Data;

class MaterialData extends Data
{
    public function __construct(
        public readonly ?int $material_category_id,
        public readonly string $name,
        public readonly string $unit,
        public readonly float $minimum_stock,
        public readonly bool $is_perishable = false,
        public readonly ?int $default_expiration_days = null,
    ) {}
}
