<?php

namespace App\Data\Sale;

use Spatie\LaravelData\Data;

class SaleData extends Data
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function __construct(
        public string $sale_date,
        public ?string $notes,
        public array $items,
        public ?int $covers = null,
        public float $discount_amount = 0,
        public float $tax_rate = 0,
    ) {}
}
