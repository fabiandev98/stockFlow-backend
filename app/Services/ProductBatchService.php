<?php

namespace App\Services;

use App\Models\ProductBatch;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class ProductBatchService
{
    /** @return QueryBuilder<ProductBatch> */
    public function indexQueryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(
            ProductBatch::query()
                ->with(['product.category', 'purchaseItem.purchase.supplier', 'production'])
                ->orderByRaw('expiration_date IS NULL')
                ->orderBy('expiration_date')
                ->latest('received_date')
        )->allowedFilters([
            'status',
            AllowedFilter::exact('product_id'),
            AllowedFilter::callback('global', function (Builder $query, $value) {
                $query
                    ->where('status', 'LIKE', "%$value%")
                    ->orWhereHas('product', fn (Builder $productQuery) => $productQuery->where('name', 'LIKE', "%$value%"));
            }),
        ])->allowedSorts([
            'received_date', 'expiration_date', 'available_quantity', 'unit_cost', 'status',
            AllowedSort::field('product', 'product_id'),
        ]);
    }
}
