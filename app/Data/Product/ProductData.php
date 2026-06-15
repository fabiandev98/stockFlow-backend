<?php

namespace App\Data\Product;

use Spatie\LaravelData\Data;

class ProductData extends Data
{
    /**
     * @param  array<int, array<string, mixed>>  $compositions
     */
    public function __construct(
        public readonly ?int $product_category_id,
        public readonly string $name,
        public readonly float $sale_price,
        public readonly bool $is_active,
        public readonly array $compositions,
    ) {}
}
