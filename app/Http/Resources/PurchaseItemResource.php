<?php

namespace App\Http\Resources;

use App\Models\PurchaseItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PurchaseItem
 */
class PurchaseItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'purchase_id' => $this->purchase_id,
            'purchase' => new PurchaseResource($this->whenLoaded('purchase')),
            'material_id' => $this->material_id,
            'material' => new MaterialResource($this->whenLoaded('material')),
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost,
            'total_cost' => $this->total_cost,
            'expiration_date' => $this->expiration_date,
            'stock_batches' => StockBatchResource::collection($this->whenLoaded('stockBatches')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
