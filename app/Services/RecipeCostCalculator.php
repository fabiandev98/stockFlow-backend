<?php

namespace App\Services;

use App\Models\Recipe;
use App\Models\StockBatch;

class RecipeCostCalculator
{
    /**
     * @return array{
     *     theoretical_cost: float,
     *     cost_per_unit: float,
     *     food_cost_percentage: float,
     *     gross_margin_per_unit: float,
     *     suggested_sale_price: float|null
     * }
     */
    public function calculate(Recipe $recipe): array
    {
        $ingredients = $recipe->relationLoaded('ingredients')
            ? $recipe->ingredients
            : $recipe->ingredients()->get();
        $cost = $ingredients->sum(function ($ingredient): float {
            $averageCost = StockBatch::query()
                ->where('material_id', $ingredient->material_id)
                ->available()
                ->validOn()
                ->selectRaw('COALESCE(SUM(available_quantity * unit_cost) / NULLIF(SUM(available_quantity), 0), 0) as average_cost')
                ->value('average_cost');

            return (float) $ingredient->net_quantity * (float) $averageCost;
        });
        $costPerUnit = $recipe->yield_quantity > 0 ? $cost / (float) $recipe->yield_quantity : 0;
        $salePrice = (float) ($recipe->relationLoaded('product') ? $recipe->product?->sale_price : $recipe->product()->value('sale_price'));
        $foodCost = $salePrice > 0 ? ($costPerUnit / $salePrice) * 100 : 0;
        $targetFoodCost = (float) $recipe->target_food_cost_percentage;

        return [
            'theoretical_cost' => round($cost, 2),
            'cost_per_unit' => round($costPerUnit, 2),
            'food_cost_percentage' => round($foodCost, 2),
            'gross_margin_per_unit' => round($salePrice - $costPerUnit, 2),
            'suggested_sale_price' => $targetFoodCost > 0
                ? round($costPerUnit / ($targetFoodCost / 100), 2)
                : null,
        ];
    }
}
