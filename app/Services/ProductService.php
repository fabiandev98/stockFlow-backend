<?php

namespace App\Services;

use App\Data\Product\ProductData;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProductService
{
    /**
     * @return QueryBuilder<Product>
     */
    public function indexQueryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(
            Product::query()
                ->with('category')
                ->withCount('compositions')
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

    public function create(ProductData $data): Product
    {
        return DB::transaction(function () use ($data) {
            $product = Product::create([
                'product_category_id' => $data->product_category_id,
                'name' => $data->name,
                'sale_price' => $data->sale_price,
                'is_composed' => $data->is_composed,
                'is_active' => $data->is_active,
            ]);

            $this->rebuildCompositions($product, $data);

            return $this->freshProduct($product);
        });
    }

    public function update(Product $product, ProductData $data): Product
    {
        return DB::transaction(function () use ($product, $data) {
            $product->update([
                'product_category_id' => $data->product_category_id,
                'name' => $data->name,
                'sale_price' => $data->sale_price,
                'is_composed' => $data->is_composed,
                'is_active' => $data->is_active,
            ]);

            $this->rebuildCompositions($product, $data);

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

    private function rebuildCompositions(Product $product, ProductData $data): void
    {
        $product->compositions()->delete();

        if (! $data->is_composed) {
            return;
        }

        if (count($data->compositions) === 0) {
            throw new HttpException(
                Response::HTTP_UNPROCESSABLE_ENTITY,
                __('errors.products.composition_required')
            );
        }

        foreach ($data->compositions as $composition) {
            $product->compositions()->create([
                'material_id' => (int) $composition['material_id'],
                'quantity_required' => (float) $composition['quantity_required'],
                'unit' => (string) $composition['unit'],
            ]);
        }
    }

    private function freshProduct(Product $product): Product
    {
        return $product->fresh([
            'category',
            'compositions.material.category',
            'productBatches',
        ]) ?? $product;
    }
}
