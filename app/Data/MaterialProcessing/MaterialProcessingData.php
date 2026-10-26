<?php

namespace App\Data\MaterialProcessing;

use Spatie\LaravelData\Data;

class MaterialProcessingData extends Data
{
    public function __construct(
        public readonly int $output_material_id,
        public readonly float $output_quantity,
        public readonly string $processed_at,
        public readonly ?string $expiration_date,
        public readonly ?string $notes,
        /** @var array<int, array{stock_batch_id: int, quantity: float}> */
        public readonly array $inputs = [],
        public readonly ?string $operation_uuid = null,
    ) {}
}
