<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockBatch;

class ProductAvailabilityCalculator
{
    public function calculate(Product $product): float
    {
        if ($product->usesProductBatches()) {
            return (float) $product->productBatches()
                ->available()
                ->validOn()
                ->sum('available_quantity');
        }

        $recipe = $product->relationLoaded('activeRecipe')
            ? $product->activeRecipe
            : $product->activeRecipe()->with('ingredients')->first();

        if ($recipe === null) {
            return 0;
        }

        $ingredients = $recipe->ingredients;

        if ($ingredients->isEmpty()) {
            return 0;
        }

        $limits = $ingredients->map(function ($ingredient): float {
            $requiredQuantity = (float) $ingredient->net_quantity;

            if ($requiredQuantity <= 0) {
                return 0;
            }

            $availableQuantity = (float) StockBatch::query()
                ->where('material_id', $ingredient->material_id)
                ->available()
                ->validOn()
                ->sum('available_quantity');

            return floor($availableQuantity / $requiredQuantity);
        });

        return (float) max(0, $limits->min() ?? 0);
    }
}
