<?php

namespace App\Http\Resources;

use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Material
 */
class InventoryMaterialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $availableStock = (float) ($this->getAttribute('available_stock') ?? 0);
        $minimumStock = (float) $this->minimum_stock;
        $nextExpirationDate = $this->getAttribute('next_expiration_date');

        return [
            'id' => $this->id,
            'material_category_id' => $this->material_category_id,
            'category' => new MaterialCategoryResource($this->whenLoaded('category')),
            'name' => $this->name,
            'unit' => $this->unit,
            'minimum_stock' => $this->minimum_stock,
            'available_stock' => number_format($availableStock, 2, '.', ''),
            'is_low_stock' => $availableStock <= $minimumStock,
            'is_perishable' => $this->is_perishable,
            'next_expiration_date' => $nextExpirationDate,
        ];
    }
}
