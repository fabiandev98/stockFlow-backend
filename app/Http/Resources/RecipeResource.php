<?php

namespace App\Http\Resources;

use App\Models\Recipe;
use App\Models\StockBatch;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Recipe */
class RecipeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $ingredients = $this->relationLoaded('ingredients') ? $this->ingredients : collect();
        $cost = $ingredients->sum(function ($ingredient): float {
            $available = StockBatch::query()
                ->where('material_id', $ingredient->material_id)
                ->where('status', 'available')
                ->where(function ($query): void {
                    $query->whereNull('expiration_date')->orWhereDate('expiration_date', '>=', today());
                })
                ->selectRaw('COALESCE(SUM(available_quantity * unit_cost) / NULLIF(SUM(available_quantity), 0), 0) as average_cost')
                ->value('average_cost');

            return (float) $ingredient->consumption_quantity * (float) $available;
        });
        $costPerUnit = $this->yield_quantity > 0 ? $cost / (float) $this->yield_quantity : 0;
        $salePrice = (float) ($this->product?->sale_price ?? 0);
        $foodCost = $salePrice > 0 ? ($costPerUnit / $salePrice) * 100 : 0;

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
            'theoretical_cost' => round($cost, 2),
            'cost_per_unit' => round($costPerUnit, 2),
            'food_cost_percentage' => round($foodCost, 2),
            'gross_margin_per_unit' => round($salePrice - $costPerUnit, 2),
            'suggested_sale_price' => $this->target_food_cost_percentage && $this->target_food_cost_percentage > 0
                ? round($costPerUnit / ((float) $this->target_food_cost_percentage / 100), 2)
                : null,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
