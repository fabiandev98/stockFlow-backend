<?php

namespace App\Http\Resources;

use App\Models\ProductStockMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ProductStockMovement
 */
class ProductStockMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $productBatch = $this->relationLoaded('productBatch') ? $this->productBatch : null;
        $totalCost = $productBatch === null
            ? null
            : number_format((float) $this->quantity * (float) $productBatch->unit_cost, 2, '.', '');

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product' => new ProductResource($this->whenLoaded('product')),
            'product_batch_id' => $this->product_batch_id,
            'product_batch' => new ProductBatchResource($this->whenLoaded('productBatch')),
            'sale_item_id' => $this->sale_item_id,
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'type' => $this->type,
            'quantity' => $this->quantity,
            'total_cost' => $this->when($productBatch !== null, $totalCost),
            'reason' => $this->reason,
            'movement_date' => $this->movement_date,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
