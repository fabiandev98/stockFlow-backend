<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Product;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

class RecipeActivator
{
    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{material_id: int, net_quantity: float}>  $ingredients
     */
    public function activate(Product $product, array $attributes, array $ingredients, ?User $user): Recipe
    {
        return DB::transaction(function () use ($product, $attributes, $ingredients, $user): Recipe {
            $product = Product::query()->lockForUpdate()->findOrFail($product->id);
            $product->recipes()->where('status', 'active')->update(['status' => 'archived']);

            $recipe = $product->recipes()->create([
                'version' => (int) ($product->recipes()->max('version') ?? 0) + 1,
                'status' => 'active',
                'yield_quantity' => $attributes['yield_quantity'] ?? 1,
                'yield_unit' => $attributes['yield_unit'] ?? 'u',
                'target_food_cost_percentage' => $attributes['target_food_cost_percentage'] ?? null,
                'instructions' => $attributes['instructions'] ?? null,
                'activated_by' => $user?->id,
                'activated_at' => now(),
            ]);

            $materials = Material::query()
                ->whereKey(collect($ingredients)->pluck('material_id')->unique())
                ->get()
                ->keyBy('id');

            foreach ($ingredients as $position => $ingredient) {
                $material = $materials->get($ingredient['material_id']);

                if (! ($material instanceof Material)) {
                    throw (new ModelNotFoundException)->setModel(Material::class, [$ingredient['material_id']]);
                }

                $recipe->ingredients()->create([
                    'material_id' => $material->id,
                    'net_quantity' => $ingredient['net_quantity'],
                    'unit' => $material->unit,
                    'position' => $position,
                ]);
            }

            $product->update(['is_composed' => true]);

            return $recipe->load(['product.category', 'ingredients.material.category', 'activatedBy']);
        });
    }

    /** @param array<int, array{material_id: int, net_quantity: float}> $ingredients */
    public function matchesActiveRecipe(Product $product, array $ingredients): bool
    {
        $activeIngredients = $product->activeRecipe()
            ->with('ingredients')
            ->first()
            ?->ingredients;

        if ($activeIngredients === null || $activeIngredients->count() !== count($ingredients)) {
            return false;
        }

        foreach ($activeIngredients->values() as $position => $ingredient) {
            $expected = $ingredients[$position];

            if (
                $ingredient->material_id !== $expected['material_id']
                || number_format((float) $ingredient->net_quantity, 4, '.', '') !== number_format($expected['net_quantity'], 4, '.', '')
            ) {
                return false;
            }
        }

        return true;
    }

    public function archiveActive(Product $product): void
    {
        $product->recipes()->where('status', 'active')->update(['status' => 'archived']);
    }
}
