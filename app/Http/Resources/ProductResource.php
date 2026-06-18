<?php

namespace App\Http\Resources;

use App\Models\Product;
use App\Models\StockBatch;
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
        $availableToSell = $this->availableToSell();

        return [
            'id' => $this->id,
            'product_category_id' => $this->product_category_id,
            'category' => new ProductCategoryResource($this->whenLoaded('category')),
            'name' => $this->name,
            'sale_price' => $this->sale_price,
            'is_composed' => $this->is_composed,
            'is_active' => $this->is_active,
            'available_to_sell' => number_format($availableToSell, 2, '.', ''),
            'compositions_count' => $this->whenCounted('compositions'),
            'compositions' => ProductCompositionResource::collection($this->whenLoaded('compositions')),
            'product_batches' => ProductBatchResource::collection($this->whenLoaded('productBatches')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function availableToSell(): float
    {
        if (! $this->is_composed) {
            return (float) $this->productBatches()
                ->where('status', 'available')
                ->sum('available_quantity');
        }

        $compositions = $this->relationLoaded('compositions')
            ? $this->compositions
            : $this->compositions()->get();

        if ($compositions->isEmpty()) {
            return 0;
        }

        $limits = $compositions->map(function ($composition) {
            $requiredQuantity = (float) $composition->quantity_required;

            if ($requiredQuantity <= 0) {
                return 0;
            }

            $availableQuantity = (float) StockBatch::query()
                ->where('material_id', $composition->material_id)
                ->where('status', 'available')
                ->sum('available_quantity');

            return floor($availableQuantity / $requiredQuantity);
        });

        return (float) max(0, $limits->min() ?? 0);
    }
}
