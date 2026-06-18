<?php

namespace App\Http\Resources;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class InventoryProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $availableStock = (float) ($this->getAttribute('available_stock') ?? 0);
        $nextExpirationDate = $this->getAttribute('next_expiration_date');

        return [
            'id' => $this->id,
            'product_category_id' => $this->product_category_id,
            'category' => new ProductCategoryResource($this->whenLoaded('category')),
            'name' => $this->name,
            'sale_price' => $this->sale_price,
            'is_composed' => $this->is_composed,
            'is_active' => $this->is_active,
            'available_stock' => number_format($availableStock, 2, '.', ''),
            'next_expiration_date' => $nextExpirationDate,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
