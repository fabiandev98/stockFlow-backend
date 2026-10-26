<?php

namespace App\Services;

use App\Models\Material;
use App\Models\Product;
use App\Models\ProductBatch;
use App\Models\StockBatch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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
                ->withSum(['stockBatches as available_stock' => fn (Builder $query) => $this->availableBatchConstraint($query)], 'available_quantity')
                ->withMin(['stockBatches as next_expiration_date' => fn (Builder $query) => $this->availableBatchConstraint($query)], 'expiration_date')
                ->withAvg('outputProcessings as average_processing_yield', 'yield_percentage')
                ->orderBy('name')
        )
            ->allowedFilters([
                'unit',
                'is_perishable',
                AllowedFilter::exact('processing_type'),
                AllowedFilter::exact('material_category_id'),
                AllowedFilter::callback('low_stock', function (Builder $query, $value) {
                    if (! filter_var($value, FILTER_VALIDATE_BOOLEAN)) {
                        return;
                    }

                    $query->whereRaw(
                        'COALESCE((SELECT SUM(stock_batches.available_quantity) FROM stock_batches WHERE stock_batches.material_id = materials.id AND stock_batches.status = ? AND (stock_batches.expiration_date IS NULL OR stock_batches.expiration_date >= ?)), 0) <= materials.minimum_stock',
                        ['available', today()->toDateString()]
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
                'average_processing_yield',
            ]);
    }

    /**
     * @return QueryBuilder<Product>
     */
    public function productSummaryQueryBuilder(): QueryBuilder
    {
        return QueryBuilder::for(
            Product::query()
                ->where('is_composed', false)
                ->with('category')
                ->withSum(['productBatches as available_stock' => fn (Builder $query) => $this->availableBatchConstraint($query)], 'available_quantity')
                ->withMin(['productBatches as next_expiration_date' => fn (Builder $query) => $this->availableBatchConstraint($query)], 'expiration_date')
                ->orderBy('name')
        )
            ->allowedFilters([
                AllowedFilter::exact('is_active'),
                AllowedFilter::exact('product_category_id'),
                AllowedFilter::callback('global', function (Builder $query, $value) {
                    $query
                        ->where('name', 'LIKE', "%$value%")
                        ->orWhereHas('category', function (Builder $query) use ($value) {
                            $query->where('name', 'LIKE', "%$value%");
                        });
                }),
            ])
            ->allowedSorts([
                'name',
                'sale_price',
                'available_stock',
                'next_expiration_date',
            ]);
    }

    /**
     * @return array{
     *     low_stock_materials: \Illuminate\Database\Eloquent\Collection<int, Material>,
     *     expired_batches: \Illuminate\Database\Eloquent\Collection<int, StockBatch>,
     *     expired_product_batches: \Illuminate\Database\Eloquent\Collection<int, ProductBatch>,
     *     expiring_batches: \Illuminate\Database\Eloquent\Collection<int, StockBatch>,
     *     expiring_product_batches: \Illuminate\Database\Eloquent\Collection<int, ProductBatch>
     * }
     */
    public function alerts(int $expirationWindowDays = 7): array
    {
        $today = Carbon::today();
        $expirationLimit = $today->copy()->addDays($expirationWindowDays);

        return [
            'low_stock_materials' => Material::query()
                ->with('category')
                ->withSum(['stockBatches as available_stock' => fn (Builder $query) => $this->availableBatchConstraint($query)], 'available_quantity')
                ->withMin(['stockBatches as next_expiration_date' => fn (Builder $query) => $this->availableBatchConstraint($query)], 'expiration_date')
                ->whereRaw(
                    'COALESCE((SELECT SUM(stock_batches.available_quantity) FROM stock_batches WHERE stock_batches.material_id = materials.id AND stock_batches.status = ? AND (stock_batches.expiration_date IS NULL OR stock_batches.expiration_date >= ?)), 0) <= materials.minimum_stock',
                    ['available', today()->toDateString()]
                )
                ->orderBy('name')
                ->limit(25)
                ->get(),
            'expired_batches' => StockBatch::query()
                ->with(['material.category', 'purchaseItem.purchase.supplier'])
                ->where('available_quantity', '>', 0)
                ->whereNotNull('expiration_date')
                ->whereDate('expiration_date', '<', $today)
                ->orderBy('expiration_date')
                ->limit(25)
                ->get(),
            'expired_product_batches' => ProductBatch::query()
                ->with(['product.category', 'purchaseItem.purchase.supplier'])
                ->where('available_quantity', '>', 0)
                ->whereNotNull('expiration_date')
                ->whereDate('expiration_date', '<', $today)
                ->orderBy('expiration_date')
                ->limit(25)
                ->get(),
            'expiring_batches' => StockBatch::query()
                ->with(['material.category', 'purchaseItem.purchase.supplier'])
                ->where('available_quantity', '>', 0)
                ->whereNotNull('expiration_date')
                ->whereDate('expiration_date', '>=', $today)
                ->whereDate('expiration_date', '<=', $expirationLimit)
                ->orderBy('expiration_date')
                ->limit(25)
                ->get(),
            'expiring_product_batches' => ProductBatch::query()
                ->with(['product.category', 'purchaseItem.purchase.supplier'])
                ->where('available_quantity', '>', 0)
                ->whereNotNull('expiration_date')
                ->whereDate('expiration_date', '>=', $today)
                ->whereDate('expiration_date', '<=', $expirationLimit)
                ->orderBy('expiration_date')
                ->limit(25)
                ->get(),
        ];
    }

    /**
     * @param  Builder<\App\Models\StockBatch|\App\Models\ProductBatch>  $query
     */
    private function availableBatchConstraint(Builder $query): void
    {
        $query
            ->where('status', 'available')
            ->where('available_quantity', '>', 0)
            ->where(function (Builder $query): void {
                $query->whereNull('expiration_date')
                    ->orWhereDate('expiration_date', '>=', today());
            });
    }
}
