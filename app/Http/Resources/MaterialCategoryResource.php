<?php

namespace App\Http\Resources;

use App\Models\MaterialCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin MaterialCategory
 */
class MaterialCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'materials_count' => $this->whenCounted('materials'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
