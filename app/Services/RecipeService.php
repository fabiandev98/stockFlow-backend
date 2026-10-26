<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Recipe;
use App\Models\User;

class RecipeService
{
    public function __construct(private readonly RecipeActivator $recipeActivator) {}

    /** @return \Illuminate\Database\Eloquent\Builder<Recipe> */
    public function activeQuery(): \Illuminate\Database\Eloquent\Builder
    {
        return Recipe::query()->where('status', 'active')->with(['product.category', 'ingredients.material.category', 'activatedBy'])->latest('activated_at');
    }

    /** @param array<string, mixed> $data */
    public function activate(Product $product, array $data, User $user): Recipe
    {
        $ingredients = array_map(
            fn (array $ingredient): array => [
                'material_id' => (int) $ingredient['material_id'],
                'net_quantity' => (float) $ingredient['net_quantity'],
            ],
            $data['ingredients'],
        );

        return $this->recipeActivator->activate($product, $data, $ingredients, $user);
    }
}
