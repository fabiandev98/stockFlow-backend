<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductProductionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'product_id' => $this->product_id, 'product' => new ProductResource($this->whenLoaded('product')), 'planned_quantity' => $this->planned_quantity, 'produced_quantity' => $this->produced_quantity, 'production_date' => $this->production_date, 'suggested_expiration_date' => $this->suggested_expiration_date, 'expiration_date' => $this->expiration_date, 'expiration_override_reason' => $this->expiration_override_reason, 'notes' => $this->notes, 'usages' => $this->whenLoaded('usages', fn () => $this->usages->map(fn ($usage) => ['material_id' => $usage->material_id, 'material' => new MaterialResource($usage->whenLoaded('material')), 'stock_batch_id' => $usage->stock_batch_id, 'quantity' => $usage->quantity, 'unit_cost' => $usage->unit_cost])), 'created_at' => $this->created_at];
    }
}
