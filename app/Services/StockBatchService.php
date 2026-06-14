<?php

namespace App\Services;

use App\Models\StockBatch;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class StockBatchService
{
    /**
     * @return QueryBuilder<StockBatch>
     */
    public function indexQueryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(
            StockBatch::query()
                ->with(['material.category', 'purchaseItem.purchase.supplier'])
                ->orderByRaw('expiration_date IS NULL')
                ->orderBy('expiration_date')
                ->latest('received_date')
        )
            ->allowedFilters([
                'status',
                AllowedFilter::exact('material_id'),
                AllowedFilter::callback('global', function (Builder $query, $value) {
                    $query
                        ->where('status', 'LIKE', "%$value%")
                        ->orWhereHas('material', function (Builder $query) use ($value) {
                            $query->where('name', 'LIKE', "%$value%");
                        })
                        ->orWhereHas('purchaseItem.purchase.supplier', function (Builder $query) use ($value) {
                            $query->where('name', 'LIKE', "%$value%");
                        });
                }),
            ])
            ->allowedSorts([
                'received_date',
                'expiration_date',
                'available_quantity',
                'unit_cost',
                'status',
                AllowedSort::field('material', 'material_id'),
            ]);
    }
}
