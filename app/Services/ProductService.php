<?php

namespace App\Services;

use App\Data\Product\ProductData;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProductService
{
    public function __construct(private readonly RecipeActivator $recipeActivator) {}

    /**
     * @return QueryBuilder<Product>
     */
    public function indexQueryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(
            Product::query()
                ->with(['category', 'activeRecipe.ingredients'])
                ->withSum('productBatches as simple_available_stock', 'available_quantity')
                ->orderBy('name')
        )
            ->allowedFilters([
                AllowedFilter::exact('is_active'),
                AllowedFilter::exact('is_composed'),
                AllowedFilter::exact('product_category_id'),
                AllowedFilter::callback('global', function (Builder $query, $value) {
                    $query
                        ->where('name', 'LIKE', "%$value%")
                        ->orWhereHas('category', function (Builder $query) use ($value) {
                            $query->where('name', 'LIKE', "%$value%");
                        });
                }),
            ]);
    }

    public function create(ProductData $data, ?User $user = null): Product
    {
        return DB::transaction(function () use ($data, $user) {
            $product = Product::create([
                'product_category_id' => $data->product_category_id,
                'name' => $data->name,
                'sale_price' => $data->sale_price,
                'is_composed' => $data->is_composed,
                'is_active' => $data->is_active,
            ]);

            $this->syncCompositionRecipe($product, $data, $user, true);

            return $this->freshProduct($product);
        });
    }

    public function update(Product $product, ProductData $data, ?User $user = null): Product
    {
        return DB::transaction(function () use ($product, $data, $user) {
            $product->update([
                'product_category_id' => $data->product_category_id,
                'name' => $data->name,
                'sale_price' => $data->sale_price,
                'is_composed' => $data->is_composed,
                'is_active' => $data->is_active,
            ]);

            $this->syncCompositionRecipe($product, $data, $user);

            return $this->freshProduct($product);
        });
    }

    public function delete(Product $product): void
    {
        if ($product->saleItems()->exists()) {
            throw new HttpException(
                Response::HTTP_CONFLICT,
                __('errors.products.delete_used_in_sales')
            );
        }

        if ($product->productBatches()->exists()) {
            throw new HttpException(
                Response::HTTP_CONFLICT,
                __('errors.products.delete_has_inventory')
            );
        }

        $product->delete();
    }

    private function syncCompositionRecipe(Product $product, ProductData $data, ?User $user, bool $isCreating = false): void
    {
        if (! $data->is_composed) {
            $this->recipeActivator->archiveActive($product);

            return;
        }

        if (count($data->compositions) === 0) {
            if ($isCreating || ! $product->activeRecipe()->exists()) {
                throw new HttpException(
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    __('errors.products.composition_required')
                );
            }

            return;
        }

        $ingredients = array_map(
            fn (array $composition): array => [
                'material_id' => (int) $composition['material_id'],
                'net_quantity' => (float) $composition['quantity_required'],
            ],
            $data->compositions,
        );

        if (! $isCreating && $this->recipeActivator->matchesActiveRecipe($product, $ingredients)) {
            return;
        }

        $this->recipeActivator->activate($product, [], $ingredients, $user);
    }

    private function freshProduct(Product $product): Product
    {
        return $product->fresh([
            'category',
            'activeRecipe.ingredients.material.category',
            'productBatches',
        ]) ?? $product;
    }
}
