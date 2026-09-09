<?php

namespace App\Data\ProductStockMovement;

use Spatie\LaravelData\Data;

class ProductStockMovementData extends Data
{
    public function __construct(
        public readonly int $product_batch_id,
        public readonly string $type,
        public readonly float $quantity,
        public readonly string $reason,
        public readonly ?string $movement_date,
    ) {}
}
