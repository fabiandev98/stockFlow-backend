<?php

namespace App\Services;

use App\Data\Product\ProductCategoryData;
use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProductCategoryService
{
    /**
     * @return QueryBuilder<ProductCategory>
     */
    public function indexQueryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(ProductCategory::query()->withCount('products')->orderBy('name'))
            ->allowedFilters([
                AllowedFilter::callback('global', function (Builder $query, $value) {
                    $query->where('name', 'LIKE', "%$value%");
                }),
            ]);
    }

    public function create(ProductCategoryData $data): ProductCategory
    {
        return ProductCategory::create([
            'name' => $data->name,
        ]);
    }

    public function update(ProductCategory $productCategory, ProductCategoryData $data): ProductCategory
    {
        $productCategory->update([
            'name' => $data->name,
        ]);

        return $productCategory->fresh(['products']) ?? $productCategory;
    }

    public function delete(ProductCategory $productCategory): void
    {
        if ($productCategory->products()->exists()) {
            throw new HttpException(
                Response::HTTP_CONFLICT,
                'Cannot delete a category assigned to products'
            );
        }

        $productCategory->delete();
    }
}
