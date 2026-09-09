<?php

namespace App\Http\Controllers;

use App\Http\Requests\Recipe\StoreRecipeRequest;
use App\Http\Resources\RecipeResource;
use App\Models\Product;
use App\Models\User;
use App\Services\RecipeService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RecipeController extends Controller
{
    public function __construct(private readonly RecipeService $recipeService) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Product::class);

        return RecipeResource::collection($this->recipeService->activeQuery()->paginate(25));
    }

    public function show(Product $product): RecipeResource
    {
        $this->authorize('view', $product);
        $recipe = $product->recipes()->where('status', 'active')->with(['product.category', 'ingredients.material.category', 'activatedBy'])->firstOrFail();

        return new RecipeResource($recipe);
    }

    public function store(StoreRecipeRequest $request, Product $product): RecipeResource
    {
        /** @var User $user */
        $user = $request->user();

        return new RecipeResource($this->recipeService->activate($product, $request->validated(), $user));
    }
}
