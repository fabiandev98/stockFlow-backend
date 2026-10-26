<?php

namespace App\Http\Resources;

use App\Models\Recipe;
use App\Services\RecipeCostCalculator;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Recipe */
class RecipeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $costs = app(RecipeCostCalculator::class)->calculate($this->resource);

        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product' => new ProductResource($this->whenLoaded('product')),
            'version' => $this->version,
            'status' => $this->status,
            'yield_quantity' => $this->yield_quantity,
            'yield_unit' => $this->yield_unit,
            'target_food_cost_percentage' => $this->target_food_cost_percentage,
            'instructions' => $this->instructions,
            'activated_at' => $this->activated_at,
            'activated_by' => new UserResource($this->whenLoaded('activatedBy')),
            'ingredients' => RecipeIngredientResource::collection($this->whenLoaded('ingredients')),
            ...$costs,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
