<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RecipeService
{
    /** @return \Illuminate\Database\Eloquent\Builder<Recipe> */
    public function activeQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Recipe::query()->where('status', 'active')->with(['product.category', 'ingredients.material.category', 'activatedBy'])->latest('activated_at');
    }

    /** @param array<string, mixed> $data */
    public function activate(Product $product, array $data, User $user): Recipe
    {
        return DB::transaction(function () use ($product, $data, $user): Recipe {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $nextVersion = (int) ($product->recipes()->max('version') ?? 0) + 1;
            $product->recipes()->where('status', 'active')->update(['status' => 'archived']);

            $recipe = $product->recipes()->create([
                'version' => $nextVersion,
                'status' => 'active',
                'yield_quantity' => $data['yield_quantity'],
                'yield_unit' => $data['yield_unit'],
                'target_food_cost_percentage' => $data['target_food_cost_percentage'] ?? null,
                'instructions' => $data['instructions'] ?? null,
                'activated_by' => $user->id,
                'activated_at' => now(),
            ]);

            $product->compositions()->delete();
            foreach ($data['ingredients'] as $position => $ingredient) {
                $material = Material::query()->findOrFail((int) $ingredient['material_id']);
                $netQuantity = (float) $ingredient['net_quantity'];

                $recipe->ingredients()->create([
                    'material_id' => $material->id,
                    'net_quantity' => $netQuantity,
                    'unit' => $material->unit,
                    'position' => $position,
                ]);
                $product->compositions()->create([
                    'material_id' => $material->id,
                    'quantity_required' => $netQuantity,
                    'unit' => $material->unit,
                ]);
            }

            $product->update(['is_composed' => true]);

            return $recipe->load(['product.category', 'ingredients.material.category', 'activatedBy']);
        });
    }
}
