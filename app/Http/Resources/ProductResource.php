<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Services\ProductAvailabilityCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $availableToSell = app(ProductAvailabilityCalculator::class)->calculate($this->resource);

        return [
            'id' => $this->id,
            'product_category_id' => $this->product_category_id,
            'category' => new ProductCategoryResource($this->whenLoaded('category')),
            'name' => $this->name,
            'sale_price' => $this->sale_price,
            'is_composed' => $this->is_composed,
            'is_active' => $this->is_active,
            'available_to_sell' => number_format($availableToSell, 2, '.', ''),
            'compositions_count' => $this->when(
                $this->relationLoaded('activeRecipe'),
                fn () => $this->activeRecipe?->ingredients->count() ?? 0,
            ),
            'compositions' => $this->when(
                $this->relationLoaded('activeRecipe'),
                fn () => $this->activeRecipe?->ingredients->map(fn ($ingredient) => [
                    'id' => $ingredient->id,
                    'product_id' => $this->id,
                    'material_id' => $ingredient->material_id,
                    'material' => $ingredient->relationLoaded('material') ? new MaterialResource($ingredient->material) : null,
                    'quantity_required' => $ingredient->net_quantity,
                    'unit' => $ingredient->unit,
                ]) ?? [],
            ),
            'product_batches' => ProductBatchResource::collection($this->whenLoaded('productBatches')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
