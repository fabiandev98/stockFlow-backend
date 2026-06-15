<?php

namespace App\Data\StockMovement;

use Spatie\LaravelData\Data;

class StockMovementData extends Data
{
    public function __construct(
        public readonly int $stock_batch_id,
        public readonly string $type,
        public readonly float $quantity,
        public readonly ?string $reason,
        public readonly ?string $movement_date,
    ) {}
}
