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
            'processing_type' => $this->processing_type,
            'unit' => $this->unit,
            'minimum_stock' => $this->minimum_stock,
            'is_perishable' => $this->is_perishable,
            'default_expiration_days' => $this->default_expiration_days,
            'processing_components' => $this->whenLoaded('processingComponents', fn () => $this->processingComponents->map(fn ($component) => [
                'id' => $component->id,
                'raw_material_id' => $component->raw_material_id,
                'raw_material' => new self($component->rawMaterial),
                'quantity_per_unit' => $component->quantity_per_unit,
                'unit' => $component->unit,
                'position' => $component->position,
            ])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
