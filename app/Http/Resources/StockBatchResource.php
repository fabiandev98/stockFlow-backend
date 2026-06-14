<?php

namespace App\Http\Resources;

use App\Models\StockBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StockBatch
 */
class StockBatchResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'material_id' => $this->material_id,
            'material' => new MaterialResource($this->whenLoaded('material')),
            'purchase_item_id' => $this->purchase_item_id,
            'purchase_item' => new PurchaseItemResource($this->whenLoaded('purchaseItem')),
            'initial_quantity' => $this->initial_quantity,
            'available_quantity' => $this->available_quantity,
            'unit_cost' => $this->unit_cost,
            'received_date' => $this->received_date,
            'expiration_date' => $this->expiration_date,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
