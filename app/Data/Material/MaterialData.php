<?php

namespace App\Data\Material;

use Spatie\LaravelData\Data;

class MaterialData extends Data
{
    public function __construct(
        public readonly ?int $material_category_id,
        public readonly string $name,
        public readonly string $processing_type,
        public readonly string $unit,
        public readonly float $minimum_stock,
        public readonly bool $is_perishable = false,
        public readonly ?int $default_expiration_days = null,
        /** @var array<int, array{raw_material_id: int, quantity_per_unit: float}> */
        public readonly array $processing_components = [],
    ) {}
}
