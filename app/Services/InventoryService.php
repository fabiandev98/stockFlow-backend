<?php

namespace App\Services;

use App\Models\Material;
use Illuminate\Database\Eloquent\Builder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class InventoryService
{
    /**
     * @return QueryBuilder<Material>
     */
    public function materialSummaryQueryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(
            Material::query()
                ->with('category')
                ->withSum('stockBatches as available_stock', 'available_quantity')
                ->withMin('stockBatches as next_expiration_date', 'expiration_date')
                ->orderBy('name')
        )
            ->allowedFilters([
                'unit',
                'is_perishable',
                AllowedFilter::exact('material_category_id'),
                AllowedFilter::callback('low_stock', function (Builder $query, $value) {
                    if (! filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                        return;
                    }

                    $query->whereRaw(
                        'COALESCE((SELECT SUM(stock_batches.available_quantity) FROM stock_batches WHERE stock_batches.material_id = materials.id), 0) <= materials.minimum_stock'
                    );
                }),
                AllowedFilter::callback('global', function (Builder $query, $value) {
                    $query
                        ->where('name', 'LIKE', "%$value%")
                        ->orWhere('unit', 'LIKE', "%$value%")
                        ->orWhereHas('category', function (Builder $query) use ($value) {
                            $query->where('name', 'LIKE', "%$value%");
                        });
                }),
            ])
            ->allowedSorts([
                'name',
                'unit',
                'minimum_stock',
                'available_stock',
                'next_expiration_date',
            ]);
    }
}
