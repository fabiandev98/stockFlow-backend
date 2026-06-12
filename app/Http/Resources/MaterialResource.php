<?php

namespace App\Http\Resources;

use App\Models\Material;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Material
 */
class MaterialResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'material_category_id' => $this->material_category_id,
            'category' => new MaterialCategoryResource($this->whenLoaded('category')),
            'name' => $this->name,
            'unit' => $this->unit,
            'minimum_stock' => $this->minimum_stock,
            'is_perishable' => $this->is_perishable,
            'default_expiration_days' => $this->default_expiration_days,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
