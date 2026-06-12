<?php

namespace App\Data\MaterialCategory;

use Spatie\LaravelData\Data;

class MaterialCategoryData extends Data
{
    public function __construct(
        public readonly string $name,
    ) {}
}
