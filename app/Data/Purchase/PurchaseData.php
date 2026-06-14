<?php

namespace App\Data\Purchase;

use Spatie\LaravelData\Data;

class PurchaseData extends Data
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function __construct(
        public readonly ?int $supplier_id,
        public readonly string $purchase_date,
        public readonly ?string $notes,
        public readonly array $items,
    ) {}
}
