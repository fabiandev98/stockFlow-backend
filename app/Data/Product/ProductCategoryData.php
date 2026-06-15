<?php

namespace App\Data\Product;

use Spatie\LaravelData\Data;

class ProductCategoryData extends Data
{
    public function __construct(
        public readonly string $name,
    ) {}
}
