<?php

namespace App\Http\Resources;

use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Sale
 */
class SaleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user' => new UserResource($this->whenLoaded('user')),
            'sale_date' => $this->sale_date,
            'covers' => $this->covers,
            'subtotal_amount' => $this->subtotal_amount,
            'discount_amount' => $this->discount_amount,
            'tax_rate' => $this->tax_rate,
            'tax_amount' => $this->tax_amount,
            'total_amount' => $this->total_amount,
            'status' => $this->status,
            'cancelled_at' => $this->cancelled_at,
            'cancelled_by_user_id' => $this->cancelled_by_user_id,
            'cancelled_by' => new UserResource($this->whenLoaded('cancelledBy')),
            'cancellation_reason' => $this->cancellation_reason,
            'notes' => $this->notes,
            'items_count' => $this->whenCounted('items'),
            'items' => SaleItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
